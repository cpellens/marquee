<?php

namespace Marquee\Data;

use Generator;
use Marquee\Exception\Exception;
use Marquee\Interfaces\ICommunicator;
use Marquee\Interfaces\IConnection;

class Relationship
{
    protected Query $query;
    private Entity  $entity;

    public function __construct(Query $possibleQuery, Entity $entity)
    {
        $this->query  = $possibleQuery;
        $this->entity = $entity;
    }

    public function getEntity(): Entity
    {
        return $this->entity;
    }

    public function getClass(): string
    {
        return $this->query->getClass();
    }

    public function getQuery(): Query
    {
        return $this->query;
    }

    public function getConnection(): IConnection
    {
        return $this->query->getConnection();
    }

    public function getCommunicator(): ICommunicator
    {
        return $this->query->getConnection()->getCommunicator();
    }

    public function one(): Entity
    {
        $records = $this->query->get();

        while ($record = $records->next()) {
            return $record;
        }

        throw new Exception('Query returned 0 results');
    }

    public function all(): Generator
    {
        $records = $this->query->get();

        while ($record = $records->next()) {
            yield $record;
        }
    }

    public function detach(Entity $parent): void
    {
        /** @var Entity $class */
        $class = get_class($parent);
        $pKey  = $class::GetPrimaryKeyName();
        $rows  = $this->all();

        foreach ($rows as $row) {
            $row->$pKey = null;
            $row->save();
        }
    }

    public function __toString(): string
    {
        return sprintf('Relationship(%s -> %s)', get_class($this->entity), $this->query->getTable()->getName());
    }

    public function __invoke(): Query
    {
        return $this->query;
    }
}