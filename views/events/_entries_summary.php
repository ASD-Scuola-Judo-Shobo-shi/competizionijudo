<?php

/** @var \App\Model\Event $event */
/** @var \App\Presentation\EventEntriesViewModel $entryReport */
/** @var bool $canViewEntryBreakdowns */
/** @var bool $hasRegistrationException */
?>

<section class="card event-entries-summary">
    <?php if ($event->closed) : ?>
        <span class="badge badge-closed event-closed-badge event-closed-badge--corner">
            <?= e(__('events.closed')) ?>
        </span>
    <?php endif; ?>

    <h2><?= e(__('events.entries_title')) ?></h2>
    <h3><?= e($event->name) ?></h3>
    <p>
        <strong><?= e(__('events.date')) ?>:</strong> <?= e($event->date) ?><br>
        <strong><?= e(__('events.location')) ?>:</strong> <?= e($event->location) ?>
    </p>

    <?php if ($entryReport->dimensions !== []) : ?>
        <div class="recap-summary">
            <h4><?= e(__('events.entries_recap')) ?></h4>
            <p>
                <strong><?= e(__('events.entries_subscribed')) ?>:</strong>
                <?= e((string) count($entryReport->entries)) ?><br>
                <?php if ($event->max_participants !== null) : ?>
                    <strong><?= e(__('admin.events.max_participants')) ?>:</strong>
                    <?= e((string) $event->max_participants) ?><br>
                    <strong><?= e(__('events.entries_free_spots')) ?>:</strong>
                    <?= e((string) max(0, $event->max_participants - count($entryReport->entries))) ?>
                <?php endif; ?>
            </p>

            <?php if ($canViewEntryBreakdowns) : ?>
                <?php require __DIR__ . '/_entries_category_weight_chart.php'; ?>

                <div class="entries-chart-grid">
                    <?php foreach ($entryReport->dimensions as $dimension) : ?>
                        <?php require __DIR__ . '/_entries_chart.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="event-entries-summary__actions">
        <a class="btn" href="<?= e(base_url('/events/details?event=' . (string) $event->id)) ?>">
            <?= e(__('events.details')) ?>
        </a>
        <?php if (!$event->closed || $hasRegistrationException) : ?>
            <a class="btn green" href="<?= e(base_url('/events/register?event=' . (string) $event->id)) ?>">
                <?= e(__('events.registration')) ?>
            </a>
        <?php endif; ?>
    </div>
</section>
