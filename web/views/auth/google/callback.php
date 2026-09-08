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
} else {
    session_start();
    $_SESSION['flash_error'] = $result['error'];
    header('Location: /auth/login');
}
exit;
