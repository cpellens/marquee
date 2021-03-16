<?php

namespace Marquee\Interfaces;

interface ICondition
{
    public function getColumn(): string;

    public function getTargetValue();

    public function getOperator(): string;

    public function getHash(): string;
}