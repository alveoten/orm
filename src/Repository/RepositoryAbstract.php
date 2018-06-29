<?php

namespace Tabusoft\ORM\Repository;

use Tabusoft\DB\DB;
use Tabusoft\DB\DBFactory;
use Tabusoft\ORM\Entity\EntityAbstract;

abstract class RepositoryAbstract
{

    /**
     * @var DB
     */
    protected $db = null; //zf3 adapter

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
        foreach ($this->tableColumnsDescription as $column => $properties_info) {
            if (!isset($entity->{$column})) {
                $values[] = null;
            } else {
                $values[] = $entity->{$column};
            }
            $types[] = '?';
        }


        $fields_names_string = "`" . implode("`,`", array_keys($this->tableColumnsDescription)) . "`";
        $fields_values_string = implode(",", $types);

        $sql = "REPLACE INTO `{$this->table}` ({$fields_names_string})
				VALUES ({$fields_values_string})";

        $this->db->query($sql, $values);
        if ($entity->{$this->primary} === null) {
            $entity->{$this->primary} = $this->db->lastInsertId();
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

        if ($this->tableColumnsDescription[$key]["orm_type"] === 'integer') {
            if (is_array($value)) {
                $placeholder = 'IN (?)';
                $return_array = true;
            } else {
                $placeholder = '= ?';
                $return_array = false;
            }
        } else {
            if (is_array($value)) {
                $placeholder = 'IN (?)';
                $return_array = true;
            } else {
                $placeholder = '= ?';
                $return_array = false;
            }
        }

        $result = $this->db->query("SELECT * FROM " . $this->table . " WHERE `{$key}` {$placeholder}", [$value]);

        if ($result->rowCount()) {

            $return = [];
            foreach ($result as $elem) {
                $return[$elem[$this->primary]] = $this->objectByRow($elem);
            }

            if ($return_array === false AND $result->rowCount() === 1) {
                reset($return);
                return current($return);
            }

            return $return;
        }

        throw new \Exception("Can't find in {$this->table} where {$key} " . (is_array($value) ? "In " . implode(",", $value) : " = " . $value));
    }

    public function objectByRow($row)
    {
        $entityClass = $this->relatedEntity;
        $entity = new $entityClass();
        foreach (array_keys($this->tableColumnsDescription) as $property) {
            $entity->{$property} = $row[$property];
        }
        return $entity;
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
        if (!is_array($entities))
            $ids = $entities->id;
        else {
            foreach ($entities as $e)
                $ids[] = $e->id;
        }

        $sql = "DELETE FROM `{$this->table}` 
                  WHERE id IN (?)";

        $this->db->query($sql, [$ids]);
    }

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

}