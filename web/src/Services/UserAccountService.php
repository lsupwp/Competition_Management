<?php

namespace App\Services;

final class UserAccountService
{
    private \mysqli $db;

    public function __construct(?\mysqli $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function findActiveById(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, email, name, password_hash, avatar_url, email_verified_at
            FROM users
            WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();

        return $user;
    }

    public function updateName(int $userId, string $name): bool
    {
        $stmt = $this->db->prepare("UPDATE users SET name = ? WHERE id = ? AND deleted_at IS NULL");
        $stmt->bind_param('si', $name, $userId);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    public function updateAvatarUrl(int $userId, string $avatarUrl): bool
    {
        $stmt = $this->db->prepare("UPDATE users SET avatar_url = ? WHERE id = ? AND deleted_at IS NULL");
        $stmt->bind_param('si', $avatarUrl, $userId);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    /** True if any user row (including soft-deleted) owns this email. */
    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $exists;
    }

    public function changeEmail(int $userId, string $newEmail, string $token, string $expiresAt): bool
    {
        $stmt = $this->db->prepare("
            UPDATE users
            SET email = ?, email_verified_at = NULL, verification_token = ?, verification_token_expires_at = ?
            WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('sssi', $newEmail, $token, $expiresAt, $userId);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    public function setPasswordHash(int $userId, string $passwordHash): bool
    {
        $stmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND deleted_at IS NULL");
        $stmt->bind_param('si', $passwordHash, $userId);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    public function softDelete(int $userId): bool
    {
        $stmt = $this->db->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
        $stmt->bind_param('i', $userId);
        $ok = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();

        return $ok;
    }

    public function deleteAvatarFile(?string $avatarUrl, string $webRoot): void
    {
        if ($avatarUrl === null || $avatarUrl === '') {
            return;
        }

        $path = rtrim($webRoot, '/') . $avatarUrl;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
