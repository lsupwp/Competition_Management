<?php
// Input component
// Usage: include __DIR__ . '/input.php'; with $inputName, $inputLabel, $inputType
?>
<div class="form-control">
    <label class="label">
        <span class="label-text"><?= $inputLabel ?? 'Label' ?></span>
    </label>
    <input
        type="<?= $inputType ?? 'text' ?>"
        name="<?= $inputName ?? 'input' ?>"
        placeholder="<?= $inputPlaceholder ?? '' ?>"
        class="input input-bordered"
        <?= isset($inputValue) ? "value=\"{$inputValue}\"" : '' ?>
        <?= isset($inputRequired) && $inputRequired ? 'required' : '' ?>
    />
</div>
