<?php
/** @var array $events */
/** @var int|null $loggedInClubId */
/** @var array<int, bool> $eventExceptions */
/** @var array<int, \App\Presentation\EventPosterViewModel> $posters */
?>
<div class="card">
    <?php if (empty($events)) : ?>
        <p><?= e(__('events.none')) ?></p>
    <?php endif; ?>

<?php foreach ($events as $ev) : ?>
        <?php
        $detailsUrl = base_url('/events/details?event=' . (string) $ev->id);
        $poster = $posters[$ev->id];
        ?>
        <div class="event-card-public">
            <?php if ($ev->closed) : ?>
                <div class="event-badge-closed event-closed-badge event-closed-badge--corner">
                    <?= e(__('events.closed')) ?>
                </div>
            <?php endif; ?>
            <div class="event-poster">
                <a
                    class="event-poster-link"
                    href="<?= e($detailsUrl) ?>"
                    aria-label="<?= e(__('events.details') . ': ' . $ev->name) ?>"
                >
                    <?php if ($poster->url && $poster->isImage) : ?>
                        <img src="<?= e($poster->url) ?>" alt="<?= e(__('events.poster_alt', ['name' => $ev->name])) ?>">
                    <?php elseif ($poster->url) : ?>
                        <span class="poster-pdf">
                            <strong><?= e(__('events.poster_pdf')) ?></strong>
                        </span>
                    <?php else : ?>
                        <span class="poster-placeholder">
                            <span class="poster-placeholder-label">
                                <strong>
                                    <?= e($poster->requiresLogin
                                        ? __('events.poster_login_required')
                                        : __('events.poster_not_available')) ?>
                                </strong>
                            </span>
                        </span>
                    <?php endif; ?>
                </a>
            </div>

            <div class="event-info">
                <h3><?= e($ev->name) ?></h3>

                <p>
                    <strong><?= e(__('events.date')) ?>:</strong> <?= e($ev->date) ?><br>
                    <strong><?= e(__('events.location')) ?>:</strong> <?= e($ev->location) ?><br>
                    <strong><?= e(__('events.organizer')) ?>:</strong> <?= e($ev->organizer) ?><br>
                    <strong><?= e(__('events.type_label')) ?>:</strong> <?= e(__('events.type.' . $ev->type)) ?><br>
                    <strong><?= e(__('events.registration_deadline')) ?>:</strong> <?= e($ev->registration_deadline) ?>
                </p>

                <?php if ($ev->description) :
                    $word_limit = 50;
                    $words = preg_split('/\s+/', $ev->description);

                    if (count($words) > $word_limit) {
                        $truncated = implode(' ', array_slice($words, 0, $word_limit)) . '...';
                    } else {
                        $truncated = $ev->description;
                    }
                    ?>
                    <p><?= nl2br(e($truncated)) ?></p>
                <?php endif; ?>

                <div class="event-actions">
                    <?php if ($ev->info_file) : ?>
                        <a class="btn orange" href="<?= e(base_url((string) $ev->info_file)) ?>" target="_blank" download><?= e(__('events.download_info')) ?></a>
                    <?php endif; ?>
                    <a class="btn" href="<?= e(base_url('/events/details?event=' . (string) $ev->id)) ?>"><?= e(__('events.details')) ?></a>
                    <a class="btn" href="<?= e(base_url('/events/entries?event=' . (string) $ev->id)) ?>"><?= e(__('events.entries')) ?></a>
                    <?php $hasException = $loggedInClubId !== null && ($eventExceptions[$ev->id] ?? false); ?>
                    <?php if (!$ev->closed || $hasException) : ?>
                        <a class="btn green" href="<?= e(base_url('/events/register?event=' . (string) $ev->id)) ?>"><?= e(__('events.registration')) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
<?php endforeach; ?>
</div>
