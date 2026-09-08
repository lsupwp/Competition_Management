<?php
// Input component
// Usage: include __DIR__ . '/input.php'; with $inputName, $inputLabel, $inputType
?>
<div class="form-control flex flex-row items-center gap-4">
    <label class="label w-32 flex-shrink-0">
        <span class="label-text"><?= $inputLabel ?? 'Label' ?></span>
    </label>
    <input
        type="<?= $inputType ?? 'text' ?>"
        name="<?= $inputName ?? 'input' ?>"
        placeholder="<?= $inputPlaceholder ?? '' ?>"
        class="input input-bordered flex-1"
        <?= isset($inputValue) ? "value=\"{$inputValue}\"" : '' ?>
        <?= isset($inputRequired) && $inputRequired ? 'required' : '' ?>
    />
</div>
