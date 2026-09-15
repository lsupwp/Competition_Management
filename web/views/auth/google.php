<?php
// Route: /auth/google
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\GoogleAuthService;

session_start();
$_SESSION['google_auth_source'] = $_GET['source'] ?? 'login';

$googleAuth = new GoogleAuthService();
$authUrl = $googleAuth->getAuthUrl();

header('Location: ' . $authUrl);
exit;
