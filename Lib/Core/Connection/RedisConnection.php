<?php

namespace Marquee\Core\Connection;

use Marquee\Core\String\Regex;
use Marquee\Data\Communicators\RedisCommunicator;
use Marquee\Data\Query;
use Marquee\Exception\Exception;
use Marquee\Interfaces\ICommunicator;
use Marquee\Interfaces\IConnection;
use Redis;

/**
 * Class RedisConnection
 * DSN: host:port/password
 */
class RedisConnection implements IConnection
{
    const DSN_PATTERN = '([0-9.]{7,15})(?:\:([0-9]{2,5}))?(?:\/(.+))?';
    protected Redis $redis;
    private string  $dsn;

    public function __construct(string $dsn)
    {
        $this->redis = new Redis();
        $this->dsn   = $dsn;
    }

    public function getDriver(): Redis
    {
        return $this->redis;
    }

    public function connect(): void
    {
        [ $host, $port, $password ] = $this->getCredentials();

        try {
            $this->redis->connect($host, intval($port));
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }

        if ($password) {
            $this->redis->auth($password);
        }
    }

    public function disconnect(): void
    {
        $this->redis->close();
        unset($this->redis);
    }

    public static function CreateDsn(string $host, int $port = null, ?string $password = null, ?string $user = null): string
    {
        if ($port && $password) {
            return sprintf('%s:%d/%s', $host, $port, $password);
        } elseif ($port) {
            return sprintf('%s:%d', $host, $port);
        } elseif ($password) {
            return sprintf('%s/%s', $host, $password);
        }

        return $host;
    }

    public function query(string $className): Query
    {
        return new Query($this, $className);
    }

    public function getCommunicator(): ICommunicator
    {
        return new RedisCommunicator($this);
    }

    public function connected(): bool
    {
        return isset($this->redis) && ($this->redis instanceof RedisConnection);
    }

    public function tryConnect(?Exception &$exception = null): bool
    {
        try {
            $this->connect();

            return true;
        } catch (Exception $e) {
            $exception = $e;

            return false;
        }
    }

    private function getCredentials(): array
    {
        $regex       = new Regex(static::DSN_PATTERN, Regex::IGNORE_CASE);
        $matches     = $regex->matches($this->dsn);
        $credentials = [];

        foreach ($matches as $i => $match) {
            $credentials[] = $match;
        }

        return array_pad($credentials, 3, null);
    }
}