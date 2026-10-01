<?php
// Input component
// Usage: include __DIR__ . '/input.php'; with $inputName, $inputLabel, $inputType
// Optional: $inputTogglePassword = true (for password fields with eye icon)
?>
<div class="form-control flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 w-full">
    <label class="label sm:w-32 flex-shrink-0 py-0 justify-start">
        <span class="label-text"><?= $inputLabel ?? 'Label' ?></span>
    </label>
    <div class="flex-1 relative w-full min-w-0">
        <input
            type="<?= $inputType ?? 'text' ?>"
            name="<?= $inputName ?? 'input' ?>"
            id="<?= $inputName ?? 'input' ?>"
            placeholder="<?= $inputPlaceholder ?? '' ?>"
            class="input input-bordered w-full <?= isset($inputTogglePassword) && $inputTogglePassword ? 'pr-10' : '' ?>"
            <?= isset($inputValue) && $inputType !== 'password' ? "value=\"{$inputValue}\"" : '' ?>
            <?= isset($inputRequired) && $inputRequired ? 'required' : '' ?>
            <?= $inputType === 'password' ? 'autocomplete="current-password"' : '' ?>
        />
        <?php if (isset($inputTogglePassword) && $inputTogglePassword): ?>
        <button type="button" class="toggle-password absolute right-3 top-1/2 -translate-y-1/2 btn btn-ghost btn-xs" data-target="<?= $inputName ?? 'input' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" class="eye-open h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            <svg xmlns="http://www.w3.org/2000/svg" class="eye-closed h-4 w-4 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
            </svg>
        </button>
        <?php endif; ?>
    </div>
</div>
