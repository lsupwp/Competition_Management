<?php
// Intentionally blank — placeholder route removed (no open info endpoints).
http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => 'Not found']);
