<?php

/** @var string $appName */
/** @var string $favicon */
/** @var string $locale */
/** @var bool $isLoggedIn */
/** @var string|null $clubEmail */
/** @var bool $aboutActive */
/** @var bool $eventsActive */
/** @var string $clubUrl */
/** @var bool $clubsActive */
/** @var bool $adminActive */
/** @var list<array{paths: list<string>, query?: array<string, list<string>>, method?: 'post', url: string, label: string}> $submenuItems */
/** @var string $currentPath */
/** @var string $clubView */
/** @var string $content */
/** @var string $privacyControllerEmail */
/** @var string $privacyControllerFiscalCode */
?>
<!doctype html>
<html lang="<?= e(\App\Localization::getLocale()) ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <?php $pageTitle = trim((string) ($title ?? '')); ?>
    <title><?= e($pageTitle !== '' && $pageTitle !== $appName ? $pageTitle . ' | ' . $appName : $appName) ?></title>

    <link rel="icon" href="<?= $favicon ?>">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
    <script>
        (function() {
            try {
                if (window.localStorage.getItem('competizioni-judo-theme') === 'dark') {
                    document.documentElement.dataset.theme = 'dark';
                }
            } catch (error) {
                // Keep the default light theme when browser storage is unavailable.
            }
        }());
    </script>

    <style>
        .next-events-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .next-event-item {
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
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
        <div class="left-panel">
            <a href="https://www.csen.it/" target="_blank" rel="noopener noreferrer" class="club-link" title="CSEN">
                <img src="<?= e(asset_url('assets/logo-csen.svg')) ?>" alt="CSEN">
            </a>
            <a href="https://www.fijlkam.it/" target="_blank" rel="noopener noreferrer" class="club-link" title="FIJLKAM">
                <img src="<?= e(asset_url('assets/logo-fijlkam-judo.svg')) ?>" alt="FIJLKAM">
            </a>
        </div>
        <div class="main-title">
            <div class="main-title-heading">
                <a href="<?= e(base_url('/')) ?>" class="site-logo-link" aria-label="<?= e(__('nav.about')) ?>">
                    <img
                        class="site-heading-logo"
                        src="<?= e(asset_url('assets/competizioni-judo-logo-optim.svgz')) ?>"
                        alt="<?= e(__('app.logo_alt')) ?>">
                </a>
                <h1><?= translate('header.title') ?></h1>
            </div>
            <p><?= translate('header.subtitle') ?></p>
        </div>
        <div class="right-panel">
            <div class="header-controls">
                <form class="lang-switch" action="<?= e(base_url('/language/switch')) ?>" method="get" aria-label="<?= e(translate('a11y.language_selector')) ?>">
                    <label for="locale-select" class="sr-only"><?= e(translate('a11y.language_selector')) ?></label>
                    <select id="locale-select" name="locale" onchange="this.form.submit()">
                        <option value="it" <?= $locale === 'it' ? 'selected' : '' ?>>🇮🇹 Italiano</option>
                        <option value="en" <?= $locale === 'en' ? 'selected' : '' ?>>🇬🇧 English</option>
                    </select>
                </form>
                <button
                    type="button"
                    class="theme-toggle"
                    id="theme-toggle"
                    aria-pressed="false"
                    aria-label="<?= e(__('a11y.use_dark_theme')) ?>"
                    data-light-action="<?= e(__('a11y.use_light_theme')) ?>"
                    data-dark-action="<?= e(__('a11y.use_dark_theme')) ?>"
                    title="<?= e(__('a11y.use_dark_theme')) ?>">
                    <svg
                        class="theme-toggle-icon"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                        focusable="false">
                        <g data-theme-icon="light">
                            <circle cx="12" cy="12" r="3.6" />
                            <path d="M12 2.4v2.1m0 15v2.1M2.4 12h2.1m15 0h2.1M5.2 5.2l1.5 1.5m10.6 10.6 1.5 1.5m0-13.6-1.5 1.5M6.7 17.3l-1.5 1.5" />
                        </g>
                        <g data-theme-icon="dark">
                            <path d="M20.3 15.4A8.2 8.2 0 0 1 8.6 3.7a8.5 8.5 0 1 0 11.7 11.7Z" />
                            <path d="m17.7 3.2.35.86.85.35-.85.35-.35.86-.35-.86-.85-.35.85-.35.35-.86Z" fill="currentColor" stroke="none" />
                            <circle cx="20.2" cy="7.4" r=".65" fill="currentColor" stroke="none" />
                        </g>
                    </svg>
                </button>
            </div>
            <?php if ($isLoggedIn) : ?>
                <div class="club-login-info">
                    <span><?= e($clubEmail) ?></span>
                    <form method="post" action="<?= e(base_url('/clubs/logout')) ?>" class="logout-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="logout-link"><?= translate('club.area.submenu.logout') ?></button>
                    </form>
                </div>
            <?php else : ?>
                <div class="club-login-info">
                    <a href="<?= e(base_url('/clubs/login')) ?>"><?= translate('nav.login') ?></a> | <a href="<?= e(base_url('/clubs/register')) ?>"><?= translate('nav.register') ?></a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <nav class="main-nav" aria-label="<?= e(translate('a11y.main_navigation')) ?>">
        <a href="<?= e(base_url('/events')) ?>" class="<?= $eventsActive ? 'active' : '' ?>"><?= translate('nav.events') ?></a>
        <a href="<?= e($clubUrl) ?>" class="<?= $clubsActive ? 'active' : '' ?>"><?= translate('nav.clubs') ?></a>
        <a href="<?= e(base_url('/about')) ?>" class="<?= $aboutActive ? 'active' : '' ?>"><?= translate('nav.about') ?></a>
        <a href="<?= e(base_url('/admin/events')) ?>" class="<?= $adminActive ? 'active' : '' ?>"><?= translate('nav.admin') ?></a>
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
            <?php if ($privacyControllerEmail !== '') : ?>
                <?php $email = e((string) $privacyControllerEmail) ?>
                <br><a href="mailto:<?= $email ?>"><?= $email ?></a>
            <?php endif; ?>
            <?php if ($privacyControllerFiscalCode !== '') : ?>
                <br><?= e(__('privacy.fiscal_code')) ?>: <?= e((string) $privacyControllerFiscalCode) ?>
            <?php endif; ?>
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
            <a href="<?= e(base_url('/privacy')) ?>"><?= e(__('privacy.footer_link')) ?></a>
        </div>
    </footer>

    <script>
        (function() {
            const toggle = document.getElementById('theme-toggle');
            if (!toggle) {
                return;
            }

            function setTheme(theme, persist) {
                const isDark = theme === 'dark';
                document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
                toggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
                const action = isDark ? toggle.dataset.lightAction : toggle.dataset.darkAction;
                toggle.setAttribute('aria-label', action);
                toggle.setAttribute('title', action);

                if (!persist) {
                    return;
                }

                try {
                    window.localStorage.setItem('competizioni-judo-theme', isDark ? 'dark' : 'light');
                } catch (error) {
                    // The selected theme remains active for the current page.
                }
            }

            setTheme(document.documentElement.dataset.theme, false);
            toggle.addEventListener('click', function() {
                setTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark', true);
            });
        }());

        (function() {
            const wrappers = [...document.querySelectorAll('.table-scroll--responsive')];
            const storageKey = 'competizioni-judo-table-view';
            const labels = {
                cards: <?= json_encode(__('tables.show_cards'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>,
                table: <?= json_encode(__('tables.show_table'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>
            };
            let preferredView = 'table';

            try {
                preferredView = window.localStorage.getItem(storageKey) === 'cards' ? 'cards' : 'table';
            } catch (error) {
                // Use the default table view when browser storage is unavailable.
            }

            function applyView(wrapper, button, icon, label, view) {
                const cardView = view === 'cards';
                wrapper.classList.toggle('is-card-view', cardView);
                button.setAttribute('aria-pressed', cardView ? 'true' : 'false');
                button.setAttribute('aria-label', cardView ? labels.table : labels.cards);
                button.setAttribute('title', cardView ? labels.table : labels.cards);
                icon.textContent = cardView ? '▦' : '▤';
                label.textContent = cardView ? labels.table : labels.cards;
            }

            wrappers.forEach((wrapper) => {
                const toolbar = document.createElement('div');
                const button = document.createElement('button');
                const icon = document.createElement('span');
                const label = document.createElement('span');

                toolbar.className = 'table-view-toolbar';
                button.className = 'table-view-toggle';
                button.type = 'button';
                icon.setAttribute('aria-hidden', 'true');
                button.append(icon, label);
                toolbar.append(button);
                wrapper.prepend(toolbar);
                applyView(wrapper, button, icon, label, preferredView);

                button.addEventListener('click', () => {
                    preferredView = wrapper.classList.contains('is-card-view') ? 'table' : 'cards';
                    wrappers.forEach((candidate) => {
                        const candidateButton = candidate.querySelector('.table-view-toggle');
                        if (candidateButton === null) {
                            return;
                        }
                        const children = candidateButton.querySelectorAll('span');
                        applyView(candidate, candidateButton, children[0], children[1], preferredView);
                    });
                    try {
                        window.localStorage.setItem(storageKey, preferredView);
                    } catch (error) {
                        // Keep the preference for this page only.
                    }
                });
            });

            function closeEditor(row, reset) {
                if (reset) {
                    row.querySelector('form.inline-edit-actions')?.reset();
                }
                row.classList.remove('is-inline-editing');
            }

            document.addEventListener('click', (event) => {
                const editButton = event.target.closest('[data-inline-edit]');
                if (editButton !== null) {
                    const row = editButton.closest('[data-inline-edit-row]');
                    if (row === null) {
                        return;
                    }
                    document.querySelectorAll('[data-inline-edit-row].is-inline-editing').forEach((openRow) => {
                        if (openRow !== row) {
                            closeEditor(openRow, true);
                        }
                    });
                    row.classList.add('is-inline-editing');
                    const firstControl = row.querySelector(
                        'input[data-inline-editor], select[data-inline-editor], textarea[data-inline-editor], ' +
                        '[data-inline-editor] input, [data-inline-editor] select, [data-inline-editor] textarea'
                    );
                    firstControl?.focus();
                    return;
                }

                const cancelButton = event.target.closest('[data-inline-cancel]');
                if (cancelButton !== null) {
                    const row = cancelButton.closest('[data-inline-edit-row]');
                    if (row !== null) {
                        closeEditor(row, true);
                        row.querySelector('[data-inline-edit]')?.focus();
                    }
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') {
                    return;
                }
                const row = event.target.closest('[data-inline-edit-row].is-inline-editing');
                if (row !== null) {
                    closeEditor(row, true);
                    row.querySelector('[data-inline-edit]')?.focus();
                }
            });
        }());
    </script>

</body>

</html>