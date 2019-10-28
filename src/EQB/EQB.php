<?php

namespace Tabusoft\ORM\EQB;

use Tabusoft\DB\DB;
use Tabusoft\DB\DBFactory;
use Tabusoft\ORM\Repository\RepositoryAbstract;


/**
 * Created by PhpStorm.
 * User: marco
 * Date: 14/10/16
 * Time: 14.46
 */
class EQB
{

    private static $commandList = [
        'hydrate' => 'SELECT',
        'select' => 'SELECT',
        'from' => 'FROM',
        'join' => 'JOIN',
        'innerjoin' => 'INNER JOIN',
        'leftjoin' => 'LEFT JOIN',
        'rightjoin' => 'RIGHT JOIN',
        'where' => 'WHERE',
        'groupby' => 'GROUP BY',
        'orderby' => 'ORDER BY',
        'having' => 'HAVING',
        'limit' => 'LIMIT',

        'insert' => 'INSERT',
        'replace' => 'REPLACE',
        'update' => 'UPDATE',
        'into' => 'INTO',
        'values' => 'VALUES',
        'set' => 'SET',

        'delete' => 'DELETE',
    ];
    private static $mainCommandChain = [
        'hydrate' => [
            'hidrate',
            'select',
            'from',
            'join',
            'where',
            'groupby',
            'orderby',
            'having',
            'limit',
        ],

        'select' => [
            'select',
            'from',
            'join',
            'where',
            'groupby',
            'orderby',
            'having',
            'limit',
        ],

        'replace' => [
            'replace',
            'into',
            'values',
            'select',
        ],

        'insert' => [
            'insert',
            'into',
            'values',
            'select',
        ],

        'update' => [
            'update',
            'join',
            'set',
            'where',
            'select',
            'orderby',
        ],

        'delete' => [
            'delete',
            'from',
            'join',
            'where',
            'orderby',
            'limit',
        ],
    ];
    private static $joinCommands = [
        'join' => true,
        'innerJoin' => true,
        'leftJoin' => true,
        'rightJoin' => true,
    ];
    private $start_chain = null;
    private $query_pieces = [];
    private $sql;
    private $selected_entities = [];
    private $hydrator_function = null;
    private $hydrate = false;
    private $entity_list = [];
    private $values = [];
    private $from_table = [];
    private $join_tree = [];

    public function __construct()
    {

    }

    public function getFormattedSql()
    {
        $this->sql = '';
        $this->_compile($this->query_pieces);
        return \SqlFormatter::format($this->sql);
    }

    private function _compile($pieces)
    {
        foreach ($pieces as $piece) {
            if (is_array($piece)) {
                $this->_compile($piece);
                continue;
            }
            $this->sql .= PHP_EOL . $piece;
        }
    }

    public function pushValue($value)
    {
        $this->values[] = $value;
        return $this;
    }

    /**
     * @param DB $db
     * @param $values
     * @return EQBResult
     */
    public function query(array $values = [])
    {
        foreach ($values as $value) {
            $this->values[] = $value;
        }

        /**
         * @var $first_entity EQBEntity
         */
        $first_entity = current($this->entity_list);
        $repository = call_user_func($first_entity->className . "::getRepository");
        /**
         * @var $repository RepositoryAbstract
         */
        $db = DBFactory::getInstanceByHash($repository->getHash());
        /**
         * @var $stm \PDOStatement
         */
        $stm = $db->query($this->getSQL(), $this->values);

        $result = new EQBResult($stm, $this->selected_entities);
        if ($this->hydrator_function !== null) {
            return call_user_func_array($this->hydrator_function, [$result]);
        }

        if ($this->hydrate) {
            $this->hydrateCompiler();
        }

        return $result;
    }

    public function getSQL()
    {
        $this->sql = "";
        $this->_compile($this->query_pieces);
        return $this->sql;
    }

    public function __call($name, $arguments): EQB
    {

        $name = strtolower($name);

        if (!isset(self::$commandList[$name])) {
            throw new EQBException("The command {$name} is not found. Is it an SQL valid statement?");
        }

        $name = "_{$name}";
        if (!method_exists($this, $name)) {
            throw new EQBException("The method $name is not yet developed");
        }

        $this->$name($arguments);

        return $this;
    }

