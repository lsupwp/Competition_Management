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

if (!$eventId) {
    $_SESSION['flash_error'] = 'Event ID is required';
    header('Location: /event');
    exit;
}

$actorUserId = (int)$_SESSION['user']['id'];
$targetUserId = $actorUserId;

if (!empty($_POST['user_id'])) {
    $decodedTarget = \App\Services\IdEncoder::decode($_POST['user_id']);
    if ($decodedTarget) {
        $targetUserId = (int)$decodedTarget;
    }
}

$eventController = new \App\Controllers\EventController();
$result = $eventController->unregisterFromEvent($eventId, $targetUserId, $actorUserId);

if ($result['success']) {
    $_SESSION['flash_success'] = $result['message'];
} else {
    $_SESSION['flash_error'] = $result['error'];
}

if ($eventController->canUserSeeEvent($eventId, $actorUserId)) {
    header('Location: /event/view?id=' . \App\Services\IdEncoder::encode($eventId));
} else {
    header('Location: /event');
}
exit;
