<?php

namespace Tabusoft\ORM\Repository;

use Tabusoft\DB\DB;
use Tabusoft\DB\DBFactory;
use Tabusoft\ORM\Entity\EntityAbstract;
use Tabusoft\ORM\Repository\Filter\Filter;

abstract class RepositoryAbstract
{

    /**
     * @var DB
     */
    protected $db = null; //zf3 adapter

    public static $postSaveAction = null;
    public static $postDeleteAction = null;
    public int $last_filter_tot_rows = 0;

    /**
     * RepositoryAbstract constructor.
     * @param array $database_info
     * @throws \Exception
     */
    public function __construct()
    {
        if (!isset($this->database)) {
            throw new \Exception("You must define 'database' properties on " . get_class($this));
        }
        if (!isset($this->table)) {
            throw new \Exception("You must define 'table' properties on " . get_class($this));
        }

        $this->setTableColumnsDescription();

        $this->db = DBFactory::getInstanceByHash($this->database_hash);
    }

    abstract protected function setTableColumnsDescription();

    public function getTableColumnsDescription()
    {
        return $this->tableColumnsDescription;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function getHost()
    {
        return $this->host;
    }

    /**
     * @param EntityAbstract $entity
     * @throws \Exception
     */
    public function save(EntityAbstract $entity)
    {

        if ($this->isView) {
            throw new \Exception('You can\'t save a view');
        }

        $values = [];
        $types = [];
        $primaryGetter = self::getGetter($this->primary);
        if ($entity->{$primaryGetter}() === null) {

            foreach ($this->tableColumnsDescription as $column => $properties_info) {
                $getter = self::getGetter($column);
                $values[] = $entity->{$getter}();
                $types[] = '?';
            }

            $fields_names_string = "`" . implode("`,`", array_keys($this->tableColumnsDescription)) . "`";
            $fields_values_string = implode(",", $types);

            $sql = "INSERT INTO `{$this->table}` ({$fields_names_string})
				VALUES ({$fields_values_string})";
        } else {
            $sql = "UPDATE `{$this->table}`
                        SET ";
            $update_column = [];
            foreach ($this->tableColumnsDescription as $column => $prop_info) {
                if ($column === $this->primary) {
                    continue;
                } else {
                    $update_column[] = "{$column} = ?";
                    $getter = self::getGetter($column);
                    $values[] = $entity->{$getter}();
                }
            }
            $sql .= implode("," . PHP_EOL, $update_column);
            $sql .= PHP_EOL . "WHERE id = ?";
            $values[] = $entity->{$primaryGetter}();
        }

        $this->db->query($sql, $values);

        $getter = self::getGetter($this->primary);
        $primary = $entity->{$getter}();
        if ($primary === null) {
            $setter = self::getSetter($this->primary);
            $entity->{$setter}($this->db->lastInsertId());
        }

        if (is_callable(self::$postSaveAction)) {
            call_user_func(self::$postSaveAction, $entity);
        }
    }

    /**
     * @param $haystack
     * @param $type
     * @return bool
     */
    public function isTypeOf($haystack, $type)
    {
        return strpos(strtolower($haystack), strtolower($type)) !== false;
    }

    /**
     * @param $id
     * @return mixed
     * @throws \Exception
     */
    public function findById($id)
    {
        return $this->findBy($this->primary, $id);
    }

    public function findBy($key, $value)
    {

        if (is_array($value)) {
            $placeholder = 'IN (?)';
            $return_array = true;
        } else {
            $placeholder = '= ?';
            $return_array = false;
        }

        $result = $this->db->query("SELECT * FROM " . $this->table . " WHERE `{$key}` {$placeholder}", [$value]);

        if ($result->rowCount()) {

            $return = [];
            foreach ($result as $elem) {
                $return[$elem[$this->primary]] = $this->objectByRow($elem);
            }

            if ($return_array === false and $result->rowCount() === 1) {
                reset($return);
                return current($return);
            }

            return $return;
        }

        throw new \Exception("Can't find in {$this->table} where {$key} " . (is_array($value) ? "In " . implode(",", $value) : " = " . $value));
    }

    public function findByValues(array $keyValues)
    {
        $query = "SELECT * FROM " . $this->table . " 
            WHERE ";

        $where = [];
        $values = [];
        foreach ($keyValues as $key => $value) {
            if (!is_array($value)) {
                $where[] = "`{$key}` = ?";
                $values[] = $value;
            } else {
                $where[] = "`{$key}` IN (" . implode(", ", array_fill(0, count($value), "?")) . ")";
                $values = array_merge($values, $value);
            }
        }

        $query .= implode(" AND ", $where);

        $result = $this->db->query($query, $values);

        $return = [];
        foreach ($result as $row) {
            $return[] = $this->objectByRow($row);
        }
        return $return;

    }

    /**
     * Filter the table with the passing values
     *
     * @param Filter[] $filters
     * @param string $sort
     * @param string $direction
     * @param int $row_in_page
     * @param int $offset
     */
    public function filter(array $filters, string $sort = "KEY", string $direction = "ASC", int $row_in_page = 25, int $offset = 0)
    {

        $query = "SELECT * FROM " . $this->table . PHP_EOL .
            "WHERE ";
        $count_query = "SELECT count(*) as tot FROM " . $this->table . PHP_EOL .
            "WHERE ";

        //build where
        $condition = [];
        foreach ($filters as $filter) {
            $condition[] = $filter->compile();
        }

        if (count($condition) === 0) {
            throw new \Exception("invalid filters number in filter method");
        }
        $conditions = implode(PHP_EOL . "AND ", $condition);
        $query .= $conditions;
        $count_query .= $conditions;

        $values = [];
        foreach ($filters as $filter) {
            $val = $filter->getValue();
            if(is_array($val)){
                $values = array_merge($values, $val);
            } else {
                $values[] = $val;
            }
        }

        //build order by
        if ($sort === 'KEY') {
            $sort = $this->primary;
        }
        $query .= PHP_EOL . "ORDER BY {$sort} {$direction}";

        //build limit
        $offset = $offset * $row_in_page;
        $limit = PHP_EOL . "LIMIT {$offset}, {$row_in_page}";

        $result = $this->db->query($query . $limit, $values);

        $return = [];
        foreach ($result as $row) {
            $return[] = $this->objectByRow($row);
        }

        $result = $this->db->query($count_query, $values);
        foreach ($result as $row) {
            $this->last_filter_tot_rows = $row["tot"];
        }

        return $return;
    }

    public function objectByRow($row)
    {
        $entityClass = $this->relatedEntity;
        $entity = new $entityClass();
        foreach (array_keys($this->tableColumnsDescription) as $property) {
            $setter = self::getSetter($property);
            if (isset($row[$property])) {
                $entity->{$setter}($row[$property]);
            } else {
                $entity->{$setter}(null);
            }
        }
        return $entity;
    }

    public function rowByEntity($entity): array
    {
        $row = [];
        foreach (array_keys($this->tableColumnsDescription) as $property) {
            $getter = self::getGetter($property);
            $row[$property] = $entity->{$getter}();
        }

        return $row;
    }

    /**
     * @return array
     */
    public function findAll()
    {
        $result = $this->db->query("SELECT * FROM " . $this->table);
        $return = [];
        foreach ($result as $elem) {
            $return[$elem[$this->primary]] = $this->objectByRow($elem);
        }
        return $return;
    }

    public function delete($entities)
    {
        $ids = [];
        if (!is_array($entities)) {
            $ids[] = $entities->getId();
        } else {
            foreach ($entities as $e) {
                $ids[] = $e->getId();
            }
        }

        $sql = "DELETE FROM `{$this->table}` 
                  WHERE id IN (?)";

        $this->db->query($sql, [$ids]);

        if (is_callable(self::$postDeleteAction)) {
            call_user_func(self::$postDeleteAction, $entities);
        }
    }

    //todo truncate referenced
    public function truncate()
    {

        $sql = "TRUNCATE `{$this->table}`";
        $this->db->query($sql, []);
    }

    public function getRelation($table)
    {
        return $this->relations[$table] ?? false;
    }

    public function getAllRelations(): array
    {
        return $this->relations;
    }

    public function getHash()
    {
        return $this->database_hash;
    }

    public static function createEntityName($entity_name)
    {
        $tableName = explode("_", $entity_name);
        for ($i = 0; $i < count($tableName); $i++) {
            $tableName[$i] = strtolower($tableName[$i]);
            $tableName[$i] = ucfirst($tableName[$i]);
        }
        return implode("", $tableName);
    }

    public static function getGetter($property)
    {
        return "get" . ucfirst(self::createEntityName($property));
    }

    public static function getSetter($property)
    {
        return "set" . ucfirst(self::createEntityName($property));
    }

    /**
     * @return int
     */
    public function getLastFilterTotRows(): int
    {
        return $this->last_filter_tot_rows;
    }
}
