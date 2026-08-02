<?php $contactEmail = (string) ($privacyControllerEmail ?? ''); ?>
<?php if ($contactEmail !== '') : ?>
    <section>
        <div class="landing-copy card banner">
            <p><?= e(__('about.banner.text')) ?> <a href="mailto:<?= e($contactEmail) ?>"><?= e(__('about.banner.link')) ?></a></p>
        </div>
    </section>
<?php endif; ?>
<section class="landing-clean">
    <div class="landing-copy">
        <img
            class="landing-logo"
            src="<?= e(asset_url('assets/competizioni-judo-logo-optim.svgz')) ?>"
            alt="<?= e(__('app.logo_alt')) ?>">
        <div>
            <h2><?= translate('header.title') ?></h2>
            <p><?= translate('about.description') ?></p>
        </div>
    </div>
</section>

<section class="info-strip">
    <div>
        <strong><?= translate('about_info.entries') ?></strong>
        <span><?= translate('about_info.entries_text') ?></span>
    </div>
    <div>
        <strong><?= translate('about_info.clubs') ?></strong>
        <span><?= translate('about_info.clubs_text') ?></span>
    </div>
    <div>
        <strong><?= translate('about_info.athletes') ?></strong>
        <span><?= translate('about_info.athletes_text') ?></span>
    </div>
</section>
