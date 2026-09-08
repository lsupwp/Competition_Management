<?php
// Alert component
// Usage: include __DIR__ . '/alert.php'; with $alertType, $alertMessage
$alertClass = match($alertType ?? 'info') {
    'success' => 'alert-success',
    'warning' => 'alert-warning',
    'error' => 'alert-error',
    default => 'alert-info',
};
?>
<div class="alert <?= $alertClass ?>">
    <span><?= $alertMessage ?? '' ?></span>
</div>
