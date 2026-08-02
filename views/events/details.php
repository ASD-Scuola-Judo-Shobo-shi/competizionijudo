<?php

/** @var \App\Model\Event|null $event */
/** @var list<\App\Model\Event> $upcomingEvents */
/** @var array<int, bool> $eventExceptions */
/** @var bool $canViewEntries */
/** @var bool $hasRegistrationException */
/** @var list<\App\Model\EventRegistrationOption>|null $registrationOptions */
$registrationOptions ??= [];
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
        __('admin.event_details.organizer')          => $event->organizer,
        __('events.type_label')            => __('events.type.' . $event->type),
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
                    <div class="poster-placeholder">
                        <span class="poster-placeholder-label">
                            <strong><?= e(__('events.poster_not_available')) ?></strong>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($event->closed) : ?>
                <div class="badge-container">
                    <span class="badge badge-closed event-closed-badge">
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
                    <?php if ($registrationOptions !== []) : ?>
                        <tr>
                            <td><strong><?= e(__('events.registration_fees')) ?>:</strong></td>
                            <td>
                                <ul class="event-registration-fees">
                                    <?php foreach ($registrationOptions as $option) : ?>
                                        <li>
                                            <?php
                                            $formattedFee = \App\Service\RegistrationPaymentService::formatAmount(
                                                $option->fee_cents
                                            );
                                            ?>
                                            <span><?= e($option->name) ?></span>
                                            <strong><?= e($formattedFee) ?></strong>
                                            <?php if ($option->is_default) : ?>
                                                <span class="badge registration-default-badge">
                                                    <?= e(__('events.registration_option_default')) ?>
                                                </span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                        </tr>
                    <?php endif; ?>
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

<?php
$upcomingEventAction = 'details';
require dirname(__DIR__) . '/components/upcoming_events.php';
?>
