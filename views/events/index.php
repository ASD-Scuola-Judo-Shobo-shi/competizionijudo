<?php

/** @var array $events */
/** @var bool $canViewEntries */
?>
<div class="card">
    <?php if (!$events) : ?>
        <div class="landing-copy">
            <img
                class="landing-logo"
                src="<?= e(asset_url('assets/competizioni-judo-logo-optim.svgz')) ?>"
                alt="<?= e(__('app.logo_alt')) ?>">
            <div>
                <h2><?= translate('header.title') ?></h2>
                <p>
                    <?= translate('home.description') ?>
                </p>
            </div>
        </div>
        <p><?= e(__('events.none')) ?></p>
    <?php endif; ?>

    <?php foreach ($events as $ev) : ?>
        <div class="event-card-public">
            <div class="event-poster">
                <?php if ($ev->poster_file) : ?>
                    <?php $ext = strtolower(pathinfo($ev->poster_file, PATHINFO_EXTENSION)); ?>
                    <?php if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) : ?>
                        <a href="<?= e(base_url((string) $ev->poster_file)) ?>" target="_blank">
                            <img src="<?= e(base_url((string) $ev->poster_file)) ?>" alt="<?= e(__('events.poster_alt', ['name' => $ev->name])) ?>">
                        </a>
                    <?php else : ?>
                        <div class="poster-pdf">
                            <strong><?= e(__('events.poster_pdf')) ?></strong><br>
                            <a class="btn orange" href="<?= e(base_url((string) $ev->poster_file)) ?>" target="_blank" download><?= e(__('events.download_poster')) ?></a>
                        </div>
                    <?php endif; ?>
                <?php else : ?>
                    <div class="poster-placeholder" style="background: linear-gradient(rgba(255, 255, 255, 0.5), rgba(255, 255, 255, 0.5)), url('<?= e(asset_url('assets/judo-bg-1280.webp')) ?>') center center / cover no-repeat;">
                        <span style="background: rgba(255,255,255,0.75); padding: 8px 14px; border-radius: 0.75em; display: inline-block;">
                            <strong><?= e(__('events.poster_not_available')) ?></strong>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="event-info">
                <h3><?= e($ev->name) ?></h3>

                <p>
                    <strong><?= e(__('events.date')) ?>:</strong> <?= e($ev->date) ?><br>
                    <strong><?= e(__('events.location')) ?>:</strong> <?= e($ev->location) ?><br>
                    <strong><?= e(__('events.organizer')) ?>:</strong> <?= e($ev->organizer) ?><br>
                    <strong><?= e(__('admin.events.type')) ?>:</strong> <?= e(__('events.type.' . $ev->type)) ?><br>
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
                    <?php if ($canViewEntries) : ?>
                        <a class="btn" href="<?= e(base_url('/events/entries?event=' . (string) $ev->id)) ?>"><?= e(__('events.entries')) ?></a>
                    <?php endif; ?>
                    <a class="btn green" href="<?= e(base_url('/events/register?id=' . (string) $ev->id)) ?>"><?= e(__('events.registration')) ?></a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>