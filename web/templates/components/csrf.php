<?php
// CSRF Token Component
// Usage: include __DIR__ . '/csrf.php';
use App\Services\CsrfService;

$csrfToken = CsrfService::getToken();
?>
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
