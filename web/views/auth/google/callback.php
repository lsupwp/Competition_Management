<?php
// Route: /auth/google/callback
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controllers\AuthController;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_GET['code'])) {
    header('Location: /auth/login');
    exit;
}

$authController = new AuthController();
$result = $authController->handleGoogleCallback($_GET['code']);

if ($result['success']) {
    session_write_close();
    header('Location: ' . $result['redirect']);
} elseif (isset($result['user_not_found']) && $result['user_not_found']) {
    $source = $_SESSION['google_auth_source'] ?? 'login';
    unset($_SESSION['google_auth_source']);
    
    if ($source === 'register') {
        $createResult = $authController->createAccountFromGoogle();
        session_write_close();
        if ($createResult['success']) {
            header('Location: ' . $createResult['redirect']);
        } else {
            $_SESSION['flash_error'] = $createResult['error'] ?? 'Failed to create account';
            header('Location: /auth/register');
        }
    } else {
        session_write_close();
        header('Location: /auth/login?google_signup=1');
    }
} elseif (isset($result['requires_linking']) && $result['requires_linking']) {
    session_write_close();
    header('Location: /auth/link-account');
} else {
    $_SESSION['flash_error'] = $result['error'];
    session_write_close();
    header('Location: /auth/login');
}
exit;
