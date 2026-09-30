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
     * Get all events with pagination
     */
    public function getEvents(int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        
        // Get total count
        $countStmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM events
            WHERE deleted_at IS NULL
        ");
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();

        // Get events with pagination
        $stmt = $this->db->prepare("
            SELECT e.*, u.name as creator_name,
                   (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND deleted_at IS NULL) as registration_count
            FROM events e
            JOIN users u ON e.created_by = u.id
            WHERE e.deleted_at IS NULL
            ORDER BY e.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param('ii', $perPage, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $events = [];
        while ($row = $result->fetch_assoc()) {
            // Get event dates
            $row['dates'] = $this->getEventDates($row['id']);
            // Get event tags
            $row['tags'] = $this->getEventTags($row['id']);
            $events[] = $row;
        }
        $stmt->close();

        return [
            'events' => $events,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }

    /**
     * Get event by ID
     */
    public function getEventById(int $eventId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT e.*, u.name as creator_name, u.email as creator_email
            FROM events e
            JOIN users u ON e.created_by = u.id
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

        // Validate
        if (empty($title)) {
            return ['success' => false, 'error' => 'Event title is required'];
        }
        if (strlen($title) > 255) {
            return ['success' => false, 'error' => 'Event title must not exceed 255 characters'];
        }

        // Start transaction
        $this->db->begin_transaction();

        try {
            // Insert event
            $stmt = $this->db->prepare("
                INSERT INTO events (created_by, title, description, location)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param('isss', $userId, $title, $description, $location);
            
            if (!$stmt->execute()) {
                throw new \Exception('Failed to create event');
            }
            
            $eventId = $this->db->insert_id;
            $stmt->close();

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
                    $dateDescription = $date['description'] ?? '';
                    $stmt->bind_param('issss', $eventId, $date['date_type'], $date['start_datetime'], $date['end_datetime'], $dateDescription);
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
                    $color = $tag['color'] ?? '#3b82f6';
                    $stmt->bind_param('iss', $eventId, $tag['name'], $color);
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
                ['title' => $title]
            );

            return [
                'success' => true,
                'event_id' => $eventId
            ];

        } catch (\Exception $e) {
            // Rollback on error
            $this->db->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
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
}
