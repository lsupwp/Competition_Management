<?php
// API: /api/team-members
require_once __DIR__ . '/../../vendor/autoload.php';

session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;

if (!$teamId) {
    echo json_encode(['success' => false, 'error' => 'Team ID required']);
    exit;
}

$eventController = new \App\Controllers\EventController();

// Check if user is member of this team
$isMember = false;
$userTeams = $eventController->getUserTeams($_SESSION['user']['id']);
foreach ($userTeams as $team) {
    if ($team['id'] === $teamId) {
        $isMember = true;
        break;
    }
}

if (!$isMember) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Get team members
$members = $eventController->getTeamMembers($teamId);

echo json_encode([
    'success' => true,
    'members' => $members
]);
