<?php
// Route: /auth/logout
require_once __DIR__ . '/../../vendor/autoload.php';

session_start();
session_destroy();

header('Location: /auth/login');
exit;
