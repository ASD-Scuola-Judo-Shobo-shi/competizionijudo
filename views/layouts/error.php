<!doctype html>
<html lang="<?= e(\App\Localization::getLocale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? __('errors.server_error')) ?></title>
    <link rel="icon" href="<?= $favicon ?>">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="error-layout">
    <main class="error-shell" id="main-content">
        <?= $content ?>
    </main>
</body>
</html>
