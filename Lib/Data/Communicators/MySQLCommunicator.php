<?php

namespace Marquee\Data\Communicators;

use Generator;
use Marquee\Core\Connection\MySQLConnection;
use Marquee\Data\Conditions\CreateCondition;
use Marquee\Data\Conditions\WhereCondition;
use Marquee\Data\Query;
use Marquee\Data\QueryResult;
use Marquee\Exception\Exception;
use Marquee\Interfaces\ICommunicator;
use Marquee\Interfaces\IConnection;

class MySQLCommunicator implements ICommunicator
{
    protected MySQLConnection $connection;
    private array             $variables = [];

    public function __construct(MySQLConnection $connection)
    {
        $this->connection = $connection;
    }

    public function execute(Query $query, int $flags): QueryResult
    {
        return new QueryResult($this->read($query), $query);
    }

    public function getConnection(): IConnection
    {
        return $this->connection;
    }

    private function read(Query $query): Generator
    {
        /** @var MySQLConnection $connection */
        $connection = $query->getConnection();
        $pdo        = $connection->getDriver();

        $class       = $query->getClass();
        $queryString = $this->getQueryString($query);
        $statement   = $pdo->prepare($queryString);

        $flags = $query->getFlags();

        if ($statement->execute($this->variables)) {
            if ($flags === Query::METHOD_INSERT) {
                yield $class::GetRepository($this->connection)->single($pdo->lastInsertId());
            } else {
                while ($record = $statement->fetch()) {
                    yield new $class($record, $connection);
                }
            }

            return;
        } else {
            [ $sqlCode, $code, $message ] = ($statement->errorInfo());
            throw new Exception($message);
        }

        throw new Exception('A query exception has occurred');
    }

    private function createSqlString(Query $query, string ...$params): string
    {
        [ $preStatement ] = $params;

        $table = $query->getTable();

        return sprintf('%s `%s`', $preStatement, $table->getName());
    }

    private function getQueryString(Query $query): string
    {
        $queryType = $query->getFlags();

        switch ($queryType) {
            case Query::METHOD_INSERT:
                $queryString     = $this->createSqlString($query, 'insert into');
                $fields          = $query->getConditions(CreateCondition::class);
                $columns         = array_map(fn(CreateCondition $cond) => $cond->getColumn(), $fields);
                $this->variables = array_map(fn(CreateCondition $cond) => $cond->getTargetValue(), $fields);

                $queryString .= sprintf('(`%s`) values (%s)', implode('`,`', $columns),
                                        implode(',', array_fill(0, count($columns), '?')));

                return $queryString;
            case Query::METHOD_SELECT:
                $whereConditions = $query->getConditions(WhereCondition::class);
                $queryString     = $this->createSqlString($query, 'select * from');

                $whereConditions = array_map(function (WhereCondition $condition) {
                    $this->variables[] = $condition->getTargetValue();

                    return implode(' ', [
                        $this->wrap($condition->getColumn(), '`'),
                        $condition->getOperator(),
                        '?',
                    ]);
                }, $whereConditions);

                if (count($whereConditions)) {
                    $queryString .= ' where ' . implode(' and ', $whereConditions);
                }

                $limit = $query->getLimit();
                if ($limit) {
                    $queryString .= sprintf(' limit %d', $limit);
                }

                return $queryString;
            default:
                throw new Exception('Invalid Query');
        }
    }

    private function wrap(string $str, string $quoteMarker = "'"): string
    {
        return sprintf('%s%s%s', $quoteMarker, $str, $quoteMarker);
    }
}