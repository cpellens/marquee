# Marquee Database Layer

## Overview

Marquee is a free database abstraction layer and ORM. Its purpose is to allow for universal code patterns while making
use of intelligent data sources. Whether you choose to store your data in Redis, MySQL, or even a CSV, your PHP code
remains the same. (Aside from the driver selection)

## Quick Start

```injectablephp
<?php

include 'vendor/autoload.php';

use Marquee\Core\Connection\RedisConnection;
use Marquee\Data\Entity;
use Marquee\Data\Relationship;

class User extends Entity {
    public static function Properties() : array{
        return array_flip(['first_name', 'last_name', 'email']);
    }

    public function getContactInfo(): Relationship {
        return $this->children(ContactInfo::class);
    }
}

class ContactInfo extends Entity {
    public static function Properties() : array{
        return array_flip(['user_id', 'label', 'type', 'info']);
    }
}

$redis = new RedisConnection(RedisConnection::CreateDsn('127.0.0.1'));

if ($redis->tryConnect($e)) {
    $user = $redis->query(User::class)->create([
                                                   'first_name' => 'Bob',
                                                   'last_name' => 'Smith',
                                                   'email' => 'bob.smith@org.com'
                                               ])->next();

    $contact = $user->create(ContactInfo::class, [
        'label' => 'Phone Number',
        'type' => 'phone',
        'info' => '(999) 999-9999'
    ]);

    $redis->query(ContactInfo::class)->update(['type' => 'phone number'], $contact)->execute();

    echo 'Contact ', $user->first_name, ' @ ', $user->contactInfo->one()->info;

    $redis->disconnect();
} else {
    echo 'Could not connect: ', $e->getMessage();
}
```

## Database Support

* Redis: Alpha
* MySQL: In development
* MSSQL: Planned
* CSV: Planned
* JSON: Planned
* PostgreSQL: Planned
* Sqlite: Planned
* Azure Cosmos DB: Planned

## About the Author

My name is Charles Pellens. I am a self-taught software engineer from Michigan, United States. I am passionate about
simplifying the web through automation and innovation. I have a nack for creating clean, semantic, and consistent APIs.
I believe that verbosity should be optional, leaving the bridge from amateur to expert an open and easy-to-traverse
path. This project allows for that by creating simple, understandable, and predictable methods while leaving
functionality in place to alter core functionality and to enable fully custom queries through code - not SQL queries.

https://charlespellens.com | contact@charlespellens.com