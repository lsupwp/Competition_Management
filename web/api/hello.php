<?php
// Reusable component: /api/hello
$helloMessage = 'Hello from API!';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $helloMessage = 'POST request received!';
}
