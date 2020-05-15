<?php


namespace Tabusoft\ORM\Repository\Filter\Operators;


class In extends AbstractOperator
{
    public function __construct()
    {
        parent::__construct("IN");
    }

    /**
     * @param $value
     * @return string
     */
    public function prepeareValue($value): string
    {
        if (!is_array($value)) {
            throw new \Exception("invalid typeof value in IN operator, must be an array");
        }
        return "(" . implode(",", array_fill(0, count($value), '?')) . ")";
    }

}
