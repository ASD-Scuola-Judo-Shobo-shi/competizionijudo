<!doctype html>
<html lang="<?= e(\App\Localization::getLocale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? 'Portale Gare Judo') . ' | ' . $appName) ?></title>

    <link rel="stylesheet" href="/assets/css/app.css">

    <style>
        .next-events-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .next-event-item {
            border-bottom: 1px solid rgba(0,0,0,0.08);
            padding: 10px 0;
        }
        .next-event-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .next-event-item .location {
            color: #555;
            font-size: 0.95rem;
        }
        .next-event-details {
            margin-top: 8px;
            padding: 8px;
            background: #f7f7f7;
            border-radius: 4px;
        }
    </style>

</head>
<body>
<a href="#main-content" class="skip-link"><?= e(translate('a11y.skip_to_content')) ?></a>

<header class="top-hero">
    <div class="left-logos">
        <a href="https://www.csen.it/" target="_blank" rel="noopener noreferrer" class="club-link" title="CSEN">
            <img src="/assets/logo-csen.svg" alt="CSEN">
        </a>
        <a href="https://www.fijlkam.it/" target="_blank" rel="noopener noreferrer" class="club-link" title="FIJLKAM">
            <img src="/assets/logo-fijlkam-judo.svg" alt="FIJLKAM">
        </a>
    </div>
    <div class="main-title">
        <h1><?= translate('header.title') ?></h1>
        <p><?= translate('header.subtitle') ?></p>
    </div>
<form class="lang-switch" action="/language/switch" method="get" aria-label="<?= e(translate('a11y.language_selector')) ?>">
    <label for="locale-select" class="sr-only"><?= e(translate('a11y.language_selector')) ?></label>
    <select id="locale-select" name="locale" onchange="this.form.submit()">
            <option value="it" <?= $locale === 'it' ? 'selected' : '' ?>>🇮🇹 Italiano</option>
            <option value="en" <?= $locale === 'en' ? 'selected' : '' ?>>🇬🇧 English</option>
        </select>
    </form>
    <?php if ($isLoggedIn) : ?>
        <div class="club-login-info">
            <span><?= e($clubEmail) ?></span>
            <form method="post" action="/club_logout.php" class="logout-form">
                <?= csrf_field() ?>
                <button type="submit" class="logout-link"><?= translate('club.area.submenu.logout') ?></button>
            </form>
        </div>
    <?php else : ?>
        <div class="club-login-info">
            <a href="/club_login.php"><?= translate('nav.login') ?></a> | <a href="/club_register.php"><?= translate('nav.register') ?></a>
        </div>
    <?php endif; ?>
</header>

<nav class="main-nav" aria-label="<?= e(translate('a11y.main_navigation')) ?>">
    <a href="/" class="<?= $homeActive ? 'active' : '' ?>"><?= translate('nav.home') ?></a>
    <a href="/events.php" class="<?= $competitionsActive ? 'active' : '' ?>"><?= translate('nav.competitions') ?></a>
    <a href="<?= e($clubUrl) ?>" class="<?= $clubsActive ? 'active' : '' ?>"><?= translate('nav.clubs') ?></a>
    <a href="/admin_manage_events.php" class="<?= $adminActive ? 'active' : '' ?>"><?= translate('nav.admin') ?></a>
</nav>

<?php if ($submenuItems) : ?>
<div class="submenu-wrap" aria-label="<?= e(translate('a11y.submenu')) ?>">
        <div class="submenu" role="navigation">
        <?php foreach ($submenuItems as $item) : ?>
            <?php
            $active = in_array($currentPath, $item['paths'], true);
            if ($active && !empty($item['query']['view'])) {
                $active = in_array($clubView, $item['query']['view'], true);
            }
            ?>
            <?php if (($item['method'] ?? null) === 'post') : ?>
                <form method="post" action="<?= e($item['url']) ?>" class="logout-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="submenu-item<?= $active ? ' submenu-item--active' : '' ?>"><?= e($item['label']) ?></button>
                </form>
            <?php else : ?>
                <a href="<?= e($item['url']) ?>" class="submenu-item<?= $active ? ' submenu-item--active' : '' ?>"><?= e($item['label']) ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<main class="page-shell" id="main-content">
    <?= $content ?>
</main>

<footer class="site-footer" role="contentinfo">
    <div>
        <strong><?= e((string) ($privacyControllerName ?? '')) ?></strong><br>
        <?= e((string) ($privacyControllerAddress ?? '')) ?>
    </div>
    <div class="footer-links">
        <a href="https://www.csen.it/" target="_blank" rel="noopener noreferrer">CSEN</a>
        <span class="footer-sep">•</span>
        <a href="https://www.fijlkam.it/" target="_blank" rel="noopener noreferrer">FIJLKAM</a>
        <span class="footer-sep">•</span>
        <a href="https://www.ijf.org/" target="_blank" rel="noopener noreferrer">IJF</a>
        <span class="footer-sep">•</span>
        <a href="https://it.m.wikipedia.org/wiki/Judo_(sport)" target="_blank" rel="noopener noreferrer">Judo</a>
        <span class="footer-sep">•</span>
        <a href="/privacy"><?= e(__('privacy.footer_link')) ?></a>
    </div>
</footer>

</body>
</html>
