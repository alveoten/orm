<?php

namespace Tabusoft\ORM\EQB;

class EQBFunction{
    private $name;
    private $arguments;
    private $alias;
    private $separator;


    public static function __callStatic($name, $arguments)
    {

        if(method_exists(self::class, $name)){
            self::$name($arguments);
        }

        return new EQBFunction($name, $arguments, "");
    }

    public function as($as){
        $this->alias = $as;
        return $this;
    }

    public function getAlias(){
        return $this->alias;
    }

    public function compile()
    {
        return "{$this->name}( ".trim(EQBUtils::text_and_entities($this->separator,$this->arguments))." )";
    }

    public function __construct($name, $arguments, $separator)
    {
        $this->name = $name;
        $this->arguments = $arguments;
        $this->separator = $separator;
    }

    public static function BETWEEN(){
        $arguments = func_get_args();

        if(count($arguments) !== 3){
            throw new EQBException("You must use 3 arguments: 'date' AND 'date' or ?t AND ?t");
        }

        return new EQBFunction("BETWEEN", $arguments, " ");
    }

}