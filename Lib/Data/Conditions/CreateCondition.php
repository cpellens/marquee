<?php

namespace Marquee\Data\Conditions;

use Marquee\Interfaces\ICondition;

class CreateCondition implements ICondition
{
    private string $column;
    private        $value;

    public function __construct(string $column, $value)
    {
        $this->column = $column;
        $this->value  = $value;
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getTargetValue()
    {
        return $this->value;
    }

    public function getOperator(): string
    {
        return '=';
    }
}