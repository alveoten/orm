<?php

namespace Tabusoft\ORM\EQB;

use Tabusoft\ORM\Repository\RepositoryAbstract;

class EQBResult implements \Iterator
{

    /**
     * @var \PDOStatement
     */
    private $stm;

    private $cursor = 0;

    private $entities = [];


    public function __construct(\PDOStatement $stm, $entities)
    {
        $this->stm = $stm;
        $this->num_row = $this->stm->rowCount();
        $this->entities = $entities;
    }
    
    private function compileEntities($row){

        $objects = [];
        foreach ($this->entities as $entity){
            if($entity instanceof EQBEntity) {
                try {
                    $obj = new $entity->className();
                    foreach ($row as $k => $value) {
                        if (strpos($k, $entity->alias) === 0) {
                            $property = str_replace($entity->alias, "", $k);
                            $setter = RepositoryAbstract::getSetter($property);
                            $obj->{$setter}($value);
                        }
                    }
                }
                catch(\Exception $e){
                    $obj = null;
                }
                $objects[$entity->alias] = $obj;

            }
            else if( ($entity instanceof EQBColumn)
                    OR ($entity instanceof EQBFunction)){
                $objects[$entity->getAlias()] = $row[$entity->getAlias()];
            }
        }

        return $objects;
    }

    public function numRows()
    {
        return $this->stm->rowCount();
    }

    public function current()
    {
        return $this->compileEntities($this->stm->fetch(null,null,$this->cursor));
    }

    public function key()
    {
        return $this->cursor;
    }

    public function next()
    {
        $this->cursor++;
    }

    public function rewind()
    {
        $this->cursor = 0;
    }

    public function valid()
    {
        if($this->cursor >= 0 AND $this->cursor < $this->num_row ){
            return true;
        }

        return false;
    }
}
