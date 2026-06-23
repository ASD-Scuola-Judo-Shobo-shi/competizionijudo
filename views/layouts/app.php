<!doctype html>
<html lang="<?= e(\App\Localization::getLocale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? 'Portale Gare Judo') . ' | ' . config('app.name')) ?></title>

    <link rel="stylesheet" href="/assets/css/app.css">

    <style>
        .cookie-banner {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: #061b3a;
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.3);
            z-index: 1000;
            font-family: Arial, sans-serif;
            box-sizing: border-box;
        }
        .cookie-banner p {
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.4;
            padding-right: 20px;
        }
        .cookie-banner button {
            background: #c80022;
            color: white;
            border: none;
            padding: 8px 20px;
            cursor: pointer;
            font-weight: bold;
            border-radius: 4px;
            white-space: nowrap;
            transition: background 0.2s;
        }
        .cookie-banner button:hover {
            background: #a0001a;
        }
        .cookie-hidden {
            display: none !important;
        }
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const banner = document.getElementById('cookie-banner');
            if (!localStorage.getItem('cookie-consent')) {
                banner.classList.remove('cookie-hidden');
            }
        });

        function acceptCookies() {
            localStorage.setItem('cookie-consent', 'true');
            document.getElementById('cookie-banner').classList.add('cookie-hidden');
        }

    </script>
</head>
<body>
<a href="#main-content" class="skip-link"><?= e(translate('a11y.skip_to_content')) ?></a>

<div id="cookie-banner" class="cookie-banner cookie-hidden" role="dialog" aria-labelledby="cookie-message" aria-modal="true">
    <p id="cookie-message"><?= translate('cookies.message') ?></p>
    <button type="button" onclick="acceptCookies()"><?= translate('cookies.accept') ?></button>
</div>

<?php
$current = (string)($currentPath ?? '/');
$clubView = htmlspecialchars((string) ($_GET['view'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$isLoggedIn = \App\Core\Session::has('club_id');
$clubEmail = null;
if ($isLoggedIn) {
    $club = \App\Model\Club::findById((int) \App\Core\Session::get('club_id'));
    if ($club !== null) {
        $clubEmail = $club->email;
    }
}

$isAdmin = !empty(\App\Core\Session::get('is_admin'));
$submenuItems = build_submenu($current, $isAdmin, $isLoggedIn);

$competitionPaths = ['/events.php', '/event_details.php', '/event_register.php'];
$clubPaths = ['/club_register.php', '/club_login.php', '/club_forgot_password.php', '/club_reset_password.php', '/club_area.php', '/clubs.php'];
$adminPaths = ['/admin_login.php', '/admin.php', '/admin_manage_clubs.php', '/admin_manage_events.php', '/admin_add_event.php', '/admin_edit_club.php', '/admin_edit_event.php', '/admin_logout.php'];
?>

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
            <option value="it" <?= \App\Localization::getLocale() === 'it' ? 'selected' : '' ?>>🇮🇹 Italiano</option>
            <option value="en" <?= \App\Localization::getLocale() === 'en' ? 'selected' : '' ?>>🇬🇧 English</option>
        </select>
    </form>
    <?php if ($isLoggedIn) : ?>
        <div class="club-login-info"><span><?= e($clubEmail) ?></span> <a href="/club_logout.php"><?= translate('club.area.submenu.logout') ?></a></div>
    <?php else : ?>
        <div class="club-login-info">
            <a href="/club_login.php"><?= translate('nav.login') ?></a> | <a href="/club_register.php"><?= translate('nav.register') ?></a>
        </div>
    <?php endif; ?>
</header>

<nav class="main-nav" aria-label="<?= e(translate('a11y.main_navigation')) ?>">
    <a href="/" class="<?= in_array($current, ['/', '/index.php'], true) ? 'active' : '' ?>"><?= translate('nav.home') ?></a>
    <a href="/events.php" class="<?= in_array($current, $competitionPaths, true) ? 'active' : '' ?>"><?= translate('nav.competitions') ?></a>
    <a href="<?= $isLoggedIn ? '/club_area.php' : '/club_login.php' ?>" class="<?= in_array($current, $clubPaths, true) ? 'active' : '' ?>"><?= translate('nav.clubs') ?></a>
    <a href="/admin_manage_events.php" class="<?= in_array($current, $adminPaths, true) ? 'active' : '' ?>"><?= translate('nav.admin') ?></a>
</nav>

<?php if ($submenuItems) : ?>
<div class="submenu-wrap" aria-label="<?= e(translate('a11y.submenu')) ?>">
        <div class="submenu" role="navigation">
        <?php foreach ($submenuItems as $item) : ?>
            <?php
            $active = in_array($current, $item['paths'], true);
            if ($active && !empty($item['query']['view'])) {
                $active = in_array($clubView, $item['query']['view'], true);
            }
            ?>
            <a href="<?= e($item['url']) ?>" class="submenu-item<?= $active ? ' submenu-item--active' : '' ?>"><?= e($item['label']) ?></a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<main class="page-shell" id="main-content">
    <?= $content ?>
</main>

<footer class="site-footer" role="contentinfo">
    <div>
        <strong><?= translate('footer.brand') ?></strong><br>
        <?= translate('footer.description') ?>
    </div>
    <div class="footer-links">
        <a href="https://www.csen.it/" target="_blank" rel="noopener noreferrer">CSEN</a>
        <span class="footer-sep">•</span>
        <a href="https://www.fijlkam.it/" target="_blank" rel="noopener noreferrer">FIJLKAM</a>
        <span class="footer-sep">•</span>
        <a href="https://www.ijf.org/" target="_blank" rel="noopener noreferrer">IJF</a>
        <span class="footer-sep">•</span>
        <a href="https://it.m.wikipedia.org/wiki/Judo_(sport)" target="_blank" rel="noopener noreferrer">Judo</a>
    </div>
</footer>

<?= \App\Model\Database::renderProfiler() ?>

</body>
</html>