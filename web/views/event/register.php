<?php
require_once __DIR__ . '/../../vendor/autoload.php';

\App\Services\SessionService::start();

if (!isset($_SESSION['user'])) {
    header('Location: /auth/login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /event');
    exit;
}

if (!\App\Services\CsrfService::validateToken($_POST['csrf_token'] ?? null)) {
    $_SESSION['flash_error'] = 'Invalid security token';
    header('Location: /event');
    exit;
}

$encodedEventId = $_POST['event_id'] ?? null;
$eventId = $encodedEventId ? \App\Services\IdEncoder::decode($encodedEventId) : null;
$registrationType = $_POST['registration_type'] ?? 'individual';

if (!$eventId) {
    $_SESSION['flash_error'] = 'Event ID is required';
    header('Location: /event');
    exit;
}

$teamId = null;
if (strpos($registrationType, 'team_') === 0) {
    $teamId = (int)substr($registrationType, 5);
}

$eventController = new \App\Controllers\EventController();
$result = $eventController->registerForEvent($eventId, $_SESSION['user']['id'], $teamId);

if ($result['success']) {
    $_SESSION['flash_success'] = $result['message'];
} else {
    $_SESSION['flash_error'] = $result['error'];
}

header('Location: /event/view?id=' . \App\Services\IdEncoder::encode($eventId));
exit;
