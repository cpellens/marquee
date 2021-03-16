<?php

namespace Marquee\Interfaces;

use JsonSerializable;
use Serializable;

interface ISerializable extends Serializable, JsonSerializable
{
    public function __serialize(): string;

    public function __unserialize(string $data): self;
}