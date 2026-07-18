<?php

/** @var \App\Model\Event|null $event */
/** @var list<\App\Model\Event> $upcomingEvents */
/** @var bool $canViewEntries */
/** @var bool $hasRegistrationException */
?>

<?php if ($event !== null) : ?>
    <?php
    $posterUrl = $event->poster_file ? base_url((string) $event->poster_file) : null;
    $posterExt = $event->poster_file ? strtolower(pathinfo($event->poster_file, PATHINFO_EXTENSION)) : '';
    $isImagePoster = in_array($posterExt, ['jpg', 'jpeg', 'png'], true);

    $infoRows = array_filter([
        __('events.name')                  => $event->name,
        __('events.date')                  => $event->date,
        __('events.location')              => $event->location,
        __('admin.add.organizer')          => $event->organizer,
        __('admin.events.type')            => __('events.type.' . $event->type),
        __('events.registration_deadline') => $event->registration_deadline,
    ]);
    ?>
    <div class="card event-details-card">
        <div class="event-details-layout">
            <!-- Event Poster -->
            <div class="event-details-poster">
                <?php if ($posterUrl && $isImagePoster) : ?>
                    <a href="<?= e($posterUrl) ?>" target="_blank">
                        <img src="<?= e($posterUrl) ?>" alt="<?= e(__('events.poster_alt', ['name' => $event->name])) ?>">
                    </a>
                <?php elseif ($posterUrl) : ?>
                    <div class="poster-pdf">
                        <strong><?= e(__('events.poster_pdf')) ?></strong><br>
                        <a class="btn orange" href="<?= e($posterUrl) ?>" target="_blank" download><?= e(__('events.download_poster')) ?></a>
                    </div>
                <?php else : ?>
                    <div class="poster-placeholder" style="background: linear-gradient(rgba(255, 255, 255, 0.5), rgba(255, 255, 255, 0.5)), url('<?= e(asset_url('assets/judo-bg-1280.webp')) ?>') center center / cover no-repeat);">
                        <span style="background: rgba(255,255,255,0.75); padding: 8px 14px; border-radius: 0.75em; display: inline-block;">
                            <strong><?= e(__('events.poster_not_available')) ?></strong>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($event->closed) : ?>
                <div class="badge-container" style="position: relative;">
                    <span class="badge badge-closed" style="background-color: #6c757d; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">
                        <?= e(__('events.closed')) ?>
                    </span>
                </div>
            <?php endif; ?>

            <!-- Event Details -->
            <div class="event-details-info">



                <table class="event-info-table">
                    <?php foreach ($infoRows as $label => $value) : ?>
                        <tr>
                            <td><strong><?= e($label) ?>:</strong></td>
                            <td><?= e($value) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <?php if ($event->description) : ?>
                    <div class="event-description">
                        <h2><strong><?= e(__('events.description')) ?>:</strong></h2>
                        <p><?= nl2br(e($event->description)) ?></p>
                    </div>
                <?php endif; ?>

                <div class="event-details-actions">
                    <a class="btn" href="<?= e(base_url('/events/entries?event=' . (string) $event->id)) ?>"><?= e(__('events.entries')) ?></a>
                    <?php if (!$event->closed || $hasRegistrationException) : ?>
                        <a class="btn green" href="<?= e(base_url('/events/register?event=' . (string) $event->id)) ?>"><?= e(__('events.registration')) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Bottom Upcoming / Next Events Section -->
<?php if ($event === null && empty($upcomingEvents)) : ?>
    <div class="card">
        <p><?= e(__('events.none')) ?></p>
    </div>
<?php elseif (!empty($upcomingEvents)) : ?>
    <div class="card">
        <?php if ($event === null) : ?>
            <p><?= e(__('events.select_event')) ?></p>
        <?php endif; ?>

        <h3><?= e($event !== null ? __('events.upcoming_events') : __('events.upcoming_heading')) ?></h3>

        <?php foreach ($upcomingEvents as $next) : ?>
            <div class="event-line">
                <a class="btn btn-sm event-details-btn" href="<?= e(base_url('/events/details?event=' . (string) $next->id)) ?>"><?= e(__('events.details')) ?></a>
                <!-- Display Closed Badge if closed -->
                <?php if ($next->closed) : ?>
                    <span class="badge badge-closed" style="background-color: #6c757d; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">
                        <?= e(__('events.closed')) ?>
                    </span>
                <?php endif; ?>
                <?= e($next->date) ?> - <?= e($next->name) ?> - <?= e($next->location) ?> - (<?= e(__('events.registration_deadline')) ?>: <?= e($next->registration_deadline) ?>)
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>