<?php

namespace Marquee\Core\Connection;

use Marquee\Core\DatabaseConnection;
use Marquee\Data\Communicators\MySQLCommunicator;
use Marquee\Data\Query;
use Marquee\Exception\Exception;
use Marquee\Interfaces\ICommunicator;
use PDO;

final class MySQLConnection extends DatabaseConnection
{
    const PATTERN_DSN      = 'mysql:host=%s;port=%s';
    const PATTERN_DSN_FULL = 'mysql:host=%s;port=%s;username=%s;password=%s';

    public function connected(): bool
    {
        return isset($this->connection) && ($this->connection instanceof PDO);
    }

    public function query(string $className): Query
    {
        return new Query($this, $className);
    }

    public function getCommunicator(): ICommunicator
    {
        return new MySQLCommunicator($this);
    }

    public static function CreateDsn(string $host, int $port = 3306, ?string $password = null, ?string $user = null): string
    {
        if ($user && $password) {
            return sprintf(MySQLConnection::PATTERN_DSN_FULL, $host, $port, $password, $user);
        } elseif ($host) {
            return sprintf(MySQLConnection::PATTERN_DSN, $host, $port, $password, $user);
        }
    }

    public function tryConnect(?Exception &$exception = null): bool
    {
        if (!isset($this->dsn)) {
            $exception = new Exception('Cannot re-open closed connection');

            return false;
        }

        try {
            $this->connect();

            return true;
        } catch (Exception $e) {
            $exception = $e;

            return false;
        }
    }
}