    public function hydrator(callable $function)
    {
        $this->hydrator_function = $function;
        return $this;
    }

    private function _hydrate($arguments)
    {
        $this->hydrate = true;
        $this->_select($arguments);
    }

    private function _select($arguments)
    {
        $this->arguments = $arguments;

        $this->checkArguments($arguments, [EQBColumn::class, EQBEntity::class, EQBFunction::class]);
        $this->selected_entities = $arguments;
        $this->addEntitiesToList($arguments);

        $this->start_chain = 'select';
        $this->order[] = 'select';

        $this->query_pieces['select'] = "SELECT ";

        $columns = [];

        foreach ($arguments as $argument) {

            if ($argument instanceof EQBEntity) {
                /**
                 * @var $argument EQBEntity
                 */
                foreach ($argument->columns as $name => $property) {
                    $columns[] = $argument->$name->compile() . " as " . $argument->getAlias($name);
                }

            } else if ($argument instanceof EQBColumn) {

                $alias = $argument->getAlias();
                if ($alias === null) {
                    throw new \Exception("Simple columns or function, in select must have an alias");
                }
                $columns[] = $argument->compile() . " as " . $alias;
            } else if ($argument instanceof EQBFunction) {
                $alias = $argument->getAlias();
                if ($alias === null) {
                    throw new \Exception("Simple columns or function, in select must have an alias");
                }
                $columns[] = $argument->compile() . " as " . $alias;
            }
        }


        $this->query_pieces['select'] .= implode(", ", $columns);

    }

    private function checkArguments($arguments, $valid_arguments_type)
    {
        $index = 1;
        foreach ($arguments as $arg) {
            if (!in_array(get_class($arg), $valid_arguments_type)) {
                throw new EQBException("The argument in select:{$index} position is not valid (" . implode(", ", $valid_arguments_type) . ") but: " . get_class($arg));
            }
            $index++;
        }
    }

    private function addEntitiesToList($arguments)
    {
        foreach ($arguments as $arg) {
            if (is_object($arg) AND get_class($arg) == EQBEntity::class AND !in_array($arg, $this->entity_list)) {
                $this->entity_list[$arg->table] = $arg;

            }
        }
    }

    private function _from($arguments)
    {
        $this->checkChain('from');

        $this->checkArguments($arguments, [EQBEntity::class]);
        $this->addEntitiesToList($arguments);

        $this->order[] = 'from';

        $this->query_pieces['from'] = "FROM ";

        $table = [];
        foreach ($arguments as $argument) {
            /**
             * @var $argument EQBEntity
             */
            $this->from_table[$argument->table] = $argument->className;
            $table[] = $argument->getTable() . " as " . $argument->alias;
            $this->join_tree[$argument->table] = $argument;

            //todo add entitis to Relation inverted?
            $this->addEntitiesToRelationList($argument->getTable());
        }

        $this->query_pieces['from'] .= implode(", ", $table);

    }

    private function checkChain($current)
    {
        $chain = self::$mainCommandChain[$this->start_chain];
        $index = array_search($current, $chain);

        if ($index === false) {
            throw new EQBException("Not valid command: {$current} on $this->start_chain chain");
        }

    }

    private function _innerjoin($arguments)
    {
        $this->_join($arguments, 'INNER JOIN');
    }

    private function _join($arguments, $command = 'JOIN')
    {
        $this->checkChain('join');
        $this->addEntitiesToList($arguments);

        if (!isset($this->query_pieces['join'])) {
            $this->query_pieces['join'] = [];
        }

        if (count($arguments) === 1 AND get_class($arguments[0]) === EQBEntity::class) {
             $arguments = $this->createSimpleRelationEQB($arguments[0]);
        }

       /* if( count($arguments) === 2
            AND get_class($arguments[0]) === EQBEntity::class
            AND get_class($arguments[1]) === EQBEntity::class){
            $arguments = $this->createRelationEQB($arguments[0], $arguments[1]);
        }*/

        $this->query_pieces['join'][] = EQBUtils::text_and_entities($command, $arguments);

    }

