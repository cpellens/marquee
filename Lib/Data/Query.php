<?php

namespace Marquee\Data;

use Marquee\Core\String\Util;
use Marquee\Data\Conditions\CreateCondition;
use Marquee\Data\Conditions\UpdateCondition;
use Marquee\Data\Conditions\WhereCondition;
use Marquee\Exception\Exception;
use Marquee\Interfaces\IConnection;
use Marquee\Traits\Serializable;
use Symfony\Component\String\Inflector\EnglishInflector;

final class Query
{
    const METHOD_DELETE   = 8;
    const METHOD_INSERT   = 4;
    const METHOD_SELECT   = 1;
    const METHOD_TRUNCATE = 16;
    const METHOD_UPDATE   = 2;
    private IConnection $connection;
    private string      $table, $class;
    private array       $conditions;
    private int         $flags;
    private bool        $oneResult = false;
    private int         $limit;
    use Serializable;

    public function __construct(IConnection $connection, string $className)
    {
        $this->connection = $connection;
        $this->conditions = [];
        $this->flags      = 0;

        if (class_exists($className)) {
            $this->class = $className;
        } else {
            $this->table = $className;
        }
    }

    public function __toString(): string
    {
        return json_encode([
                               'table'      => json_decode($this->getTable()),
                               'conditions' => $this->conditions,
                           ]);
    }

    public function getConnection(): IConnection
    {
        return $this->connection;
    }

    public function get(): QueryResult
    {
        $this->flags |= Query::METHOD_SELECT;

        return $this->execute();
    }

    public function getFlags(): int
    {
        return $this->flags;
    }

    public function __destruct()
    {
        unset($this->connection);
    }

    public function getLimit(): int
    {
        return isset($this->limit) ? $this->limit : 0;
    }

    public function getTable(): Table
    {
        return new Table($this->connection, $this->getTableName());
    }

    /**
     * @param string|null $type
     * @return WhereCondition[]
     */
    public function getConditions(?string $type = null): array
    {
        if ($type === null) {
            return $this->conditions;
        }

        return array_filter($this->conditions, fn($obj) => is_a($obj, $type));
    }

    public function getClass(): ?string
    {
        return $this->class ?? $this->table;
    }

    public function where(string $column, string $operation, $value): self
    {
        $this->conditions[] = new WhereCondition($column, $operation, $value);

        return $this;
    }

    public function update(array $fields, ?Entity $entity = null): Query
    {
        if (!is_subclass_of($this->class, Entity::class)) {
            throw new Exception('Cannot update non-entity object');
        }

        $class = $this->class;

        foreach ($fields as $prop => $value) {
            $this->conditions[] = new UpdateCondition($prop, $value);
        }

        if ($entity) {
            $this->conditions[] = new WhereCondition($class::GetPrimaryKeyName(), '=', $entity->getId());
        }

        $this->flags |= Query::METHOD_UPDATE;

        return $this;
    }

    public function create(array $parameters): QueryResult
    {
        $this->flags = Query::METHOD_INSERT;

        foreach ($parameters as $key => $value) {
            $this->conditions[] = new CreateCondition($key, $value);
        }

        return $this->execute();
    }

    public function execute(): QueryResult
    {
        return $this->connection->getCommunicator()->execute($this, $this->flags);
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function delete(): self
    {
        $this->flags = Query::METHOD_DELETE;

        return $this;
    }

    public function truncate(): QueryResult
    {
        $this->flags = Query::METHOD_TRUNCATE;

        return $this->execute();
    }

    private function getTableName(): string
    {
        if (isset($this->table)) {
            return $this->table;
        }

        /**
         * Is a class. Is it an entity? If so, we can call its GetTable static method.
         * Otherwise, generate a table name for it
         */
        if (is_subclass_of($this->class, Entity::class)) {
            $class = $this->class;

            return $class::GetTableName();
        }

        if (!class_exists($this->class)) {
            throw new Exception('Invalid Class [%s]', $this->class);
        }

        return $this->generateTableName();
    }

    private function generateTableName(): string
    {
        [ $className ] = array_reverse(explode('\\', $this->class));
        [ $toPlural ] = array_reverse(explode(' ', $words = Util::PascalToWords($className)));

        $inflector = new EnglishInflector();
        [ $pluralized ] = $inflector->pluralize($inflector->singularize($toPlural)[ 0 ]);

        return Util::ToSnakeCase(str_replace($toPlural, $pluralized, $words));
    }
}