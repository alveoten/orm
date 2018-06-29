<?php

namespace Tabusoft\ORM\EQB;

use Tabusoft\ORM\Entity\EntityAbstract;
use Tabusoft\ORM\EQB\EQBColumn;

class EQBEntity{

    static $alias_counter = 0;

    public $className;
    public $alias;
    public $table;
    public $columns;
    public $factory = null;
    public $host;
    public $port;

    public function __construct($class){

        //check entity class
        if( !class_exists($class) ){
            throw new EQBException("Can't find {$class}, is it defined?");
        }

        $repository = call_user_func( $class."::getRepository") ;
        if( !is_object($repository) ){
            throw new EQBException("Can't find {$repository}, is it defined?");
        }

        $repository = new $repository();

        $this->table = $repository->getTable();
        $this->columns = $repository->getTableColumnsDescription();
        $this->host = $repository->getHost();
        $this->port = $repository->getPort();

        $this->alias = "e".(self::$alias_counter)."__";

        $this->className = $class;
        self::$alias_counter++;
    }

    public function setFactory( $factory ){
        $this->factory = $factory;
    }

    public function hasFactory(){
        return $this->factory !== null;
    }

    public function getAlias($name){
        return $this->alias . $name;
    }

    public function getTable(){
        return $this->table;
    }

    public function __get($name)
    {
        if(!isset($this->columns[$name]))
            throw new EQBException("Can't find column: {$name} int this entity: {$this->className}");

        return new EQBColumn($name,$this);
    }
}