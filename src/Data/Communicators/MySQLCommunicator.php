<?php

namespace Marquee\Data\Communicators;

use Generator;
use Marquee\Core\Connection\MySQLConnection;
use Marquee\Data\Conditions\CreateCondition;
use Marquee\Data\Conditions\UpdateCondition;
use Marquee\Data\Conditions\WhereCondition;
use Marquee\Data\Entity;
use Marquee\Data\Query;
use Marquee\Data\QueryResult;
use Marquee\Data\Table;
use Marquee\Exception\Exception;
use Marquee\Interfaces\ICommunicator;
use Marquee\Interfaces\IConnection;
use PDOException;

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
        if (!$this->connection->connected()) {
            $this->connection->connect();
        }

        return new QueryResult($this->read($query), $query);
    }

    public function getConnection(): IConnection
    {
        return $this->connection;
    }

    public function getTables(): Generator
    {
        $pdo = $this->connection->getDriver();
        if ($result = $pdo->query('show tables')) {
            $rows = $result->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($rows as $row) {
                yield new Table($this->connection, $row);
            }
        }
    }

    private function read(Query $query): Generator
    {
        /** @var MySQLConnection $connection */
        $connection = $query->getConnection();
        $pdo        = $connection->getDriver();

        /** @var Entity $class */
        $class       = $query->getClass();
        $queryString = $this->getQueryString($query);

        $statement = $pdo->prepare($queryString);
        $flags     = $query->getFlags();

        try {
            if ($statement->execute($this->variables)) {
                switch ($flags) {
                    case Query::METHOD_INSERT:
                        yield $class::GetRepository($this->connection)->single($pdo->lastInsertId());
                        break;

                    case Query::METHOD_SELECT:
                        while ($record = $statement->fetch()) {
                            yield new $class($record, $connection);
                        }
                        break;

                    case Query::METHOD_UPDATE:
                        $get             = new Query($query->getConnection(), $class);
                        $whereConditions = $query->getConditions(WhereCondition::class);

                        foreach ($whereConditions as $whereCondition) {
                            $get->where($whereCondition->getColumn(), $whereCondition->getOperator(),
                                        $whereCondition->getTargetValue());
                        }

                        $result = $query->get();

                        while ($row = $result->next()) {
                            yield $row;
                        }

                        break;

                    default:
                        break;
                }

                return;
            }
        } catch (PDOException $e) {
            yield null;
        }

        [ $sqlCode, $code, $message ] = ($statement->errorInfo());
        throw new Exception($message);
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
            case Query::METHOD_UPDATE:
                $queryString = 'update `%s` set %s';

                $updateConditions = $query->getConditions(UpdateCondition::class);
                $whereConditions  = $query->getConditions(WhereCondition::class);

                $updateStatements = [];

                /** @var UpdateCondition $updateCondition */
                foreach ($updateConditions as $updateCondition) {
                    $column = $updateCondition->getColumn();
                    $value  = $updateCondition->getTargetValue();

                    $updateStatements[] = sprintf('`%s` = ?', $column);
                    $this->variables[]  = $value;
                }

                $queryString = sprintf($queryString, $query->getTable()->getName(), implode(',', $updateStatements));

                if ($whereConditions) {
                    $queryString .= ' where ' . implode(' and ', array_map(function (WhereCondition $condition) {
                            $this->variables[] = $condition->getTargetValue();

                            return sprintf('`%s` = ?', $condition->getColumn());
                        }, $whereConditions));
                }

                return $queryString;

            case Query::METHOD_TRUNCATE:
                return sprintf('truncate `%s`', $query->getTable()->getName());
            default:
                throw new Exception('Invalid Query');
        }
    }

    private function wrap(string $str, string $quoteMarker = "'"): string
    {
        return sprintf('%s%s%s', $quoteMarker, $str, $quoteMarker);
    }
}