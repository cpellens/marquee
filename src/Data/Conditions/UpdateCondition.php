<?php

namespace Marquee\Data\Conditions;

use Marquee\Interfaces\ICondition;
use Marquee\Traits\Serializable;

class UpdateCondition implements ICondition
{
    use Serializable;

    private string $column, $operator;
    private                 $targetValue;

    public function __construct(string $column, $value = null)
    {
        $this->column      = $column;
        $this->operator    = '=';
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
}