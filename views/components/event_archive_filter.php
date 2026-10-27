<?php

declare(strict_types=1);

/** @var string|null $archiveListPath */
/** @var bool|null $showArchived */
$archiveListPath ??= '/admin/events';
$showArchived ??= false;
?>
<nav aria-label="<?= e(__('event_archive.filter')) ?>">
    <a class="btn <?= !$showArchived ? 'green' : 'gray' ?>" href="<?= e(base_url($archiveListPath)) ?>" <?= !$showArchived ? 'aria-current="page"' : '' ?>><?= e(__('event_archive.active')) ?></a>
    <a class="btn <?= $showArchived ? 'green' : 'gray' ?>" href="<?= e(base_url($archiveListPath . '?archived=1')) ?>" <?= $showArchived ? 'aria-current="page"' : '' ?>><?= e(__('event_archive.archived')) ?></a>
</nav>
