<?php
// API: /api/events-calendar
require_once __DIR__ . '/../vendor/autoload.php';

\App\Services\SessionService::start();

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

if ($start === '' || $end === '') {
    http_response_code(400);
    echo json_encode(['error' => 'start and end are required']);
    exit;
}

$eventController = new \App\Controllers\EventController();
$userId = (int)$_SESSION['user']['id'];

$teamId = null;
if (!empty($_GET['team'])) {
    $decoded = \App\Services\IdEncoder::decode($_GET['team']);
    if ($decoded) {
        $candidate = (int)$decoded;
        // Only honor team filter when the caller is a member (EVT-02)
        if ($eventController->getTeamByIdForMember($candidate, $userId)) {
            $teamId = $candidate;
        }
    }
}

$events = $eventController->getCalendarEvents(
    $userId,
    $start,
    $end,
    $teamId
);

echo json_encode($events);
