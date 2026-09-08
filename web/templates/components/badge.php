<?php
// Badge component
// Usage: include __DIR__ . '/badge.php'; with $badgeText, $badgeClass
?>
<span class="badge <?= $badgeClass ?? 'badge-primary' ?>">
    <?= $badgeText ?? 'Badge' ?>
</span>
