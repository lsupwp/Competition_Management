<?php

namespace App\Services;

class PasswordPolicyService
{
    public const MIN_LENGTH = 12;

    /** @var list<string> */
    private const COMMON_PASSWORDS = [
        'password', 'password123', 'password1234', 'passw0rd',
        '12345678', '123456789', '1234567890', '123456789012',
        'qwerty123', 'qwerty123456', 'admin123456', 'welcome123',
        'letmein1234', 'iloveyou123', 'abc123456789', 'changeme123',
        'football123', 'baseball123', 'monkey123456', 'dragon123456',
        'master123456', 'login123456', 'princess123', 'sunshine123',
    ];

    public static function validate(string $password): ?string
    {
        if ($password === '') {
            return 'Password is required';
        }

        if (mb_strlen($password) < self::MIN_LENGTH) {
            return 'Password must be at least ' . self::MIN_LENGTH . ' characters long';
        }

        if (mb_strlen($password) > 128) {
            return 'Password must not exceed 128 characters';
        }

        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return 'Password must include at least one letter and one number';
        }

        $normalized = strtolower($password);
        foreach (self::COMMON_PASSWORDS as $common) {
            if ($normalized === $common) {
                return 'This password is too common. Please choose a stronger password';
            }
        }

        return null;
    }
}
