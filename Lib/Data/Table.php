<?php

namespace Marquee\Data;

use Marquee\Interfaces\ICommunicator;
use Marquee\Interfaces\IConnection;
use Marquee\Traits\Serializable;

class Table
{
    protected string        $tableName;
    protected ICommunicator $communicator;

    use Serializable;

    public function __construct(IConnection $connection, string $tableName)
    {
        $this->tableName    = $tableName;
        $this->communicator = $connection->getCommunicator();
    }

    public function getName(): string
    {
        return $this->tableName;
    }
}