<?php
// Route: /auth/logout
require_once __DIR__ . '/../../vendor/autoload.php';

session_start();

// Capture user info before destroying session
$userId = $_SESSION['user']['id'] ?? null;
$userName = $_SESSION['user']['name'] ?? 'Unknown';

// Log logout if user was logged in
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

session_destroy();

header('Location: /auth/login');
exit;
