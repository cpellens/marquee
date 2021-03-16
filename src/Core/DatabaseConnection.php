<?php

namespace Marquee\Core;

use Marquee\Exception\Exception;
use Marquee\Interfaces\IConnection;
use PDO;
use PDOException;

abstract class DatabaseConnection implements IConnection
{
    protected PDO       $connection;
    protected string    $dbname;
    protected string    $dsn;
    private array       $options;
    private IConnection $cacheConnection;

    public function __construct(string $dsn)
    {
        $this->dsn     = $dsn;
        $this->options = [];
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

            $this->connection = new PDO($dsn, $username, $password, $this->options);

            unset($this->options);
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

    public function getCacheConnection(): IConnection
    {
        if (isset($this->cacheConnection)) {
            return $this->cacheConnection;
        }

        return $this;
    }

    public final function setCacheConnection(IConnection &$connection)
    {
        if (!$connection->connected()) {
            $connection->connect();
        }

        $this->cacheConnection = &$connection;
    }

    final public function __destruct()
    {
        $this->disconnect();
    }

    final public function selectDb(string $dbname): void
    {
        $this->dbname = $dbname;
    }

    final public function setSslCertificate(string $path): void
    {
        $this->setAttribute(PDO::MYSQL_ATTR_SSL_CA, $path);
    }

    final protected function setAttribute(int $option, mixed $value): void
    {
        $this->options[ $option ] = $value;
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