<?php

namespace Marquee\Data;

use Marquee\Interfaces\ICommunicator;
use Marquee\Interfaces\IConnection;
use Marquee\Schema\Property;
use Marquee\Traits\Serializable;

class Table
{
    protected string        $tableName;
    protected ICommunicator $communicator;
    protected array         $columns;
    protected array         $primaryKeys;
    use Serializable;

    public function __construct(IConnection $connection, string $tableName)
    {
        $this->tableName    = $tableName;
        $this->communicator = $connection->getCommunicator();
        $this->columns      = [];
        $this->primaryKeys  = [];
    }

    public function getName(): string
    {
        return $this->tableName;
    }

    public function exists(): bool
    {
        $tables = $this->communicator->getTables();

        foreach ($tables as $table) {
            if ($table->getName() === $this->getName()) {
                return true;
            }
        }

        return false;
    }

    public function addColumnFromProperty(Property &$property): void
    {
        $this->columns[] = $property;

        if ($property->isPrimary()) {
            $this->primaryKeys[] = $property;
        }
    }

    public function create(): void
    {
        $this->communicator->getConnection()->getDriver()->exec($this->getCreateSql());
    }

    public function getCreateSql(): string
    {
        $lines = [
            sprintf('create table if not exists `%s`', $this->getName()),
        ];

        $lines[] = '(';

        /** @var Property $column */
        foreach ($this->columns as $column) {
            $lines[] = $column->getCreateSql() . ',';
        }

        $pKeys = [];

        /** @var Property $primaryKey */
        foreach ($this->primaryKeys as $primaryKey) {
            $pKeys[] = sprintf('primary key(`%s`)', $primaryKey->getName());
        }

        $lines[] = implode(',', $pKeys);

        $lines[] = ');';

        return implode(PHP_EOL, $lines);
    }

    public function drop(): void
    {
        $this->communicator->getConnection()->getDriver()->exec(sprintf('drop table `%s`', $this->getName()));
    }
}