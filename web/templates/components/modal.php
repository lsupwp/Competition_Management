<?php
// Modal component
// Usage: include __DIR__ . '/modal.php'; with $modalId, $modalTitle, $modalContent
?>
<dialog id="<?= $modalId ?? 'modal' ?>" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg"><?= $modalTitle ?? 'Modal Title' ?></h3>
        <div class="py-4">
            <?= $modalContent ?? '' ?>
        </div>
        <div class="modal-action">
            <form method="dialog">
                <button class="btn">Close</button>
            </form>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>