    private function _leftjoin($arguments)
    {
        $this->_join($arguments, 'LEFT JOIN');
    }

    private function _rightjoin($arguments)
    {
        $this->_join($arguments, 'RIGHT JOIN');
    }

    private function _where($arguments)
    {
        $this->checkChain('where');
        if(!isset($this->query_pieces['where'])){
            $this->query_pieces['where'] = EQBUtils::text_and_entities('WHERE', $arguments);
        } else {
            $this->query_pieces['where'] .= EQBUtils::text_and_entities(' ', $arguments);
        }
    }

    private function _groupby($arguments)
    {
        $this->checkChain('groupby');

        $this->query_pieces['groupby'] = EQBUtils::text_and_entities('GROUP BY', $arguments, ",");
    }

    private function _orderby($arguments)
    {
        $this->checkChain('orderby');

        $this->query_pieces['orderby'] = EQBUtils::text_and_entities('ORDER BY', $arguments);
    }

    private function _having($arguments)
    {
        $this->checkChain('having');

        $this->query_pieces['having'] = EQBUtils::text_and_entities('HAVING', $arguments);
    }

    private function _limit($arguments)
    {
        $num_arguments = count($arguments);
        if ($num_arguments === 0)
            throw new EQBException("Limit must have min. 1 argument");
        if ($num_arguments === 1) {
            $this->query_pieces['limit'] = "LIMIT {$arguments[0]}";
        } else if ($num_arguments === 2) {
            $this->query_pieces['limit'] = "LIMIT {$arguments[0]}, {$arguments[1]}";
        } else {
            throw new EQBException("Limit must have max. 2 argument");
        }
    }

    private function createSimpleRelationEQB($arg){
        if( count($this->from_table) > 1 ){
            throw new EQBException("Can't use short join operator if you have more than 1 table in the FROM command");
        }

        $this->join_tree[$arg->table] = $arg;

        foreach($this->join_tree as $table => $eqb_entity){
            $repository = call_user_func($eqb_entity->className."::getRepository");
            $relation = $repository->getRelation($arg->table);

            if($relation){
                return [
                    $arg,
                    "ON",
                    $eqb_entity->{$relation["from_column"]},
                    "=",
                    $arg->{$relation["to_column"]}
                ];
            }
        }

        //NO DIRECT RELATION MAYBe the joined entity is related to an already joined
        //first win
        $repository = call_user_func($arg->className."::getRepository");
        $relations = $repository->getAllRelations();
        foreach($relations as $cicled_relation => $info_relation){
            if(isset($this->join_tree[$cicled_relation])){
                return [
                    $arg, "ON", $this->join_tree[$cicled_relation]->{$info_relation["to_column"]}, "=", $arg->{$info_relation["from_column"]}
                ];
            }
        }

        throw new \Exception("unable to find relation for this table : {$arg->table}");

    }

    private function addEntitiesToRelationList($argument){
        /*
         if(isset($this->join_tree[$argument->getTable()])) {
                return;
            }
            $repository = call_user_func($argument->className."::getRepository");

                $this->join_tree[$argument->getTable()] =
        */
    }

    /*public function createRelationEQB($a, $b){
        $repository = call_user_func($a->className."::getRepository");

        /**
         * @var $repository RepositoryAbstract
         * /
        $relation = $repository->getRelation($b->table);
        if($relation === false){
            $repository = call_user_func($b->className."::getRepository");

            /**
             * @var $repository RepositoryAbstract
             * /
            $relation = $repository->getRelation($a->table);
        }

        if($relation === false){
            throw new EQBException("can't find relation from ".get_class($a)." and ".get_class($b));
        }

        dump($relation, $this->entity_list);
        exit;
    }
*/

}

/*
$q = new EQB();

$e1 = new EQBEntity(Entity1::class);
$e2 = new EQBEntity(Entity2::class);

$q->select($e1, $e2)
    ->from($e1)
    ->join($e2)->on($e1->prop_id, "=", $e2->prop_id)
    ->where($e1->id, "IN", [1, 2, 3])
    ->groupBy($e2->altro);

*/
