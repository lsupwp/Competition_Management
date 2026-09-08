<?php
// Button component
// Usage: include __DIR__ . '/button.php'; with $btnText, $btnClass
?>
<button class="btn <?= $btnClass ?? 'btn-primary' ?>">
    <?= $btnText ?? 'Button' ?>
</button>
