<?php

namespace App\Controllers;

use App\Services\Database;
use App\Services\ActivityLogService;

class EventController
{
    private $db;
    private $activityLog;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->activityLog = new ActivityLogService();
    }

    /**
     * Get events visible to user, with pagination and filters
     *
     * @param array{q?:string,tag?:string,date_from?:string,date_to?:string} $filters
     */
    public function getEvents(int $page = 1, int $perPage = 10, ?int $userId = null, ?int $teamId = null, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;

        $where = ['e.deleted_at IS NULL'];
        $types = '';
        $params = [];

        if ($userId !== null) {
            $where[] = "(
                e.created_by = ?
                OR EXISTS (
                    SELECT 1 FROM event_visibility ev
                    WHERE ev.event_id = e.id AND ev.user_id = ? AND ev.deleted_at IS NULL
                )
                OR EXISTS (
                    SELECT 1 FROM event_registrations er
                    WHERE er.event_id = e.id AND er.user_id = ? AND er.deleted_at IS NULL AND er.status != 'cancelled'
                )
                OR EXISTS (
                    SELECT 1 FROM team_members tm
                    WHERE tm.team_id = e.team_id
                      AND tm.user_id = ?
                      AND tm.role = 'owner'
                      AND tm.deleted_at IS NULL
                      AND e.team_id IS NOT NULL
                )
            )";
            $types .= 'iiii';
            array_push($params, $userId, $userId, $userId, $userId);
        }

        if ($teamId !== null) {
            $where[] = 'e.team_id = ?';
            $types .= 'i';
            $params[] = $teamId;
        }

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = 'e.title LIKE ?';
            $types .= 's';
            $params[] = '%' . $q . '%';
        }

        $tag = trim((string)($filters['tag'] ?? ''));
        if ($tag !== '') {
            $where[] = "EXISTS (
                SELECT 1 FROM event_tags et
                WHERE et.event_id = e.id AND et.deleted_at IS NULL AND et.name = ?
            )";
            $types .= 's';
            $params[] = $tag;
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where[] = "EXISTS (
                SELECT 1 FROM event_dates ed
                WHERE ed.event_id = e.id AND ed.deleted_at IS NULL
                  AND DATE(ed.end_datetime) >= ?
            )";
            $types .= 's';
            $params[] = $dateFrom;
        }
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where[] = "EXISTS (
                SELECT 1 FROM event_dates ed
                WHERE ed.event_id = e.id AND ed.deleted_at IS NULL
                  AND DATE(ed.start_datetime) <= ?
            )";
            $types .= 's';
            $params[] = $dateTo;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM events e
            WHERE {$whereSql}
        ");
        if ($types !== '') {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $total = (int)$countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();

        $listTypes = $types . 'ii';
        $listParams = array_merge($params, [$perPage, $offset]);

        $stmt = $this->db->prepare("
            SELECT e.*, u.name as creator_name, t.name as team_name,
                   (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND deleted_at IS NULL) as registration_count
            FROM events e
            JOIN users u ON e.created_by = u.id
            LEFT JOIN teams t ON e.team_id = t.id AND t.deleted_at IS NULL
            WHERE {$whereSql}
            ORDER BY e.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param($listTypes, ...$listParams);
        $stmt->execute();
        $result = $stmt->get_result();

        $events = [];
        while ($row = $result->fetch_assoc()) {
            $row['dates'] = $this->getEventDates($row['id']);
            $row['tags'] = $this->getEventTags($row['id']);
            $events[] = $row;
        }
        $stmt->close();

        return [
            'events' => $events,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => max(1, (int)ceil($total / $perPage))
        ];
    }

    /**
     * Distinct tag names for visible events (optionally scoped to team)
     */
    public function getAvailableEventTags(int $userId, ?int $teamId = null): array
    {
        $types = '';
        $params = [];
        $extra = '';
        if ($teamId !== null) {
            $extra = ' AND e.team_id = ? ';
            $types .= 'i';
            $params[] = $teamId;
        }
        $types .= 'iiii';
        array_push($params, $userId, $userId, $userId, $userId);

        $stmt = $this->db->prepare("
            SELECT DISTINCT et.name
            FROM event_tags et
            INNER JOIN events e ON e.id = et.event_id
            WHERE et.deleted_at IS NULL
              AND e.deleted_at IS NULL
              {$extra}
              AND (
                e.created_by = ?
                OR EXISTS (
                    SELECT 1 FROM event_visibility ev
                    WHERE ev.event_id = e.id AND ev.user_id = ? AND ev.deleted_at IS NULL
                )
                OR EXISTS (
                    SELECT 1 FROM event_registrations er
                    WHERE er.event_id = e.id AND er.user_id = ? AND er.deleted_at IS NULL AND er.status != 'cancelled'
                )
                OR EXISTS (
                    SELECT 1 FROM team_members tm
                    WHERE tm.team_id = e.team_id
                      AND tm.user_id = ?
                      AND tm.role = 'owner'
                      AND tm.deleted_at IS NULL
                      AND e.team_id IS NOT NULL
                )
              )
            ORDER BY et.name
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $tags = [];
        while ($row = $result->fetch_assoc()) {
            $tags[] = $row['name'];
        }
        $stmt->close();
        return $tags;
    }

    /**
     * Get team by ID only if the user is an active member (no name leak to outsiders).
     */
    public function getTeamByIdForMember(int $teamId, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.id, t.name, t.description
            FROM teams t
            INNER JOIN team_members tm
                ON tm.team_id = t.id
               AND tm.user_id = ?
               AND tm.deleted_at IS NULL
            WHERE t.id = ? AND t.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->bind_param('ii', $userId, $teamId);
        $stmt->execute();
        $team = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $team;
    }

    /**
     * Get team by ID (internal; does not check membership)
     */
    public function getTeamById(int $teamId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, description
            FROM teams
            WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $team = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $team;
    }

    /**
     * Teams for /event index: membership teams + visible-event counts
     */
    public function getTeamsForEventIndex(int $userId): array
    {
        $eventVisibility = "
            e.created_by = ?
            OR EXISTS (
                SELECT 1 FROM event_visibility ev
                WHERE ev.event_id = e.id AND ev.user_id = ? AND ev.deleted_at IS NULL
            )
            OR EXISTS (
                SELECT 1 FROM event_registrations er
                WHERE er.event_id = e.id AND er.user_id = ? AND er.deleted_at IS NULL AND er.status != 'cancelled'
            )
            OR EXISTS (
                SELECT 1 FROM team_members tm2
                WHERE tm2.team_id = e.team_id
                  AND tm2.user_id = ?
                  AND tm2.role = 'owner'
                  AND tm2.deleted_at IS NULL
            )
        ";

        $stmt = $this->db->prepare("
            SELECT t.id, t.name, t.description, t.logo_url, tm.role,
                   (
                       SELECT COUNT(*)
                       FROM events e
                       WHERE e.team_id = t.id
                         AND e.deleted_at IS NULL
                         AND ({$eventVisibility})
                   ) AS event_count
            FROM teams t
            INNER JOIN team_members tm ON tm.team_id = t.id
            WHERE tm.user_id = ?
              AND tm.deleted_at IS NULL
              AND t.deleted_at IS NULL
            ORDER BY t.name
        ");
        $stmt->bind_param('iiiii', $userId, $userId, $userId, $userId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $teams = [];
        while ($row = $result->fetch_assoc()) {
            $teams[] = $row;
        }
        $stmt->close();
        return $teams;
    }

    /**
     * Get event by ID
     */
    public function getEventById(int $eventId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT e.*, u.name as creator_name, u.email as creator_email, t.name as team_name
            FROM events e
            JOIN users u ON e.created_by = u.id
            LEFT JOIN teams t ON e.team_id = t.id AND t.deleted_at IS NULL
            WHERE e.id = ? AND e.deleted_at IS NULL
        ");
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        $event = $result->fetch_assoc();
        $stmt->close();

        if (!$event) {
            return null;
        }

        // Get event dates
        $event['dates'] = $this->getEventDates($eventId);
        // Get event tags
        $event['tags'] = $this->getEventTags($eventId);
        // Get registrations
        $event['registrations'] = $this->getEventRegistrations($eventId);

        return $event;
    }

    /**
     * Create new event
     */
    public function createEvent(array $data, int $userId): array
    {
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $location = trim($data['location'] ?? '');
        $teamId = !empty($data['team_id']) ? (int)$data['team_id'] : null;
        $requiredMembers = !empty($data['required_members']) ? (int)$data['required_members'] : 3;
        $visibilityUserIds = $data['visibility_users'] ?? [];

        // Validate
        if (empty($title)) {
            return ['success' => false, 'error' => 'Event title is required'];
        }
        if (strlen($title) > 255) {
            return ['success' => false, 'error' => 'Event title must not exceed 255 characters'];
        }
        if (!$teamId) {
            return ['success' => false, 'error' => 'Team is required'];
        }
        if (!$this->isUserTeamManager($teamId, $userId)) {
            return ['success' => false, 'error' => 'Only team owners and admins can create events'];
        }
        if ($requiredMembers < 1 || $requiredMembers > 100) {
            return ['success' => false, 'error' => 'Required members must be between 1 and 100'];
        }

        $dateValidationError = $this->validateEventDates($data['dates'] ?? null);
        if ($dateValidationError !== null) {
            return ['success' => false, 'error' => $dateValidationError];
        }

        // Start transaction
        $this->db->begin_transaction();

        try {
            // Insert event
            $stmt = $this->db->prepare("
                INSERT INTO events (created_by, team_id, title, description, location, required_members)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('iisssi', $userId, $teamId, $title, $description, $location, $requiredMembers);
            
            if (!$stmt->execute()) {
                throw new \Exception('Failed to create event');
            }
            
            $eventId = $this->db->insert_id;
            $stmt->close();

            // Insert event visibility (team members only)
            $visibilityUserIds = $this->filterVisibilityUserIds($teamId, $visibilityUserIds);
            foreach ($visibilityUserIds as $visibilityUserId) {
                $stmt = $this->db->prepare("
                    INSERT INTO event_visibility (event_id, user_id, granted_by)
                    VALUES (?, ?, ?)
                ");
                $stmt->bind_param('iii', $eventId, $visibilityUserId, $userId);
                $stmt->execute();
                $stmt->close();
            }

            // Insert event dates
            if (isset($data['dates']) && is_array($data['dates'])) {
                foreach ($data['dates'] as $date) {
                    if (empty($date['date_type']) || empty($date['start_datetime']) || empty($date['end_datetime'])) {
                        continue;
                    }
                    
                    $stmt = $this->db->prepare("
                        INSERT INTO event_dates (event_id, date_type, start_datetime, end_datetime, description)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $dateType = $date['date_type'];
                    $startDatetime = $date['start_datetime'];
                    $endDatetime = $date['end_datetime'];
                    $dateDescription = $date['description'] ?? '';
                    $stmt->bind_param('issss', $eventId, $dateType, $startDatetime, $endDatetime, $dateDescription);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            // Insert event tags
            if (isset($data['tags']) && is_array($data['tags'])) {
                foreach ($data['tags'] as $tag) {
                    if (empty($tag['name'])) {
                        continue;
                    }
                    
                    $stmt = $this->db->prepare("
                        INSERT INTO event_tags (event_id, name, color)
                        VALUES (?, ?, ?)
                    ");
                    $tagName = $tag['name'];
                    $color = $this->sanitizeTagColor($tag['color'] ?? null);
                    $stmt->bind_param('iss', $eventId, $tagName, $color);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            // Commit transaction
            $this->db->commit();

            // Log activity
            $this->activityLog->log(
                'event.create',
                "Created event '$title'",
                $userId,
                'event',
                $eventId,
                ['title' => $title, 'team_id' => $teamId, 'required_members' => $requiredMembers]
            );

            return [
                'success' => true,
                'event_id' => $eventId
            ];

        } catch (\Exception $e) {
            // Rollback on error
            $this->db->rollback();
            error_log('event.create failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to create event. Please try again.'];
        }
    }

    /**
     * Update event (creator only)
     */
    public function updateEvent(int $eventId, array $data, int $userId): array
    {
        $event = $this->getEventById($eventId);
        if (!$event) {
            return ['success' => false, 'error' => 'Event not found'];
        }
        if ((int)$event['created_by'] !== (int)$userId) {
            return ['success' => false, 'error' => 'Only the event creator can edit this event'];
        }

        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $location = trim($data['location'] ?? '');
        $teamId = !empty($data['team_id']) ? (int)$data['team_id'] : null;
        $requiredMembers = !empty($data['required_members']) ? (int)$data['required_members'] : 3;
        $visibilityUserIds = $data['visibility_users'] ?? [];
        if (!is_array($visibilityUserIds)) {
            $visibilityUserIds = [];
        }
        $visibilityUserIds = array_map('intval', $visibilityUserIds);

        // Registered members cannot lose visibility
        $registeredUserIds = $this->getRegisteredUserIds($eventId);
        $visibilityUserIds = array_values(array_unique(array_merge($visibilityUserIds, $registeredUserIds)));

        if (empty($title)) {
            return ['success' => false, 'error' => 'Event title is required'];
        }
        if (strlen($title) > 255) {
            return ['success' => false, 'error' => 'Event title must not exceed 255 characters'];
        }
        if (!$teamId) {
            return ['success' => false, 'error' => 'Team is required'];
        }
        if (!$this->isUserTeamManager($teamId, $userId)) {
            return ['success' => false, 'error' => 'Only team owners and admins can assign events to a team'];
        }
        if ($requiredMembers < 1 || $requiredMembers > 100) {
            return ['success' => false, 'error' => 'Required members must be between 1 and 100'];
        }
        $registeredCount = count($registeredUserIds);
        if ($requiredMembers < $registeredCount) {
            return ['success' => false, 'error' => "Required members cannot be below registered count ({$registeredCount})"];
        }

        $dateValidationError = $this->validateEventDates($data['dates'] ?? null);
        if ($dateValidationError !== null) {
            return ['success' => false, 'error' => $dateValidationError];
        }

        $this->db->begin_transaction();

        try {
            $stmt = $this->db->prepare("
                UPDATE events
                SET team_id = ?, title = ?, description = ?, location = ?, required_members = ?
                WHERE id = ? AND deleted_at IS NULL
            ");
            $stmt->bind_param('isssii', $teamId, $title, $description, $location, $requiredMembers, $eventId);
            if (!$stmt->execute()) {
                throw new \Exception('Failed to update event');
            }
            $stmt->close();

            // Soft-delete existing visibility, dates, tags then re-insert
            $softDeleteTables = ['event_visibility', 'event_dates', 'event_tags'];
            foreach ($softDeleteTables as $table) {
                $stmt = $this->db->prepare("UPDATE {$table} SET deleted_at = NOW() WHERE event_id = ? AND deleted_at IS NULL");
                $stmt->bind_param('i', $eventId);
                $stmt->execute();
                $stmt->close();
            }

            $visibilityUserIds = $this->filterVisibilityUserIds($teamId, $visibilityUserIds, $registeredUserIds);
            foreach ($visibilityUserIds as $visibilityUserId) {
                $stmt = $this->db->prepare("
                    INSERT INTO event_visibility (event_id, user_id, granted_by)
                    VALUES (?, ?, ?)
                ");
                $stmt->bind_param('iii', $eventId, $visibilityUserId, $userId);
                $stmt->execute();
                $stmt->close();
            }

            if (isset($data['dates']) && is_array($data['dates'])) {
                foreach ($data['dates'] as $date) {
                    if (empty($date['date_type']) || empty($date['start_datetime']) || empty($date['end_datetime'])) {
                        continue;
                    }
                    $stmt = $this->db->prepare("
                        INSERT INTO event_dates (event_id, date_type, start_datetime, end_datetime, description)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $dateType = $date['date_type'];
                    $startDatetime = $date['start_datetime'];
                    $endDatetime = $date['end_datetime'];
                    $dateDescription = $date['description'] ?? '';
                    $stmt->bind_param('issss', $eventId, $dateType, $startDatetime, $endDatetime, $dateDescription);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            if (isset($data['tags']) && is_array($data['tags'])) {
                foreach ($data['tags'] as $tag) {
                    if (empty($tag['name'])) {
                        continue;
                    }
                    $stmt = $this->db->prepare("
                        INSERT INTO event_tags (event_id, name, color)
                        VALUES (?, ?, ?)
                    ");
                    $tagName = $tag['name'];
                    $color = $this->sanitizeTagColor($tag['color'] ?? null);
                    $stmt->bind_param('iss', $eventId, $tagName, $color);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $this->db->commit();

            $this->activityLog->log(
                'event.update',
                "Updated event '$title'",
                $userId,
                'event',
                $eventId,
                ['title' => $title, 'team_id' => $teamId, 'required_members' => $requiredMembers]
            );

            return ['success' => true, 'event_id' => $eventId];

        } catch (\Exception $e) {
            $this->db->rollback();
            error_log('event.update failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to update event. Please try again.'];
        }
    }

    /**
     * Get visibility user IDs for event
     */
    public function getEventVisibilityUserIds(int $eventId): array
    {
        $stmt = $this->db->prepare("
            SELECT user_id
            FROM event_visibility
            WHERE event_id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        $ids = [];
        while ($row = $result->fetch_assoc()) {
            $ids[] = (int)$row['user_id'];
        }
        $stmt->close();
        return $ids;
    }

    /**
     * Check if user is member of team
     */
    private function isUserTeamMember(int $teamId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT id FROM team_members
            WHERE team_id = ? AND user_id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('ii', $teamId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    /**
     * Check if user is owner or admin of team
     */
    public function isUserTeamManager(int $teamId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT id FROM team_members
            WHERE team_id = ? AND user_id = ? AND role IN ('owner', 'admin') AND deleted_at IS NULL
        ");
        $stmt->bind_param('ii', $teamId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    /**
     * Get user's teams
     */
    public function getUserTeams(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT t.id, t.name
            FROM teams t
            INNER JOIN team_members tm ON t.id = tm.team_id
            WHERE tm.user_id = ? AND tm.deleted_at IS NULL AND t.deleted_at IS NULL
            ORDER BY t.name
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $teams = [];
        while ($row = $result->fetch_assoc()) {
            $teams[] = $row;
        }
        $stmt->close();
        return $teams;
    }

    /**
     * Get teams where user is owner or admin (can create/manage events)
     */
    public function getUserManagedTeams(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT t.id, t.name, tm.role
            FROM teams t
            INNER JOIN team_members tm ON t.id = tm.team_id
            WHERE tm.user_id = ?
              AND tm.role IN ('owner', 'admin')
              AND tm.deleted_at IS NULL
              AND t.deleted_at IS NULL
            ORDER BY t.name
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $teams = [];
        while ($row = $result->fetch_assoc()) {
            $teams[] = $row;
        }
        $stmt->close();
        return $teams;
    }

    /**
     * Get team members
     */
    public function getTeamMembers(int $teamId): array
    {
        $stmt = $this->db->prepare("
            SELECT u.id, u.name, u.email
            FROM users u
            INNER JOIN team_members tm ON u.id = tm.user_id
            WHERE tm.team_id = ? AND tm.deleted_at IS NULL AND u.deleted_at IS NULL
            ORDER BY u.name
        ");
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $result = $stmt->get_result();
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
        $stmt->close();
        return $members;
    }

    /**
     * Calendar feed items for FullCalendar (one item per event_date)
     */
    public function getCalendarEvents(int $userId, string $rangeStart, string $rangeEnd, ?int $teamId = null): array
    {
        $teamSql = '';
        if ($teamId !== null) {
            $teamSql = ' AND e.team_id = ? ';
        }

        $sql = "
            SELECT e.id AS event_id, e.title, e.team_id, t.name AS team_name,
                   ed.id AS date_id, ed.date_type, ed.start_datetime, ed.end_datetime, ed.description AS date_description,
                   (
                       SELECT et.color FROM event_tags et
                       WHERE et.event_id = e.id AND et.deleted_at IS NULL
                       ORDER BY et.id ASC LIMIT 1
                   ) AS tag_color
            FROM event_dates ed
            INNER JOIN events e ON e.id = ed.event_id AND e.deleted_at IS NULL
            LEFT JOIN teams t ON t.id = e.team_id AND t.deleted_at IS NULL
            WHERE ed.deleted_at IS NULL
              AND ed.start_datetime < ?
              AND ed.end_datetime > ?
              {$teamSql}
              AND (
                e.created_by = ?
                OR EXISTS (
                    SELECT 1 FROM event_visibility ev
                    WHERE ev.event_id = e.id AND ev.user_id = ? AND ev.deleted_at IS NULL
                )
                OR EXISTS (
                    SELECT 1 FROM event_registrations er
                    WHERE er.event_id = e.id AND er.user_id = ? AND er.deleted_at IS NULL AND er.status != 'cancelled'
                )
                OR EXISTS (
                    SELECT 1 FROM team_members tm
                    WHERE tm.team_id = e.team_id
                      AND tm.user_id = ?
                      AND tm.role = 'owner'
                      AND tm.deleted_at IS NULL
                      AND e.team_id IS NOT NULL
                )
              )
            ORDER BY ed.start_datetime ASC
        ";

        $stmt = $this->db->prepare($sql);
        if ($teamId !== null) {
            $types = 'ssiiiii';
            $params = [$rangeEnd, $rangeStart, $teamId, $userId, $userId, $userId, $userId];
        } else {
            $types = 'ssiiii';
            $params = [$rangeEnd, $rangeStart, $userId, $userId, $userId, $userId];
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $defaultColors = [
            'competition' => '#22c55e',
            'registration_deadline' => '#38bdf8',
            'meeting' => '#a78bfa',
            'other' => '#94a3b8',
        ];

        $items = [];
        while ($row = $result->fetch_assoc()) {
            $dateType = $row['date_type'] ?: 'other';
            $typeLabel = ucfirst(str_replace('_', ' ', $dateType));
            $color = $row['tag_color'] ?: ($defaultColors[$dateType] ?? $defaultColors['other']);
            $title = $typeLabel . ': ' . $row['title'];

            $items[] = [
                'id' => 'date_' . $row['date_id'],
                'title' => $title,
                'start' => date('c', strtotime($row['start_datetime'])),
                'end' => date('c', strtotime($row['end_datetime'])),
                'url' => '/event/view?id=' . \App\Services\IdEncoder::encode((int)$row['event_id']),
                'backgroundColor' => $color,
                'borderColor' => $color,
                'textColor' => '#0f172a',
                'extendedProps' => [
                    'event_id' => (int)$row['event_id'],
                    'date_type' => $dateType,
                    'team_name' => $row['team_name'],
                    'description' => $row['date_description'],
                ],
            ];
        }
        $stmt->close();
        return $items;
    }

    /**
     * Get event dates
     */
    private function getEventDates(int $eventId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM event_dates
            WHERE event_id = ? AND deleted_at IS NULL
            ORDER BY start_datetime ASC
        ");
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $dates = [];
        while ($row = $result->fetch_assoc()) {
            $dates[] = $row;
        }
        $stmt->close();

        return $dates;
    }

    /**
     * Get event tags
     */
    private function getEventTags(int $eventId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM event_tags
            WHERE event_id = ? AND deleted_at IS NULL
            ORDER BY name ASC
        ");
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tags = [];
        while ($row = $result->fetch_assoc()) {
            $tags[] = $row;
        }
        $stmt->close();

        return $tags;
    }

    /**
     * Get event registrations
     */
    private function getEventRegistrations(int $eventId): array
    {
        $stmt = $this->db->prepare("
            SELECT er.*, u.name as user_name, u.email as user_email, t.name as team_name
            FROM event_registrations er
            JOIN users u ON er.user_id = u.id
            LEFT JOIN teams t ON er.team_id = t.id
            WHERE er.event_id = ? AND er.deleted_at IS NULL
            ORDER BY er.registered_at DESC
        ");
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $registrations = [];
        while ($row = $result->fetch_assoc()) {
            $registrations[] = $row;
        }
        $stmt->close();

        return $registrations;
    }

    /**
     * Register user for event
     */
    public function registerForEvent(int $eventId, int $userId, ?int $teamId = null): array
    {
        // Check if event exists
        $event = $this->getEventById($eventId);
        if (!$event) {
            return ['success' => false, 'error' => 'Event not found'];
        }

        // Check if user can see this event (visibility check)
        if (!$this->canUserSeeEvent($eventId, $userId)) {
            return ['success' => false, 'error' => 'You do not have permission to register for this event'];
        }

        // Check if already registered
        if ($this->isUserRegistered($eventId, $userId)) {
            return ['success' => false, 'error' => 'You are already registered for this event'];
        }

        // Team registration only allowed for event's own team
        if ($teamId) {
            if (empty($event['team_id']) || (int)$teamId !== (int)$event['team_id']) {
                return ['success' => false, 'error' => 'You can only register with this event\'s team'];
            }
            if (!$this->isUserTeamMember($teamId, $userId)) {
                return ['success' => false, 'error' => 'You are not a member of this team'];
            }
        }

        // Insert registration (team_id may be NULL for individual registration)
        if ($teamId === null) {
            $stmt = $this->db->prepare("
                INSERT INTO event_registrations (event_id, user_id, team_id, status, registered_at)
                VALUES (?, ?, NULL, 'confirmed', NOW())
            ");
            $stmt->bind_param('ii', $eventId, $userId);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO event_registrations (event_id, user_id, team_id, status, registered_at)
                VALUES (?, ?, ?, 'confirmed', NOW())
            ");
            $stmt->bind_param('iii', $eventId, $userId, $teamId);
        }
        
        if (!$stmt->execute()) {
            $stmt->close();
            return ['success' => false, 'error' => 'Failed to register for event'];
        }
        
        $registrationId = $this->db->insert_id;
        $stmt->close();

        // Log activity
        $this->activityLog->log(
            'event.register',
            "Registered for event '{$event['title']}'",
            $userId,
            'event',
            $eventId,
            ['registration_id' => $registrationId, 'team_id' => $teamId]
        );

        return [
            'success' => true,
            'registration_id' => $registrationId,
            'message' => 'Successfully registered for event'
        ];
    }

    /**
     * Unregister user from event (self) or kick (event creator)
     */
    public function unregisterFromEvent(int $eventId, int $targetUserId, int $actorUserId): array
    {
        $event = $this->getEventById($eventId);
        if (!$event) {
            return ['success' => false, 'error' => 'Event not found'];
        }

        $isSelf = (int)$targetUserId === (int)$actorUserId;
        $isOwner = (int)$event['created_by'] === (int)$actorUserId;

        if (!$isSelf && !$isOwner) {
            return ['success' => false, 'error' => 'You do not have permission to remove this registration'];
        }

        if (!$this->isUserRegistered($eventId, $targetUserId)) {
            return ['success' => false, 'error' => $isSelf
                ? 'You are not registered for this event'
                : 'That user is not registered for this event'];
        }

        $stmt = $this->db->prepare("
            UPDATE event_registrations
            SET status = 'cancelled', deleted_at = NOW()
            WHERE event_id = ? AND user_id = ? AND deleted_at IS NULL AND status != 'cancelled'
        ");
        $stmt->bind_param('ii', $eventId, $targetUserId);

        if (!$stmt->execute() || $stmt->affected_rows === 0) {
            $stmt->close();
            return ['success' => false, 'error' => $isSelf
                ? 'Failed to unregister from event'
                : 'Failed to remove registration'];
        }
        $stmt->close();

        if ($isSelf) {
            $this->activityLog->log(
                'event.unregister',
                "Unregistered from event '{$event['title']}'",
                $actorUserId,
                'event',
                $eventId
            );
            return [
                'success' => true,
                'message' => 'Successfully unregistered from event'
            ];
        }

        $this->activityLog->log(
            'event.kick',
            "Removed user #{$targetUserId} from event '{$event['title']}'",
            $actorUserId,
            'event',
            $eventId,
            ['target_user_id' => $targetUserId]
        );

        return [
            'success' => true,
            'message' => 'Registration removed successfully'
        ];
    }

    /**
     * Soft-delete event (creator or team owner)
     */
    public function deleteEvent(int $eventId, int $userId): array
    {
        $event = $this->getEventById($eventId);
        if (!$event) {
            return ['success' => false, 'error' => 'Event not found'];
        }

        if (!$this->canUserDeleteEvent($eventId, $userId)) {
            return ['success' => false, 'error' => 'You do not have permission to delete this event'];
        }

        $this->db->begin_transaction();

        try {
            $tables = ['event_visibility', 'event_dates', 'event_tags', 'event_registrations', 'events'];
            foreach ($tables as $table) {
                if ($table === 'events') {
                    $stmt = $this->db->prepare("UPDATE events SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
                } else {
                    $stmt = $this->db->prepare("UPDATE {$table} SET deleted_at = NOW() WHERE event_id = ? AND deleted_at IS NULL");
                }
                $stmt->bind_param('i', $eventId);
                if (!$stmt->execute()) {
                    throw new \Exception('Failed to delete event data');
                }
                $stmt->close();
            }

            $this->db->commit();

            $this->activityLog->log(
                'event.delete',
                "Deleted event '{$event['title']}'",
                $userId,
                'event',
                $eventId
            );

            return ['success' => true, 'message' => 'Event deleted successfully'];
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log('event.delete failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to delete event. Please try again.'];
        }
    }

    /**
     * Event creator only may edit
     */
    public function canUserEditEvent(int $eventId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT id FROM events
            WHERE id = ? AND created_by = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('ii', $eventId, $userId);
        $stmt->execute();
        $allowed = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $allowed;
    }

    /**
     * Event creator or team owner may delete
     */
    public function canUserDeleteEvent(int $eventId, int $userId): bool
    {
        if ($this->canUserEditEvent($eventId, $userId)) {
            return true;
        }
        return $this->isTeamOwnerOfEvent($eventId, $userId);
    }

    /**
     * User is owner of the team linked to this event
     */
    public function isTeamOwnerOfEvent(int $eventId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT tm.id
            FROM events e
            INNER JOIN team_members tm ON tm.team_id = e.team_id
            WHERE e.id = ?
              AND e.deleted_at IS NULL
              AND e.team_id IS NOT NULL
              AND tm.user_id = ?
              AND tm.role = 'owner'
              AND tm.deleted_at IS NULL
        ");
        $stmt->bind_param('ii', $eventId, $userId);
        $stmt->execute();
        $isOwner = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $isOwner;
    }

    /**
     * Get user IDs with active registration for event
     */
    public function getRegisteredUserIds(int $eventId): array
    {
        $stmt = $this->db->prepare("
            SELECT user_id
            FROM event_registrations
            WHERE event_id = ? AND deleted_at IS NULL AND status != 'cancelled'
        ");
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        $ids = [];
        while ($row = $result->fetch_assoc()) {
            $ids[] = (int)$row['user_id'];
        }
        $stmt->close();
        return $ids;
    }

    /**
     * Check if user can see event
     */
    public function canUserSeeEvent(int $eventId, int $userId): bool
    {
        // Check if user is event creator
        $stmt = $this->db->prepare("
            SELECT id FROM events
            WHERE id = ? AND created_by = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('ii', $eventId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $stmt->close();
            return true;
        }
        $stmt->close();

        // Team owner can see every event for their team
        if ($this->isTeamOwnerOfEvent($eventId, $userId)) {
            return true;
        }

        // Registered users always keep access
        if ($this->isUserRegistered($eventId, $userId)) {
            return true;
        }

        // Check if user has visibility permission
        $stmt = $this->db->prepare("
            SELECT id FROM event_visibility
            WHERE event_id = ? AND user_id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('ii', $eventId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $hasPermission = $result->num_rows > 0;
        $stmt->close();

        return $hasPermission;
    }

    /**
     * Check if user is already registered for event
     */
    public function isUserRegistered(int $eventId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT id FROM event_registrations
            WHERE event_id = ? AND user_id = ? AND deleted_at IS NULL AND status != 'cancelled'
        ");
        $stmt->bind_param('ii', $eventId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $isRegistered = $result->num_rows > 0;
        $stmt->close();
        return $isRegistered;
    }

    /**
     * Keep only active team members (plus optional locked IDs such as registrants).
     *
     * @param list<int|string>|mixed $visibilityUserIds
     * @param list<int> $extraAllowedIds
     * @return list<int>
     */
    private function filterVisibilityUserIds(int $teamId, $visibilityUserIds, array $extraAllowedIds = []): array
    {
        if (!is_array($visibilityUserIds)) {
            $visibilityUserIds = [];
        }

        $memberIds = [];
        foreach ($this->getTeamMembers($teamId) as $member) {
            $memberIds[(int)$member['id']] = true;
        }
        foreach ($extraAllowedIds as $extraId) {
            $extraId = (int)$extraId;
            if ($extraId > 0) {
                $memberIds[$extraId] = true;
            }
        }

        $allowed = [];
        foreach ($visibilityUserIds as $visibilityUserId) {
            $visibilityUserId = (int)$visibilityUserId;
            if ($visibilityUserId > 0 && isset($memberIds[$visibilityUserId])) {
                $allowed[$visibilityUserId] = $visibilityUserId;
            }
        }

        return array_values($allowed);
    }

    /** Allow only #RGB or #RRGGBB hex colors. */
    private function sanitizeTagColor(?string $color): string
    {
        $color = trim((string)$color);
        if (preg_match('/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', $color) === 1) {
            return strtolower($color);
        }
        return '#3b82f6';
    }

    /**
     * Ensure every provided date range has end after start.
     */
    private function validateEventDates($dates): ?string
    {
        if ($dates === null || $dates === '') {
            return null;
        }
        if (!is_array($dates)) {
            return 'Invalid date ranges';
        }

        foreach ($dates as $index => $date) {
            if (!is_array($date)) {
                continue;
            }
            $start = trim((string)($date['start_datetime'] ?? ''));
            $end = trim((string)($date['end_datetime'] ?? ''));
            if ($start === '' || $end === '') {
                continue;
            }

            $startTs = strtotime($start);
            $endTs = strtotime($end);
            if ($startTs === false || $endTs === false) {
                return 'Invalid date/time format in schedule #' . ((int)$index + 1);
            }
            if ($endTs <= $startTs) {
                return 'End time must be after start time for every schedule';
            }
        }

        return null;
    }
}
