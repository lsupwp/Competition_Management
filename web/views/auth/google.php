<?php
// Route: /auth/google
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\GoogleAuthService;

$googleAuth = new GoogleAuthService();
$authUrl = $googleAuth->getAuthUrl();

header('Location: ' . $authUrl);
exit;
