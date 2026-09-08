<?php
// Card component
// Usage: include __DIR__ . '/card.php'; with $cardTitle, $cardBody
?>
<div class="card bg-base-100 shadow-xl">
    <div class="card-body">
        <h2 class="card-title"><?= $cardTitle ?? 'Card Title' ?></h2>
        <p><?= $cardBody ?? '' ?></p>
        <?php if (isset($cardActions)): ?>
        <div class="card-actions justify-end">
            <?= $cardActions ?>
        </div>
        <?php endif; ?>
    </div>
</div>
