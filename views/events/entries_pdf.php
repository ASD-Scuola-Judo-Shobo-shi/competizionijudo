<?php

declare(strict_types=1);

/** @var \App\Model\Event $event */
/** @var \App\Model\Club $club */
/** @var list<array<string, mixed>> $athletes */
/** @var string $generatedAt */
?>
<div class="document-heading">
    <p><?= e(__('events.entries_pdf_title')) ?></p>
    <h1><?= e($event->name) ?></h1>
    <p><strong><?= e(__('events.date')) ?>:</strong> <?= e($event->date) ?></p>
    <p><strong><?= e(__('events.location')) ?>:</strong> <?= e($event->location) ?></p>
</div>
<div class="document-meta">
    <h2><?= e($club->name) ?></h2>
    <p><strong><?= e(__('events.entries_code')) ?>:</strong> <?= e($club->federal_code) ?></p>
    <p class="document-total"><?= e(__('events.entries_pdf_total', ['count' => (string) count($athletes)])) ?></p>
    <p><?= e(__('events.entries_pdf_generated', ['date' => $generatedAt])) ?></p>
</div>
<?php if ($athletes === []) : ?>
    <p><?= e(__('club.area.no_entries')) ?></p>
<?php else : ?>
    <table>
        <thead>
            <tr>
                <th scope="col" class="column-number">#</th>
                <th scope="col" class="column-athlete"><?= e(__('club.area.table.athlete')) ?></th>
                <th scope="col" class="column-age"><?= e(__('club.area.table.age_class')) ?></th>
                <th scope="col" class="column-gender"><?= e(__('club.area.table.gender')) ?></th>
                <th scope="col" class="column-weight"><?= e(__('club.area.table.weight')) ?></th>
                <th scope="col" class="column-category"><?= e(__('club.area.table.weight_category')) ?></th>
                <th scope="col" class="column-belt"><?= e(__('club.area.table.belt')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($athletes as $index => $athlete) : ?>
                <tr>
                    <td><?= e((string) ($index + 1)) ?></td>
                    <td><?= e((string) $athlete['athlete_name']) ?></td>
                    <td><?= e((string) $athlete['age_category']) ?></td>
                    <td><?= e((string) $athlete['gender_label']) ?></td>
                    <td><?= e((string) $athlete['weight_display']) ?></td>
                    <td><?= e((string) $athlete['weight_label']) ?></td>
                    <td>
                        <?php
                        $beltBadge = \App\Model\Belt::tryFromValue((string) $athlete['belt']);
                        $beltBadgeFallback = (string) $athlete['belt_label'];
                        require dirname(__DIR__) . '/components/belt_badge.php';
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
