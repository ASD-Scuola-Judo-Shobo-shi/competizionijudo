<?php

/** @var \App\Model\Event $event */
/** @var list<array<string, mixed>> $outliers */
/** @var array{page:int, per_page:int, total:int, last_page:int, offset:int, links:string} $pagination */
?>
<section class="card">
    <h2><?= e(__('entry_review.outliers.title')) ?> — <?= e($event->name) ?></h2>
    <p><?= e(__('entry_review.outliers.help')) ?></p>
    <p><?= e(__('entry_review.outliers.count', ['count' => (string) $pagination['total']])) ?></p>
    <a class="btn" href="<?= e(base_url('/admin/events/details?event_id=' . $event->id)) ?>"><?= e(__('entry_review.back')) ?></a>
    <?php if ($outliers === []) :
        ?><p><?= e(__('entry_review.outliers.none')) ?></p><?php
    endif; ?>
    <?php foreach ($outliers as $entry) : ?>
        <article>
            <h3>
                <a href="<?= e(base_url('/admin/events/entry-data?event_id=' . $event->id . '&entry_id=' . (int) $entry['entry_id'])) ?>">
                    <?= e(trim((string) $entry['last_name'] . ' ' . (string) $entry['first_name'])) ?>
                    — <?= e((string) $entry['club_name']) ?>
                </a>
            </h3>
            <p><?= e((string) ($entry['weight_kg'] ?? '—')) ?> kg — <?= e((string) ($entry['belt'] ?? '—')) ?></p>
            <ul>
                <?php foreach ($entry['review_reasons'] as $reason) :
                    ?><li><?= e($reason) ?></li><?php
                endforeach; ?>
            </ul>
        </article>
    <?php endforeach; ?>
    <?= $pagination['links'] ?>
</section>
