<?php

/** @var \App\Model\Event $event */
/** @var list<array<string, mixed>> $reviews */
?>
<section class="card">
    <h2><?= e(__('entry_review.pending_title')) ?> — <?= e($event->name) ?></h2>
    <a class="btn" href="<?= e(base_url('/admin/events/details?event_id=' . $event->id)) ?>"><?= e(__('entry_review.back')) ?></a>
    <?php if ($reviews === []) :
        ?><p><?= e(__('entry_review.no_pending')) ?></p><?php
    endif; ?>
    <?php foreach ($reviews as $review) : ?>
        <p>
            <a href="<?= e(base_url('/admin/events/entry-data?event_id=' . $event->id . '&entry_id=' . (int) $review['entry_id'])) ?>">
                <?= e((string) $review['club_name']) ?> — <?= e((string) $review['requested_at']) ?> UTC
            </a>
            <?= e((string) $review['reason']) ?>
        </p>
    <?php endforeach; ?>
</section>
