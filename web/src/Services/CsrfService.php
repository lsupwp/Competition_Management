<?php

namespace App\Services;

class CsrfService
{
    private const TOKEN_KEY = '_csrf_token';
    private const TOKEN_LIFETIME = 7200; // 2 hours

    public static function generateToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION[self::TOKEN_KEY] = [
            'token' => $token,
            'expires' => time() + self::TOKEN_LIFETIME,
        ];

        return $token;
    }

    public static function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[self::TOKEN_KEY]) || 
            $_SESSION[self::TOKEN_KEY]['expires'] < time()) {
            return self::generateToken();
        }

        return $_SESSION[self::TOKEN_KEY]['token'];
    }

    public static function validateToken(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($token) || !isset($_SESSION[self::TOKEN_KEY])) {
            return false;
        }

        $stored = $_SESSION[self::TOKEN_KEY];

        if ($stored['expires'] < time()) {
            unset($_SESSION[self::TOKEN_KEY]);
            return false;
        }

        return hash_equals($stored['token'], $token);
    }
}
