<?php

namespace Marquee\Interfaces;

use Marquee\Data\Query;

interface IConnection
{
    public function __construct(string $dsn);

    public function connect(): void;

    public function connected(): bool;

    public function disconnect(): void;

    public function query(string $which): Query;

    public function getCommunicator(): ICommunicator;

    public static function CreateDSN(string $host, ?int $port = null, ?string $password = null, ?string $user = null): string;
}