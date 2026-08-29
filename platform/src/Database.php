<?php

declare(strict_types=1);

namespace AnyTour\Platform;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    public static function connectFromEnvironment(): PDO
    {
        $dsn = trim((string) getenv('ANYTOOUR_DB_DSN'));
        $user = (string) getenv('ANYTOOUR_DB_USER');
        $password = (string) getenv('ANYTOOUR_DB_PASSWORD');

        if ($dsn === '') {
            throw new RuntimeException('ANYTOOUR_DB_DSN is not configured.');
        }

        try {
            return new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Unable to connect to AnyTour Site Platform database.', 0, $e);
        }
    }
}
