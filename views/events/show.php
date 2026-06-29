<?php
/** @var \App\Model\Event|null $event */
/** @var list<\App\Model\Event> $upcomingEvents */
/** @var list<\App\Model\Event> $nextEvents */
/** @var bool $canViewEntries */
?>
<?php
$eventsList = $event !== null ? $nextEvents : $upcomingEvents;
?>
<?php if ($event !== null) : ?>
<div class="card event-details-card">
    <div class="event-details-layout">
        <div class="event-details-poster">
            <?php if ($event->poster_file) : ?>
                <?php $ext = strtolower(pathinfo($event->poster_file, PATHINFO_EXTENSION)); ?>
                <?php if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) : ?>
                    <a href="/<?= e($event->poster_file) ?>" target="_blank">
                        <img src="/<?= e($event->poster_file) ?>" alt="<?= e(__('events.poster_alt', ['name' => $event->name])) ?>">
                    </a>
                <?php else : ?>
                    <div class="poster-pdf">
                        <strong><?= e(__('events.poster_pdf')) ?></strong><br>
                        <a class="btn orange" href="/<?= e($event->poster_file) ?>" target="_blank" download><?= e(__('events.download_poster')) ?></a>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <div class="poster-placeholder" style="background: linear-gradient(rgba(255, 255, 255, 0.5), rgba(255, 255, 255, 0.5)), url('/assets/judo-bg.jpg') center center / cover no-repeat;">
                    <span style="background: rgba(255,255,255,0.75); padding: 8px 14px; border-radius: 0.75em; display: inline-block;">
                        <strong><?= e(__('events.poster_not_available')) ?></strong>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <div class="event-details-info">
            <table class="event-info-table">
                <tr>
                    <td><strong><?= e(__('events.name')) ?>:</strong></td>
                    <td><?= e($event->name) ?></td>
                </tr>
                <tr>
                    <td><strong><?= e(__('events.date')) ?>:</strong></td>
                    <td><?= e($event->date) ?></td>
                </tr>
                <tr>
                    <td><strong><?= e(__('events.location')) ?>:</strong></td>
                    <td><?= e($event->location) ?></td>
                </tr>
                <?php if ($event->organizer) : ?>
                <tr>
                    <td><strong><?= e(__('admin.add.organizer')) ?>:</strong></td>
                    <td><?= e($event->organizer) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><strong><?= e(__('admin.events.type')) ?>:</strong></td>
                    <td><?= e(__('events.type.' . $event->type)) ?></td>
                </tr>
                <tr>
                    <td><strong><?= e(__('events.registration_deadline')) ?>:</strong></td>
                    <td><?= e($event->registration_deadline) ?></td>
                </tr>
            </table>
            <?php if ($event->description) : ?>
            <div class="event-description">
                <h2><strong><?= e(__('events.description')) ?>:</strong></h2>
                <p><?= nl2br(e($event->description)) ?></p>
            </div>
            <?php endif; ?>
            <div class="event-details-actions">
                <?php if ($canViewEntries) : ?>
                    <a class="btn" href="/event_entries.php?event=<?= e($event->id) ?>"><?= e(__('events.entries')) ?></a>
                <?php endif; ?>
                <a class="btn green" href="/event_register.php?id=<?= e($event->id) ?>"><?= e(__('events.registration')) ?></a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($eventsList)) : ?>
<div class="card">
    <?php if ($event === null) : ?>
        <p><?= e(__('events.select_competition')) ?></p>
    <?php endif; ?>
    <h3><?= e($event !== null ? __('events.upcoming_events') : __('events.upcoming_heading')) ?></h3>
    <?php foreach ($eventsList as $next) : ?>
        <div class="event-line">
            <a class="btn btn-sm event-details-btn" href="/event_details.php?event=<?= e($next->id) ?>"><?= e(__('events.details')) ?></a>
            <?= e($next->date) ?>
            - <?= e($next->name) ?>
            - <?= e($next->location) ?>
            - (<?= e(__('events.registration_deadline')) ?>: <?= e($next->registration_deadline) ?>)
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
