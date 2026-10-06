<?php

namespace App\Services;

class ActivityLogService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Log an activity
     * 
     * @param string $action Action identifier (e.g., 'team.create', 'auth.login')
     * @param string $description Human-readable description
     * @param int|null $userId User who performed the action (null for system)
     * @param string|null $entityType Type of entity affected (e.g., 'team', 'user')
     * @param int|null $entityId ID of the entity
     * @param array|null $metadata Additional context data
     */
    public function log(
        string $action,
        string $description,
        ?int $userId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $metadata = null
    ): void {
        $ipAddress = self::resolveClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $metadataJson = $metadata ? json_encode($metadata) : null;

        // Build SQL with NULL literals where needed — mysqli bind_param rejects null ints on PHP 8.1+
        $userIdSql = $userId === null ? 'NULL' : '?';
        $entityTypeSql = $entityType === null ? 'NULL' : '?';
        $entityIdSql = $entityId === null ? 'NULL' : '?';
        $metadataSql = $metadataJson === null ? 'NULL' : '?';
        $ipSql = $ipAddress === null ? 'NULL' : '?';
        $uaSql = $userAgent === null ? 'NULL' : '?';

        $stmt = $this->db->prepare("
            INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, metadata, ip_address, user_agent)
            VALUES ({$userIdSql}, ?, {$entityTypeSql}, {$entityIdSql}, ?, {$metadataSql}, {$ipSql}, {$uaSql})
        ");

        if (!$stmt) {
            error_log('ActivityLogService: failed to prepare insert');
            return;
        }

        $types = '';
        $params = [];
        if ($userId !== null) {
            $types .= 'i';
            $params[] = $userId;
        }
        $types .= 's';
        $params[] = $action;
        if ($entityType !== null) {
            $types .= 's';
            $params[] = $entityType;
        }
        if ($entityId !== null) {
            $types .= 'i';
            $params[] = $entityId;
        }
        $types .= 's';
        $params[] = $description;
        if ($metadataJson !== null) {
            $types .= 's';
            $params[] = $metadataJson;
        }
        if ($ipAddress !== null) {
            $types .= 's';
            $params[] = $ipAddress;
        }
        if ($userAgent !== null) {
            $types .= 's';
            $params[] = $userAgent;
        }

        // bind_param requires references
        $refs = [];
        foreach ($params as $key => $value) {
            $refs[$key] = &$params[$key];
        }
        $stmt->bind_param($types, ...$refs);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Get activity logs with filters and pagination
     * 
     * @param array $filters Filter options
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array ['logs' => [...], 'total' => int, 'page' => int, 'perPage' => int, 'totalPages' => int]
     */
    public function getLogs(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1'];
        $params = [];
        $types = '';

        // Filter by user
        if (isset($filters['user_id'])) {
            $where[] = 'al.user_id = ?';
            $params[] = $filters['user_id'];
            $types .= 'i';
        }

        // Filter by action
        if (isset($filters['action'])) {
            $where[] = 'al.action = ?';
            $params[] = $filters['action'];
            $types .= 's';
        }

        // Filter by entity
        if (isset($filters['entity_type']) && isset($filters['entity_id'])) {
            $where[] = 'al.entity_type = ? AND al.entity_id = ?';
            $params[] = $filters['entity_type'];
            $params[] = $filters['entity_id'];
            $types .= 'si';
        }

        // Filter by date range
        if (isset($filters['date_from'])) {
            $where[] = 'al.created_at >= ?';
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        if (isset($filters['date_to'])) {
            $where[] = 'al.created_at <= ?';
            $params[] = $filters['date_to'];
            $types .= 's';
        }

        $whereClause = implode(' AND ', $where);

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM activity_logs al WHERE $whereClause";
        $countStmt = $this->db->prepare($countSql);
        if (!empty($params)) {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();

        // Get paginated results
        $offset = ($page - 1) * $perPage;
        $sql = "
            SELECT al.*, u.name as user_name, u.email as user_email, u.avatar_url as user_avatar
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE $whereClause
            ORDER BY al.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->db->prepare($sql);
        $params[] = $perPage;
        $params[] = $offset;
        $types .= 'ii';
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $row['metadata'] = $row['metadata'] ? json_decode($row['metadata'], true) : null;
            $logs[] = $row;
        }
        $stmt->close();

        return [
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }

    /**
     * Get activity logs for a specific entity
     */
    public function getEntityLogs(string $entityType, int $entityId, int $page = 1, int $perPage = 20): array
    {
        return $this->getLogs([
            'entity_type' => $entityType,
            'entity_id' => $entityId
        ], $page, $perPage);
    }

    /**
     * Get activity logs for a specific user
     */
    public function getUserLogs(int $userId, int $page = 1, int $perPage = 20): array
    {
        return $this->getLogs([
            'user_id' => $userId
        ], $page, $perPage);
    }

    /**
     * Prefer X-Forwarded-For only when REMOTE_ADDR is a trusted proxy.
     */
    public static function resolveClientIp(): ?string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? null;
        if ($remote === null || $remote === '') {
            return null;
        }

        $trusted = Env::get('TRUSTED_PROXIES', '127.0.0.1,::1');
        $trustedList = array_filter(array_map('trim', explode(',', (string)$trusted)));

        if (!self::ipIsTrusted($remote, $trustedList)) {
            return $remote;
        }

        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($forwarded === '') {
            return $remote;
        }

        $parts = array_map('trim', explode(',', $forwarded));
        $candidate = $parts[0] ?? '';
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }

        return $remote;
    }

    private static function ipIsTrusted(string $ip, array $trustedList): bool
    {
        foreach ($trustedList as $entry) {
            if ($entry === '') {
                continue;
            }
            if (str_contains($entry, '/')) {
                if (self::ipInCidr($ip, $entry)) {
                    return true;
                }
                continue;
            }
            if ($ip === $entry) {
                return true;
            }
        }
        return false;
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = array_pad(explode('/', $cidr, 2), 2, null);
        if ($subnet === null || $mask === null) {
            return false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
            || filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }
        $mask = (int)$mask;
        if ($mask < 0 || $mask > 32) {
            return false;
        }
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        $maskLong = $mask === 0 ? 0 : (~((1 << (32 - $mask)) - 1) & 0xFFFFFFFF);
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}
