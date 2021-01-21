<?php

namespace Marquee\Data;

use Closure;
use Generator;
use Marquee\Traits\Serializable;

final class QueryResult
{
    private Generator $generator;
    private Query     $query;
    use Serializable;

    public function __construct(Generator $generator, Query $query)
    {
        $this->generator = $generator;
        $this->query     = $query;
    }

    public function getQuery(): Query
    {
        return $this->query;
    }

    public function toArray(): array
    {
        $data = [];

        foreach ($this->generator as $item) {
            if (!is_array($item)) {
                $data[] = json_decode($item);
            } else {
                $data[] = $item;
            }
        }

        return $data;
    }

    /**
     * @param string ...$properties
     * @return array|Entity|mixed|null
     */
    public function next(string ...$properties)
    {
        if ($this->generator->valid()) {
            $value = $this->generator->current();
            $this->generator->next();

            if ($properties) {
                if (count($properties) > 1) {
                    $returnValue = [];

                    foreach ($properties as $property) {
                        $returnValue[ $property ] = $value->$property;
                    }

                    return $returnValue;
                }

                return $value->{$properties[ 0 ]};
            }

            return $value;
        }

        return null;
    }

    public function each(Closure $callback): void
    {
        while ($record = $this->next()) {
            $callback($record);
        }
    }

    public function map(Closure $callback): QueryResult
    {
        $callback = function () use ($callback) {
            while ($record = $this->next()) {
                yield $callback($record);
            }
        };

        return new QueryResult($callback(), $this->query);
    }

    public static function OK(Query $query): self
    {
        return new QueryResult((fn() => yield true)(), $query);
    }

    public function count(): int
    {
        $count = 0;

        while ($this->next() !== null) {
            $count++;
        }

        return $count;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public function __get(string $name)
    {
        $record = $this->next();

        if (!$record) {
            return null;
        }

        return $record->$name;
    }
}