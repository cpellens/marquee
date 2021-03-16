# Marquee Database Layer

## Overview

Marquee is a free multi-database abstraction layer and ORM. Its purpose is to allow for universal code patterns while making
use of intelligent data sources. Whether you choose to store your data in Redis, MySQL, or even a CSV - 99% of your PHP code
remains the same.

## Database Support

* Redis: Testing
* MySQL: Alpha / Testing
* SQLite: Planned
* MSSQL: Planned
* CSV: Planned
* JSON: Planned
* PostgreSQL: Planned
* Azure Cosmos DB: Planned

## About the Author

My name is Charles Pellens. I am a self-taught software engineer from Michigan. I am passionate about
simplifying the web development process through automation and innovation. I have a nack for creating clean, semantic APIs.
I believe that code verbosity should not be dependent solely on comments. This project allows for expressive code by making use of
simple, understandable, and predictable method and class names while maintaining flexible functionality. I hope you find that
my work enables you to quickly get up and going with your next project.

https://charlespellens.me/ | contact@charlespellens.com

## Disclaimer: Not Production Ready

While I am very proud of the progress on this project, please do not use it for any mission critical work at this point.
This is a work in progress and I invite you to play around with it or make it your own.

## Quick Start / Example

```injectablephp
<?php

include 'vendor/autoload.php';

/**
 * Library Use Statements
 */
use Marquee\Core\Connection\MySQLConnection;
use Marquee\Data\Entity;
use Marquee\Exception\Exception;
use Marquee\Schema\Property;

/**
 * PHP Core Use Statements
 */
use \Generator;

/**
 * Define a sample entity with two string properties:
 * - username
 * - password
 */
class User extends Entity
{
    public function getUsername(): string
    {
        return $this->username;
    }

    public static function Properties(): Generator
    {
        yield Property::string('username')->unique();
        yield Property::string('password');
    }
}

$db = new MySQLConnection(MySQLConnection::CreateDsn(DB_HOST, DB_PORT, DB_PASSWORD, DB_USERNAME));
$db->selectDb(DB_NAME);

if ($db->tryConnect($e)) {
    try {
        /**
         * Build the user table if it doesn't already exist in the schema
         */
        $table = User::BuildTable($db);
        if (!$table->exists()) {
            $table->create();
            echo 'Created user table', '<br>';
        }

        /**
         * Query all users
         */
        $userCount = 0;
        $users     = $db->query(User::class)->limit(10)->get();

        echo '<ul>';
        while ($user = $users->next()) {
            echo '<li>', $user, '</li>';
            $userCount++;
        }
        echo '</ul>';

        /**
         * If we have less than 10 users, insert a new one.
         */
        if ($userCount < 10) {
            $insert = $db->query(User::class)->create([
                'username' => 'Test ' . uniqid(),
                'password' => password_hash('test password', PASSWORD_ARGON2I)
            ]);

            if ($user = $insert->next()) {
                echo 'Created test user';
            }
        } else {
            /**
             * Start over if we have 10 users
             */
            $db->query(User::class)->truncate()->next();
            echo 'Deleted all users';
        }
    } catch (Exception $e) {
        echo 'Error: ', $e->getMessage();
    } finally {
        $db->disconnect();
    }
} else {
    exit($e->getMessage());
}
```