<?php

namespace Marquee\Interfaces;

use Marquee\Data\Query;
use Marquee\Data\QueryResult;

interface ICommunicator
{
    public function execute(Query $query, int $flags): QueryResult;
    public function getConnection(): IConnection;
}