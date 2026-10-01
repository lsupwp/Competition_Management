<?php
// Route: /auth/logout
require_once __DIR__ . '/../../vendor/autoload.php';

\App\Services\SessionService::start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

if (!\App\Services\CsrfService::validateToken($_POST['csrf_token'] ?? null)) {
    $_SESSION['flash_error'] = 'Invalid security token. Please try again.';
    header('Location: /');
    exit;
}

$userId = $_SESSION['user']['id'] ?? null;
$userName = $_SESSION['user']['name'] ?? 'Unknown';

if ($userId) {
    $activityLog = new \App\Services\ActivityLogService();
    $activityLog->log(
        'auth.logout',
        "User '$userName' logged out",
        $userId,
        'user',
        $userId
    );
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        (bool)$params['secure'],
        (bool)$params['httponly']
    );
}
session_destroy();

header('Location: /auth/login');
exit;
