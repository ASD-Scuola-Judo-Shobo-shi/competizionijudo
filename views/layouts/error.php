<?php

/** @var string $favicon */
/** @var string $content */
?>

<!doctype html>
<html lang="<?= e(\App\Localization::getLocale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= e($title ?? __('errors.server_error')) ?></title>
    <link rel="icon" href="<?= $favicon ?>">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
    <script>
        (function () {
            try {
                if (window.localStorage.getItem('competizioni-judo-theme') === 'dark') {
                    document.documentElement.dataset.theme = 'dark';
                }
            } catch (error) {
                // Keep the default light theme when browser storage is unavailable.
            }
        }());
    </script>
</head>
<body class="error-layout">
    <main class="error-shell" id="main-content">
        <?= $content ?>
    </main>
</body>
</html>
