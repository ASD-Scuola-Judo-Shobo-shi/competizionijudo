<?php

declare(strict_types=1);

/** @var array<string, mixed> $privacy */
$email = (string) ($privacy['contact_email'] ?? '');
$dpo = (string) ($privacy['dpo_email'] ?? '');
?>
<section class="page-card privacy-notice">
    <h2><?= e(__('privacy.title')) ?></h2>
    <p><?= e(__('privacy.intro')) ?></p>

    <h3><?= e(__('privacy.controller_title')) ?></h3>
    <p>
        <strong><?= e((string) ($privacy['controller_name'] ?? '')) ?></strong><br>
        <?= nl2br(e((string) ($privacy['controller_address'] ?? ''))) ?><br>
        <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
        <?php if ($dpo !== '') : ?><br><?= e(__('privacy.dpo')) ?>: <a href="mailto:<?= e($dpo) ?>"><?= e($dpo) ?></a><?php endif; ?>
    </p>

    <h3><?= e(__('privacy.data_title')) ?></h3>
    <ul>
        <li><?= e(__('privacy.account_data')) ?></li>
        <li><?= e(__('privacy.athlete_data')) ?></li>
        <li><?= e(__('privacy.event_data')) ?></li>
        <li><?= e(__('privacy.security_data')) ?></li>
    </ul>

    <h3><?= e(__('privacy.purpose_title')) ?></h3>
    <p><?= e(__('privacy.account_purpose')) ?><br><strong><?= e(__('privacy.legal_basis')) ?>:</strong> <?= e((string) ($privacy['account_legal_basis'] ?? '')) ?></p>
    <p><?= e(__('privacy.athlete_purpose')) ?><br><strong><?= e(__('privacy.legal_basis')) ?>:</strong> <?= e((string) ($privacy['athlete_legal_basis'] ?? '')) ?></p>
    <p><?= e(__('privacy.provision')) ?></p>

    <h3><?= e(__('privacy.recipients_title')) ?></h3>
    <p><?= e(__('privacy.hosting', [
        'provider' => (string) ($privacy['hosting_provider'] ?? ''),
        'location' => (string) ($privacy['hosting_location'] ?? ''),
    ])) ?></p>
    <?php if ((string) ($privacy['additional_processors'] ?? '') !== '') : ?>
        <p><?= e(__('privacy.additional_processors')) ?>: <?= e((string) $privacy['additional_processors']) ?></p>
    <?php endif; ?>
    <p><strong><?= e(__('privacy.transfers')) ?>:</strong> <?= e((string) ($privacy['data_transfer_details'] ?? '')) ?></p>

    <h3><?= e(__('privacy.retention_title')) ?></h3>
    <ul>
        <li><?= e(__('privacy.live_retention')) ?></li>
        <li><?= e(__('privacy.snapshot_retention')) ?></li>
        <li><?= e(__('privacy.upload_retention')) ?></li>
        <li><?= e(__('privacy.log_retention', ['days' => (string) ($privacy['log_retention_days'] ?? '')])) ?></li>
        <li><?= e(__('privacy.backup_retention', ['days' => (string) ($privacy['backup_retention_days'] ?? '')])) ?></li>
    </ul>

    <h3><?= e(__('privacy.rights_title')) ?></h3>
    <p><?= e(__('privacy.rights')) ?> <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>.</p>
    <p><?= e(__('privacy.consent_right')) ?></p>
    <p><?= e(__('privacy.complaint')) ?></p>

    <h3><?= e(__('privacy.cookies_title')) ?></h3>
    <p><?= e(__('privacy.cookies')) ?></p>
    <p><?= e(__('privacy.automation')) ?></p>
</section>
