<?php

namespace Tabusoft\ORM\Repository\Filter\Operators;

abstract class AbstractOperator
{
    private string $operator;

    public function __construct(string $operator)
    {
        $this->operator = $operator;
    }

    /**
     * @return string
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * Transform a string into a valid mysql value string
     *
     * @param $value
     * @return string
     */
    abstract public function prepeareValue($value): string;
}
