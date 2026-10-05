<?php
// Badge component
// Usage: include __DIR__ . '/badge.php'; with $badgeText, $badgeClass
?>
<span class="badge <?= htmlspecialchars((string)($badgeClass ?? 'badge-primary'), ENT_QUOTES, 'UTF-8') ?>">
    <?= htmlspecialchars((string)($badgeText ?? 'Badge'), ENT_QUOTES, 'UTF-8') ?>
</span>
