<?php

namespace Tabusoft\ORM\EQB;


use Tabusoft\ORM\EQB\EQBEntity;

class EQBColumn{
    private $entity;
    private $column;

    private $as = null;

    public function __construct(string $column, EQBEntity $entity)
    {
        $this->column = $column;
        $this->entity = $entity;
    }

    public function getAlias(){
        return $this->as;
    }

    public function as($as){
        $this->as = $as;
        return $this;
    }

    public function compile(){
        return trim($this->entity->alias.".".$this->column);
    }

    public function compileSelect(){
        return trim($this->compile());
    }

}
