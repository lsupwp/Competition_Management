<?php
// Main layout template
// Usage: include __DIR__ . '/../templates/layout.php';
?>
<!DOCTYPE html>
<html lang="th" data-theme="<?= $theme ?? 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Team Competition' ?></title>

    <!-- Tailwind CSS v4 (Play CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <!-- DaisyUI v5 -->
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5/themes.css" rel="stylesheet" type="text/css" />

    <style type="text/tailwindcss">
        @theme {
            --color-primary: oklch(0.7 0.15 198);
            --color-primary-focus: oklch(0.6 0.18 198);
            --color-primary-content: oklch(0.98 0 0);
        }
    </style>
</head>
<body class="min-h-screen bg-base-200 flex flex-col">
    <?php include_once __DIR__ . '/header.php'; ?>

    <main class="container mx-auto p-4 flex-1">
        <?= $content ?? '' ?>
    </main>

    <?php include_once __DIR__ . '/footer.php'; ?>

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
    </script>
</body>
</html>
