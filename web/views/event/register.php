<?php
require_once __DIR__ . '/../../vendor/autoload.php';

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: /auth/login');
    exit;
}

$db = \App\Services\Database::getInstance();

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['flash_error'] = 'Invalid security token';
    header('Location: /event');
    exit;
}

$eventId = $_POST['event_id'] ?? null;
$registrationType = $_POST['registration_type'] ?? 'individual';

if (!$eventId) {
    $_SESSION['flash_error'] = 'Event ID is required';
    header('Location: /event');
    exit;
}

// Parse registration type
$teamId = null;
if (strpos($registrationType, 'team_') === 0) {
    $teamId = (int)substr($registrationType, 5);
}

$userId = $_SESSION['user']['id'];

// Check if already registered
$checkStmt = $db->prepare("
    SELECT id FROM event_registrations 
    WHERE event_id = ? AND user_id = ? AND deleted_at IS NULL
");
$checkStmt->bind_param('ii', $eventId, $userId);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows > 0) {
    $_SESSION['flash_error'] = 'You are already registered for this event';
    $checkStmt->close();
    header('Location: /event/view.php?id=' . $eventId);
    exit;
}
$checkStmt->close();

// If team registration, verify user is member of team
if ($teamId) {
    $teamCheckStmt = $db->prepare("
        SELECT id FROM team_members 
        WHERE team_id = ? AND user_id = ? AND deleted_at IS NULL
    ");
    $teamCheckStmt->bind_param('ii', $teamId, $userId);
    $teamCheckStmt->execute();
    $teamResult = $teamCheckStmt->get_result();
    
    if ($teamResult->num_rows === 0) {
        $_SESSION['flash_error'] = 'You are not a member of this team';
        $teamCheckStmt->close();
        header('Location: /event/view.php?id=' . $eventId);
        exit;
    }
    $teamCheckStmt->close();
}

// Insert registration
$insertStmt = $db->prepare("
    INSERT INTO event_registrations (event_id, user_id, team_id, status, registered_at)
    VALUES (?, ?, ?, 'confirmed', NOW())
");
$insertStmt->bind_param('iii', $eventId, $userId, $teamId);

if ($insertStmt->execute()) {
    $_SESSION['flash_success'] = 'Successfully registered for event!';
} else {
    $_SESSION['flash_error'] = 'Failed to register for event';
}

$insertStmt->close();

header('Location: /event/view.php?id=' . $eventId);
exit;
