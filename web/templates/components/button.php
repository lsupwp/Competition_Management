<?php
// Button component
// Usage: include __DIR__ . '/button.php'; with $btnText, $btnClass
?>
<button class="btn <?= htmlspecialchars((string)($btnClass ?? 'btn-primary'), ENT_QUOTES, 'UTF-8') ?>">
    <?= htmlspecialchars((string)($btnText ?? 'Button'), ENT_QUOTES, 'UTF-8') ?>
</button>
