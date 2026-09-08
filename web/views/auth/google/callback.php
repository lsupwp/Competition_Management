<?php
// Route: /auth/google/callback
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controllers\AuthController;

if (!isset($_GET['code'])) {
    header('Location: /auth/login');
    exit;
}

$authController = new AuthController();
$result = $authController->handleGoogleCallback($_GET['code']);

if ($result['success']) {
    header('Location: ' . $result['redirect']);
} elseif (isset($result['user_not_found']) && $result['user_not_found']) {
    // User not found - redirect to login with flag
    header('Location: /auth/login?google_signup=1');
} elseif (isset($result['requires_linking']) && $result['requires_linking']) {
    // User exists but needs account linking
    header('Location: /auth/link-account');
} else {
    session_start();
    $_SESSION['flash_error'] = $result['error'];
    header('Location: /auth/login');
}
exit;
