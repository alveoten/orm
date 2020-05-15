<?php

namespace Tabusoft\ORM\Repository\Filter;

use Tabusoft\ORM\Repository\Filter\Operators\AbstractOperator;

class Filter
{
    private string $colum;
    private AbstractOperator $operator;
    private $value;

    /**
     * Filter constructor.
     *
     * @param string $column
     * @param AbstractOperator $operator
     * @param mixed|null $value
     */
    public function __construct(string $column, AbstractOperator $operator, $value = null)
    {
        $this->colum = $column;
        $this->operator = $operator;
        $this->value = $value;
    }

    /**
     * Compile to valid mysql CONDITION
     *
     * @return string
     */
    public function compile(): string
    {
        $value = $this->operator->prepeareValue($this->value);
        return "{$this->colum} " . $this->operator->getOperator() . " {$value}";
    }

    /**
     * @return mixed|mixed|null
     */
    public function getValue()
    {
        return $this->value;
    }

}
