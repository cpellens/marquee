<?php

namespace Marquee\Data\Conditions;

use Marquee\Interfaces\ICondition;
use Marquee\Traits\Serializable;

class WhereCondition implements ICondition
{
    use Serializable;

    private string $column, $operator;
    private mixed           $targetValue;

    public function __construct(string $column, string $operator = '=', $value = null)
    {
        $this->column      = $column;
        $this->operator    = $operator;
        $this->targetValue = $value;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function getTargetValue()
    {
        return $this->targetValue;
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getHash(): string
    {
        return sprintf('(%s[%s]%s)', $this->column, $this->operator, $this->targetValue);
    }
}