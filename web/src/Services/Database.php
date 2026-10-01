<?php

namespace App\Services;

class Database
{
    private static ?\mysqli $instance = null;

    public static function getInstance(): \mysqli
    {
        if (self::$instance === null) {
            $host = Env::getOrFail('DB_HOST');
            $user = Env::getOrFail('DB_USERNAME');
            $pass = Env::get('DB_PASSWORD', '') ?? '';
            $name = Env::getOrFail('DB_DATABASE');
            $port = (int)(Env::get('DB_PORT', '3306') ?? '3306');

            mysqli_report(MYSQLI_REPORT_OFF);
            self::$instance = @new \mysqli($host, $user, $pass, $name, $port);

            if (self::$instance->connect_error) {
                throw new \Exception(
                    'Database connection failed: ' . self::$instance->connect_error
                    . " (host={$host}:{$port}, db={$name})"
                );
            }

            self::$instance->set_charset('utf8mb4');
        }

        return self::$instance;
    }
}
