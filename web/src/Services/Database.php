<?php

namespace App\Services;

class Database
{
    private static ?\mysqli $instance = null;

    public static function getInstance(): \mysqli
    {
        if (self::$instance === null) {
            self::$instance = new \mysqli(
                $_ENV['DB_HOST'],
                $_ENV['DB_USERNAME'],
                $_ENV['DB_PASSWORD'],
                $_ENV['DB_DATABASE'],
                (int)$_ENV['DB_PORT']
            );

            if (self::$instance->connect_error) {
                throw new \Exception("Database connection failed: " . self::$instance->connect_error);
            }

            self::$instance->set_charset('utf8mb4');
        }

        return self::$instance;
    }
}
