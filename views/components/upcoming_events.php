<?php

/** @var \App\Model\Event|null $event */
/** @var list<\App\Model\Event> $upcomingEvents */
/** @var array<int, bool> $eventExceptions */
/** @var 'details'|'entries'|'registration' $upcomingEventAction */
$actionConfig = match ($upcomingEventAction) {
    'details' => ['path' => '/events/details', 'label' => __('events.details'), 'class' => 'btn'],
    'registration' => ['path' => '/events/register', 'label' => __('events.registration'), 'class' => 'btn green'],
    default => ['path' => '/events/entries', 'label' => __('events.entries'), 'class' => 'btn'],
};
?>

<?php if ($event === null && $upcomingEvents === []) : ?>
    <section class="card">
        <p><?= e(__('events.none')) ?></p>
    </section>
<?php elseif ($upcomingEvents !== []) : ?>
    <section class="card upcoming-events-card">
        <?php if ($event === null) : ?>
            <p><?= e(__('events.select_event')) ?></p>
        <?php endif; ?>

        <h3><?= e($event !== null ? __('events.upcoming_events') : __('events.upcoming_heading')) ?></h3>

        <div class="upcoming-events-list">
            <?php foreach ($upcomingEvents as $next) : ?>
                <?php
                $hasException = !empty($eventExceptions[$next->id]);
                $showAction = $upcomingEventAction !== 'registration' || !$next->closed || $hasException;
                ?>
                <div class="event-line">
                    <span class="event-line__summary">
                        <?= e($next->date) ?> - <?= e($next->name) ?> - <?= e($next->location) ?>
                        - (<?= e(__('events.registration_deadline')) ?>: <?= e($next->registration_deadline) ?>)
                    </span>
                    <?php if ($next->closed) : ?>
                        <span class="badge badge-closed event-closed-badge"><?= e(__('events.closed')) ?></span>
                    <?php endif; ?>
                    <?php if ($showAction) : ?>
                        <a
                            class="<?= e($actionConfig['class']) ?> btn-sm event-details-btn"
                            href="<?= e(base_url($actionConfig['path'] . '?event=' . (string) $next->id)) ?>"
                        ><?= e($actionConfig['label']) ?></a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
