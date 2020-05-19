<?php

namespace Tabusoft\ORM\Repository\Filter\Operators;

class LessOrEqual extends AbstractOperator
{
    public function __construct()
    {
        parent::__construct("<=");
    }

    /**
     * @param $value
     * @return string
     */
    public function prepeareValue($value): string
    {
        return '?';
    }
}
