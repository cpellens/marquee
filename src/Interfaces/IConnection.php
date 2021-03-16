<?php

namespace Marquee\Interfaces;

use Marquee\Data\Query;
use Marquee\Exception\Exception;

interface IConnection
{
    public function __construct(string $dsn);

    public function connect(): void;

    public function tryConnect(?Exception &$exception = null): bool;

    public function connected(): bool;

    public function getDriver();

    public function disconnect(): void;

    public function query(string $className): Query;

    public function getCommunicator(): ICommunicator;

    public static function CreateDsn(string $host, int $port, ?string $password = null, ?string $user = null): string;

    public function getCacheConnection(): IConnection;
}