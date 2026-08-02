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

        (function() {
            const labels = {
                ascending: <?= json_encode(__('tables.sort_ascending'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>,
                descending: <?= json_encode(__('tables.sort_descending'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>
            };
            const collator = new Intl.Collator(document.documentElement.lang, {
                numeric: true,
                sensitivity: 'base'
            });

            function actionLabel(template, column) {
                return template.replace('{column}', column);
            }

            function cellValue(cell) {
                if (cell.dataset.sortValue !== undefined) {
                    return cell.dataset.sortValue.trim();
                }

                const display = cell.querySelector('[data-inline-display]') ?? cell;
                const denseValue = display.querySelector('.table-density-value');
                if (denseValue !== null) {
                    return denseValue.getAttribute('aria-label')
                        ?? denseValue.getAttribute('title')
                        ?? denseValue.textContent?.trim()
                        ?? '';
                }
                const time = display.querySelector('time[datetime]');
                if (time !== null) {
                    return time.getAttribute('datetime') ?? '';
                }

                return (display.textContent ?? '').replace(/\s+/g, ' ').trim();
            }

            function numberValue(value) {
                const normalized = value
                    .replace(/\s+/g, '')
                    .replace(',', '.')
                    .replace(/^€/, '')
                    .replace(/^#/, '')
                    .replace(/(?:kg|%)$/i, '');

                return /^[+-]?\d+(?:\.\d+)?$/.test(normalized)
                    ? Number.parseFloat(normalized)
                    : null;
            }

            document.querySelectorAll('table:not(.event-info-table)').forEach((table) => {
                const body = table.tBodies.item(0);
                const headers = [...table.querySelectorAll('thead th')];
                if (body === null || headers.length === 0) {
                    return;
                }

                const serverMode = table.dataset.sortMode === 'server';
                const sortParameter = table.dataset.sortParameter ?? 'sort';
                const directionParameter = table.dataset.sortDirectionParameter ?? 'direction';
                const pageParameter = table.dataset.sortPageParameter ?? 'page';
                const defaultSort = table.dataset.sortDefault ?? '';
                const defaultDirection = table.dataset.sortDefaultDirection === 'desc' ? 'desc' : 'asc';
                const currentUrl = new URL(window.location.href);
                const requestedSort = currentUrl.searchParams.get(sortParameter) ?? defaultSort;
                const availableSorts = headers.map((header) => header.dataset.sortKey ?? '');
                const currentSort = availableSorts.includes(requestedSort) ? requestedSort : defaultSort;
                const requestedDirection = currentUrl.searchParams.get(directionParameter);
                const currentDirection = requestedDirection === 'asc' || requestedDirection === 'desc'
                    ? requestedDirection
                    : defaultDirection;
                let activeColumn = -1;
                let ascending = true;
                headers.forEach((header, column) => {
                    if (header.dataset.sortable === 'false') {
                        return;
                    }
                    const sortKey = header.dataset.sortKey ?? '';
                    if (serverMode && sortKey === '') {
                        return;
                    }
                    table.classList.add('table-is-sortable');

                    const visibleColumnLabel = (header.textContent ?? '').replace(/\s+/g, ' ').trim();
                    const columnLabel = header.getAttribute('title')?.trim() || visibleColumnLabel;
                    const control = document.createElement(serverMode ? 'a' : 'button');
                    const indicator = document.createElement('span');
                    if (control instanceof HTMLButtonElement) {
                        control.type = 'button';
                    }
                    control.className = 'table-sort-button';
                    control.dataset.sortLabel = columnLabel;
                    indicator.className = 'table-sort-indicator';
                    indicator.textContent = '↕';
                    indicator.setAttribute('aria-hidden', 'true');
                    control.append(...header.childNodes, indicator);
                    header.append(control);
                    header.setAttribute('aria-sort', 'none');

                    if (serverMode && control instanceof HTMLAnchorElement) {
                        const isActive = sortKey === currentSort;
                        const nextDirection = isActive && currentDirection === 'asc' ? 'desc' : 'asc';
                        const target = new URL(currentUrl);
                        target.searchParams.set(sortParameter, sortKey);
                        target.searchParams.set(directionParameter, nextDirection);
                        target.searchParams.delete(pageParameter);
                        control.href = target.pathname + target.search + target.hash;
                        const nextAction = nextDirection === 'asc'
                            ? labels.ascending
                            : labels.descending;
                        const nextLabel = actionLabel(nextAction, columnLabel);
                        control.setAttribute('aria-label', nextLabel);
                        control.title = nextLabel;
                        header.setAttribute(
                            'aria-sort',
                            isActive ? (currentDirection === 'asc' ? 'ascending' : 'descending') : 'none'
                        );
                        indicator.textContent = isActive
                            ? (currentDirection === 'asc' ? '▲' : '▼')
                            : '↕';
                        return;
                    }

                    control.setAttribute('aria-label', actionLabel(labels.ascending, columnLabel));
                    control.title = actionLabel(labels.ascending, columnLabel);

                    control.addEventListener('click', () => {
                        ascending = activeColumn !== column || !ascending;
                        activeColumn = column;
                        const rows = [...body.rows];
                        const sortableRows = rows.filter((row) => (
                            row.cells.length > column
                            && row.querySelector('.responsive-table__empty') === null
                        ));
                        const fixedRows = rows.filter((row) => !sortableRows.includes(row));

                        sortableRows.sort((leftRow, rightRow) => {
                            const left = cellValue(leftRow.cells[column]);
                            const right = cellValue(rightRow.cells[column]);
                            if (left === '' && right !== '') {
                                return 1;
                            }
                            if (right === '' && left !== '') {
                                return -1;
                            }

                            const leftNumber = numberValue(left);
                            const rightNumber = numberValue(right);
                            const comparison = leftNumber !== null && rightNumber !== null
                                ? leftNumber - rightNumber
                                : collator.compare(left, right);

                            return ascending ? comparison : -comparison;
                        });
                        body.append(...sortableRows, ...fixedRows);

                        headers.forEach((candidate, candidateColumn) => {
                            const candidateButton = candidate.querySelector('.table-sort-button');
                            if (candidateButton === null) {
                                return;
                            }
                            const candidateIndicator = candidate.querySelector('.table-sort-indicator');
                            const candidateLabel = candidateButton.dataset.sortLabel ?? '';
                            const isActive = candidateColumn === activeColumn;
                            candidate.setAttribute(
                                'aria-sort',
                                isActive ? (ascending ? 'ascending' : 'descending') : 'none'
                            );
                            if (candidateIndicator !== null) {
                                candidateIndicator.textContent = isActive
                                    ? (ascending ? '▲' : '▼')
                                    : '↕';
                            }
                            const nextAction = isActive && ascending
                                ? labels.descending
                                : labels.ascending;
                            const nextLabel = actionLabel(nextAction, candidateLabel);
                            candidateButton.setAttribute('aria-label', nextLabel);
                            candidateButton.title = nextLabel;
                        });

                        table.dispatchEvent(new CustomEvent('table:sorted'));
                    });
                });
            });
        }());

        (function() {
            const labels = {
                nav: <?= json_encode(__('pagination.label'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>,
                first: <?= json_encode(__('pagination.first'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>,
                prev: <?= json_encode(__('pagination.prev'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>,
                next: <?= json_encode(__('pagination.next'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>,
                last: <?= json_encode(__('pagination.last'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>,
                firstPage: <?= json_encode(__('pagination.first_page'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>,
                previousPage: <?= json_encode(__('pagination.previous_page'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>,
                previousFivePages: <?= json_encode(__('pagination.previous_five_pages'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>,
                nextFivePages: <?= json_encode(__('pagination.next_five_pages'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>,
                nextPage: <?= json_encode(__('pagination.next_page'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>,
                lastPage: <?= json_encode(__('pagination.last_page'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>,
                pageNumber: <?= json_encode(__('pagination.page_number'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>
            };

            function pageLabel(page) {
                return labels.pageNumber.replace('{page}', String(page));
            }

            document.querySelectorAll('table[data-client-pagination]').forEach((table) => {
                const body = table.tBodies.item(0);
                let rows = body === null ? [] : [...body.rows];
                const requestedPageSize = Number.parseInt(table.dataset.clientPagination ?? '', 10);
                const pageSize = Number.isFinite(requestedPageSize) && requestedPageSize > 0
                    ? requestedPageSize
                    : 50;
                const lastPage = Math.ceil(rows.length / pageSize);
                if (lastPage <= 1) {
                    return;
                }

                const nav = document.createElement('nav');
                const wrapper = table.closest('.table-scroll');
                let page = 1;
                nav.className = 'pagination';
                nav.setAttribute('role', 'navigation');
                nav.setAttribute('aria-label', wrapper?.getAttribute('aria-label') || labels.nav);
                (wrapper ?? table).insertAdjacentElement('afterend', nav);

                function button(text, ariaLabel, targetPage, disabled, active = false) {
                    const control = document.createElement('button');
                    control.type = 'button';
                    control.className = 'pagination-link';
                    control.textContent = text;
                    control.setAttribute('aria-label', ariaLabel);
                    control.disabled = disabled;
                    control.classList.toggle('disabled', disabled);
                    control.classList.toggle('active', active);
                    if (active) {
                        control.setAttribute('aria-current', 'page');
                    }
                    control.addEventListener('click', () => {
                        page = Math.max(1, Math.min(targetPage, lastPage));
                        render();
                    });

                    return control;
                }

                function render() {
                    const firstRow = (page - 1) * pageSize;
                    rows.forEach((row, index) => {
                        row.hidden = index < firstRow || index >= firstRow + pageSize;
                    });

                    const atFirstPage = page === 1;
                    const atLastPage = page === lastPage;
                    const controls = [
                        button('«« ' + labels.first, labels.firstPage, 1, atFirstPage),
                        button('−5', labels.previousFivePages, page - 5, atFirstPage),
                        button('‹ ' + labels.prev, labels.previousPage, page - 1, atFirstPage)
                    ];
                    const firstPage = Math.max(1, page - 2);
                    const finalPage = Math.min(lastPage, page + 2);
                    for (let candidate = firstPage; candidate <= finalPage; candidate += 1) {
                        controls.push(button(
                            String(candidate),
                            pageLabel(candidate),
                            candidate,
                            false,
                            candidate === page
                        ));
                    }
                    controls.push(button(
                        labels.next + ' ›',
                        labels.nextPage,
                        page + 1,
                        atLastPage
                    ));
                    controls.push(button(
                        '+5',
                        labels.nextFivePages,
                        page + 5,
                        atLastPage
                    ));
                    controls.push(button(
                        labels.last + ' »»',
                        labels.lastPage,
                        lastPage,
                        atLastPage
                    ));
                    nav.replaceChildren(...controls);
                }

                table.addEventListener('table:sorted', () => {
                    rows = body === null ? [] : [...body.rows];
                    page = 1;
                    render();
                });
                render();
            });
        }());
    </script>

</body>

</html>
