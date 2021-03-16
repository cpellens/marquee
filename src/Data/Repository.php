<?php

namespace Marquee\Data;

use Marquee\Exception\Exception;
use Marquee\Interfaces\IConnection;
use Marquee\Traits\Serializable;

class Repository
{
    protected string    $entityClass;
    private IConnection $connection;
    use Serializable;

    public function __construct(IConnection $connection, string $entityClass)
    {
        if (!is_subclass_of($entityClass, Entity::class)) {
            throw new Exception('Class [%s] must be a [%s]', $entityClass, Entity::class);
        }

        $this->entityClass = $entityClass;
        $this->connection  = $connection;
    }

    public function single(int|string $id): ?Entity
    {
        $class = $this->entityClass;
        $query = new Query($this->connection, $class);

        $result = $query->where($class::GetPrimaryKeyName(), '=', $id)->get();

        if ($row = $result->next()) {
            return $row;
        }

        return null;
    }

    public function create(array $data = []): Entity
    {
        $class = $this->entityClass;
        $query = new Query($this->connection, $class);

        $result = $query->create($data);

        while ($row = $result->next()) {
            return $row;
        }

        throw new Exception('Could not create user');
    }

    public function getConnection(): IConnection
    {
        return $this->connection;
    }

    public function getEntityClass(): Entity|string
    {
        return $this->entityClass;
    }

    public function all(): QueryResult
    {
        $class = $this->entityClass;
        $query = new Query($this->connection, $class);

        return $query->get();
    }

    public function update(Entity $entity, array $parameters): QueryResult
    {
        $entityClass = $this->entityClass;

        $connection = $this->getConnection();

        return $connection->query($this->entityClass)->where($entityClass::GetPrimaryKeyName(), '=',
                                                             $entity->getId())->update($parameters)->execute();
    }

    public function query(): Query
    {
        return new Query($this->connection, $this->entityClass);
    }
}