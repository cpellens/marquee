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

    public function toArray(): array
    {
        $data = [];

        foreach ($this->generator as $item) {
            $data[] = $item;
        }

        return $data;
    }

    public function next()
    {
        if ($this->generator->valid()) {
            $value = $this->generator->current();
            $this->generator->next();

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

    public static function OK(Query $query): self
    {
        return new QueryResult((fn() => yield true)(), $query);
    }
}