<?php
// Main layout template
// Usage: include __DIR__ . '/../templates/layout.php';
if (session_status() === PHP_SESSION_NONE) {
    \App\Services\SessionService::start();
}
?>
<!DOCTYPE html>
<html lang="th" data-theme="<?= $theme ?? 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Team Competition', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" href="/assets/logo.png" type="image/png" />

    <!-- Tailwind CSS v4 (Play CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <!-- DaisyUI v5 -->
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5/themes.css" rel="stylesheet" type="text/css" />

    <?php if (!empty($extraHead)): ?>
        <?= $extraHead ?>
    <?php endif; ?>

    <style type="text/tailwindcss">
        @theme {
            --color-primary: oklch(0.7 0.15 198);
            --color-primary-focus: oklch(0.6 0.18 198);
            --color-primary-content: oklch(0.98 0 0);
        }
    </style>
    <style>
        /* Site-wide mobile safety */
        html, body { overflow-x: hidden; }
        img, video, canvas, svg { max-width: 100%; height: auto; }
        .modal-box { width: min(100% - 2rem, 32rem); max-width: 100%; }
        @media (max-width: 640px) {
            .table { font-size: 0.875rem; }
        }
    </style>
</head>
<body class="min-h-screen bg-base-200 flex flex-col overflow-x-hidden">
    <?php include_once __DIR__ . '/header.php'; ?>

    <main class="<?= !empty($fullBleed) ? 'flex-1 w-full min-w-0' : 'flex-1 w-full min-w-0 px-3 sm:px-4 py-4' ?>">
        <?= $content ?? '' ?>
    </main>

    <?php include_once __DIR__ . '/footer.php'; ?>

    <dialog id="app-confirm-modal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg" id="app-confirm-title">Confirm</h3>
            <p class="py-4 whitespace-pre-wrap" id="app-confirm-message"></p>
            <div class="modal-action">
                <button type="button" class="btn" id="app-confirm-cancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="app-confirm-ok">Confirm</button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button type="submit">close</button>
        </form>
    </dialog>

    <dialog id="app-alert-modal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg" id="app-alert-title">Notice</h3>
            <p class="py-4 whitespace-pre-wrap" id="app-alert-message"></p>
            <div class="modal-action">
                <button type="button" class="btn btn-primary" id="app-alert-ok">OK</button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button type="submit">close</button>
        </form>
    </dialog>

    <script>
        // Theme toggle
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('change', function(e) {
                const theme = e.target.checked ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', theme);
                localStorage.setItem('theme', theme);
            });
        }

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        if (themeToggle) {
            themeToggle.checked = savedTheme === 'dark';
        }

        window.appConfirm = function(message, options = {}) {
            return new Promise((resolve) => {
                const modal = document.getElementById('app-confirm-modal');
                const titleEl = document.getElementById('app-confirm-title');
                const msgEl = document.getElementById('app-confirm-message');
                const okBtn = document.getElementById('app-confirm-ok');
                const cancelBtn = document.getElementById('app-confirm-cancel');

                titleEl.textContent = options.title || 'Confirm';
                msgEl.textContent = message;
                okBtn.textContent = options.confirmText || 'Confirm';
                cancelBtn.textContent = options.cancelText || 'Cancel';
                okBtn.className = 'btn ' + (options.confirmClass || 'btn-primary');

                const finish = (result) => {
                    okBtn.onclick = null;
                    cancelBtn.onclick = null;
                    modal.close();
                    resolve(result);
                };

                okBtn.onclick = () => finish(true);
                cancelBtn.onclick = () => finish(false);
                modal.addEventListener('close', function onClose() {
                    modal.removeEventListener('close', onClose);
                    if (okBtn.onclick) {
                        okBtn.onclick = null;
                        cancelBtn.onclick = null;
                        resolve(false);
                    }
                });
                modal.showModal();
            });
        };

        window.appAlert = function(message, options = {}) {
            return new Promise((resolve) => {
                const modal = document.getElementById('app-alert-modal');
                const titleEl = document.getElementById('app-alert-title');
                const msgEl = document.getElementById('app-alert-message');
                const okBtn = document.getElementById('app-alert-ok');

                titleEl.textContent = options.title || 'Notice';
                msgEl.textContent = message;
                okBtn.textContent = options.okText || 'OK';
                okBtn.className = 'btn ' + (options.okClass || 'btn-primary');

                const finish = () => {
                    okBtn.onclick = null;
                    modal.close();
                    resolve();
                };
                okBtn.onclick = finish;
                modal.addEventListener('close', function onClose() {
                    modal.removeEventListener('close', onClose);
                    if (okBtn.onclick) {
                        okBtn.onclick = null;
                        resolve();
                    }
                });
                modal.showModal();
            });
        };

        document.addEventListener('submit', async function(e) {
            const form = e.target;
            if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) {
                return;
            }
            if (form.dataset.confirmAccepted === '1') {
                delete form.dataset.confirmAccepted;
                return;
            }
            e.preventDefault();
            const ok = await window.appConfirm(form.dataset.confirm, {
                title: form.dataset.confirmTitle || 'Confirm',
                confirmText: form.dataset.confirmText || 'Confirm',
                cancelText: form.dataset.confirmCancel || 'Cancel',
                confirmClass: form.dataset.confirmClass || 'btn-primary',
            });
            if (ok) {
                form.dataset.confirmAccepted = '1';
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            }
        }, true);
    </script>
</body>
</html>
