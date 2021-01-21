<?php

namespace Marquee\Core;

use Marquee\Exception\Exception;
use Marquee\Interfaces\IConnection;
use PDO;
use PDOException;

abstract class DatabaseConnection implements IConnection
{
    protected PDO    $connection;
    protected string $dbname;
    protected string $dsn;

    public function __construct(string $dsn) {
        $this->dsn = $dsn;
    }

    final public function connect(): void
    {
        if (!isset($this->dbname)) {
            throw new Exception('Db name not set');
        }

        try {
            [ $username, $password ] = static::GetCredentialsFromDsn($this->dsn);
            $dsn = $this->getDsn();

            unset($this->dsn, $this->dbname);

            $this->connection = new PDO($dsn, $username, $password);
        } catch (PDOException $e) {
            throw new Exception($e->getMessage());
        }
    }

    final public function getDriver(): PDO
    {
        return $this->connection;
    }

    final public function disconnect(): void
    {
        unset($this->connection);
    }

    final public function __destruct()
    {
        $this->disconnect();
    }

    final public function selectDb(string $dbname): void
    {
        $this->dbname = $dbname;
    }

    private static function GetCredentialsFromDsn(string $dsn): array
    {
        $parts = explode(';', $dsn);

        if (!$parts) {
            throw new Exception('No credentials found in DSN [%s]', $dsn);
        }

        static $keys = [ 'username', 'password' ];
        $credentials = [];

        foreach ($parts as $part) {
            [ $which, $value ] = explode('=', $part);

            if (!in_array($which, $keys)) {
                continue;
            }

            $credentials[] = $value;
        }

        return array_reverse($credentials);
    }

    private function getDsn(): string
    {
        static $exclude = [ 'username', 'password' ];

        $parts        = [];
        $currentParts = explode(';', $this->dsn . sprintf(';dbname=%s', $this->dbname));

        foreach ($currentParts as $part) {
            [ $key, $value ] = explode('=', $part);

            if (in_array($key, $exclude)) {
                continue;
            }

            $parts[] = $part;
        }

        return implode(';', $parts);
    }
}