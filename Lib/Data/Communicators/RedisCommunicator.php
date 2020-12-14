<?php

namespace Marquee\Data\Communicators;

use Generator;
use Marquee\Core\Connection\RedisConnection;
use Marquee\Core\String\Util;
use Marquee\Data\Conditions\CreateCondition;
use Marquee\Data\Conditions\UpdateCondition;
use Marquee\Data\Conditions\WhereCondition;
use Marquee\Data\Entity;
use Marquee\Data\Query;
use Marquee\Data\QueryResult;
use Marquee\Exception\Exception;
use Marquee\Interfaces\ICommunicator;

class RedisCommunicator implements ICommunicator
{
    private RedisConnection $connection;

    public function __construct(RedisConnection $connection)
    {
        $this->connection = $connection;
    }

    public function getConnection(): RedisConnection
    {
        return $this->connection;
    }

    public function getKey(...$params): string
    {
        return Util::ToSnakeCase(implode('_', $params));
    }

    public function execute(Query $query, int $flags): QueryResult
    {
        $conditions = $query->getConditions(WhereCondition::class);

        if (($flags & Query::METHOD_SELECT) === Query::METHOD_SELECT) {
            /*
             * Select
             */

            if ($conditions) {
                return new QueryResult($this->getFiltered($query), $query);
            } else {
                return new QueryResult($this->getAll($query), $query);
            }
        } elseif (($flags & Query::METHOD_UPDATE) === Query::METHOD_UPDATE) {
            /*
             * Update
             */

            $records = new QueryResult($conditions ? $this->getFiltered($query) : $this->getAll($query), $query);
            $count   = 0;
            $updates = $query->getConditions(UpdateCondition::class);

            while ($record = $records->next()) {
                $key = $this->getKey($query->getTable()->getName(), $record->getId());

                foreach ($updates as $condition) {
                    if ($condition instanceof UpdateCondition) {
                        $this->getConnection()->getRedisDriver()->hSet($key, $condition->getColumn(),
                                                                       $condition->getTargetValue());
                    }
                }

                $count++;
            }

            return new QueryResult((function () use ($count) { yield $count; })(), $query);
        } elseif (($flags & Query::METHOD_INSERT) === Query::METHOD_INSERT) {
            /*
             * Create
             */
            // Get Max ID
            $redis = $this->getConnection()->getRedisDriver();
            $ids   = $redis->sMembers($smkey = $this->getKey($query->getTable()->getName(), 'indices'));
            $newId = $ids ? (max($ids) + 1) : 1;
            $class = $query->getClass();

            $conditions = array_merge($query->getConditions(CreateCondition::class), [
                new CreateCondition(class_exists($class) && is_subclass_of($class, Entity::class)
                                        ? $class::GetPrimaryKeyName() : 'id', $newId),
            ]);

            $data = [];

            foreach ($conditions as $cond) {
                $data[ $cond->getColumn() ] = $cond->getTargetValue();
            }

            $redis->sAdd($smkey, $newId);
            $redis->hMSet($this->getKey($query->getTable()->getName(), $newId), $data);

            return new QueryResult((function () use ($data, $class) {
                yield new $class($data, $this->connection);
            })(), $query);
        } elseif (($flags & Query::METHOD_DELETE) === Query::METHOD_DELETE) {
            $results = $this->getFiltered($query);

            foreach ($results as $row) {
                $id = $row->getId();

                if ($table = $query->getTable()) {
                    $key = $this->getKey($table->getName(), $id);
                    $this->getConnection()->getRedisDriver()->del($key);
                    $this->connection->getRedisDriver()->sRem($this->getKey($table->getName(), 'indices'), $id);
                }
            }

            return new QueryResult($results, $query);
        } elseif ($flags === Query::METHOD_TRUNCATE) {
            $keys   = $this->getConnection()->getRedisDriver()->keys($this->getKey($tableName = $query->getTable()->getName(),
                                                                                   '*'));
            $keys[] = $this->getKey($tableName, 'indices');

            call_user_func_array([ $this->getConnection()->getRedisDriver(), 'del' ], $keys);

            return QueryResult::OK($query);
        }

        throw new Exception('Invalid Query');
    }

    private function getAll(Query $query): Generator
    {
        $tableName = $query->getTable()->getName();
        $redis     = $this->connection->getRedisDriver();
        $records   = $redis->sMembers($key = $this->getKey($tableName, 'indices'));

        $class = $query->getClass();
        $count = 0;
        $limit = $query->getLimit();

        foreach ($records as $record) {
            $count++;

            if ($limit && ($count > $limit)) {
                return;
            }

            if ($class && is_subclass_of($class, Entity::class)) {
                $data = $redis->hGetAll($key = $this->getKey($query->getTable()->getName(), $record));
                yield new $class($data, $this->connection);
            } else {
                if (class_exists($class)) {
                    yield new $class($record);
                } else {
                    yield $redis->hGetAll($this->getKey($query->getTable()->getName(), $record));
                }
            }
        }
    }

    private function getFiltered(Query $query): Generator
    {
        $conditions = $query->getConditions(WhereCondition::class);

        $redis = $this->getConnection()->getRedisDriver();
        $keys  = $redis->keys($this->getKey($query->getTable()->getName(), '*'));
        $class = $query->getClass();

        $count = 0;
        $limit = $query->getLimit();

        foreach ($keys as $key) {
            if ($limit && ($count >= $limit)) {
                return;
            }

            $record = $redis->hGetAll($key);

            if (!$record) {
                continue;
            }

            $pass = true;

            foreach ($conditions as $condition) {
                $column      = $condition->getColumn();
                $operator    = $condition->getOperator();
                $targetValue = $condition->getTargetValue();
                $success     = false;

                if (!isset($record[ $column ])) {
                    $pass = false;
                    break;
                }

                switch ($operator) {
                    case '>':
                        $success = ($record[ $column ] > $targetValue);
                        break;
                    case '=':
                        $success = ($record[ $column ] == $targetValue);
                        break;
                    case '<':
                        $success = ($record[ $column ] < $targetValue);
                        break;
                }

                if (!$success) {
                    $pass = false;
                    break;
                }
            }

            if ($pass) {
                $count++;
                if ($class && class_exists($class)) {
                    yield new $class($record, $this->connection);
                } else {
                    yield $record;
                }
            }
        }
    }
}