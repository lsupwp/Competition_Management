<?php
// Card component
// Usage: include __DIR__ . '/card.php'; with $cardTitle, $cardBody
?>
<div class="card bg-base-100 shadow-xl">
    <div class="card-body">
        <h2 class="card-title"><?= h($cardTitle ?? 'Card Title') ?></h2>
        <p><?= h($cardBody ?? '') ?></p>
        <?php if (isset($cardActions)): ?>
        <div class="card-actions justify-end">
            <?= $cardActions ?>
        </div>
        <?php endif; ?>
    </div>
</div>
