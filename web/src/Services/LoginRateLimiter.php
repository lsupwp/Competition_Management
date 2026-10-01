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
        $windowStart = date('Y-m-d H:i:s', time() - (self::WINDOW_MINUTES * 60));
        $count = 0;

        if ($ipAddress !== null && $ipAddress !== '') {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) AS total
                FROM activity_logs
                WHERE action = 'auth.login_failed'
                  AND ip_address = ?
                  AND created_at >= ?
            ");
            $stmt->bind_param('ss', $ipAddress, $windowStart);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $count = max($count, (int)($row['total'] ?? 0));
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS total
            FROM activity_logs
            WHERE action = 'auth.login_failed'
              AND created_at >= ?
              AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.email')) = ?
        ");
        $stmt->bind_param('ss', $windowStart, $email);
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
}
