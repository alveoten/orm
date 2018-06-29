<?php

namespace Tabusoft\ORM\EQB;


class EQBUtils{
    
    public static function text_and_entities($command, $arguments, $glue = " "){
        $index = 0;
        $elems = [];
        foreach ($arguments as $arg) {
            $index++;

            if ( $arg instanceof EQBEntity ) {
                $elems[] = $arg->getTable()." as ".$arg->alias;
                continue;
            }
            else if ( ($arg instanceof EQBColumn)
                    OR ( $arg instanceof EQBFunction)){
                $elems[] = $arg->compile();   
                continue;
            }
            else if( is_scalar($arg) ){
                $elems[] = $arg;
                continue;
            }

            throw new EQBException("Not valid argument in {$command} clause index: $index");
        }

        return "{$command} " . implode($glue, $elems);
    }

    
}