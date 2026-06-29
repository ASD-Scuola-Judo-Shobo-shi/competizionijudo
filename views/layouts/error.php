<!doctype html>
<html lang="<?= e(\App\Localization::getLocale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? __('errors.server_error')) ?></title>
</head>
<body>
    <main>
        <?= $content ?>
    </main>
</body>
</html>
