<?php

namespace App\Services;

class LoginRateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function tooManyAttempts(?string $ipAddress, string $email): bool
    {
        return $this->tooManyActionAttempts('auth.login_failed', $ipAddress, $email);
    }

    public function tooManyPasswordResetAttempts(?string $ipAddress, string $email): bool
    {
        return $this->tooManyActionAttempts('auth.password_reset_request', $ipAddress, $email);
    }

    private function tooManyActionAttempts(string $action, ?string $ipAddress, string $email): bool
    {
        $windowStart = date('Y-m-d H:i:s', time() - (self::WINDOW_MINUTES * 60));
        $count = 0;

        if ($ipAddress !== null && $ipAddress !== '') {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) AS total
                FROM activity_logs
                WHERE action = ?
                  AND ip_address = ?
                  AND created_at >= ?
            ");
            $stmt->bind_param('sss', $action, $ipAddress, $windowStart);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $count = max($count, (int)($row['total'] ?? 0));
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS total
            FROM activity_logs
            WHERE action = ?
              AND created_at >= ?
              AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.email')) = ?
        ");
        $stmt->bind_param('sss', $action, $windowStart, $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $count = max($count, (int)($row['total'] ?? 0));

        return $count >= self::MAX_ATTEMPTS;
    }

    public function retryAfterMessage(): string
    {
        return 'Too many failed login attempts. Please try again in ' . self::WINDOW_MINUTES . ' minutes.';
    }

    public function passwordResetRetryAfterMessage(): string
    {
        return 'Too many password reset requests. Please try again in ' . self::WINDOW_MINUTES . ' minutes.';
    }
}
