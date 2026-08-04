<?php

declare(strict_types=1);

/** @var string $termsVersion */
/** @var array<string, mixed> $operator */
$email = (string) ($operator['contact_email'] ?? '');
?>
<section class="content-panel privacy-notice terms-of-service">
    <h2><?= e(__('terms.title')) ?></h2>
    <p><strong><?= e(__('terms.version_label')) ?>:</strong> <?= e($termsVersion) ?></p>
    <p><?= e(__('terms.intro')) ?></p>

    <h3><?= e(__('terms.operator_title')) ?></h3>
    <p>
        <?= e(__('terms.operator_intro')) ?><br>
        <strong><?= e((string) ($operator['controller_name'] ?? '')) ?></strong><br>
        <?= nl2br(e((string) ($operator['controller_address'] ?? ''))) ?><br>
        <?php if ((string) ($operator['controller_fiscal_code'] ?? '') !== '') : ?>
            <?= e(__('privacy.fiscal_code')) ?>:
            <?= e((string) $operator['controller_fiscal_code']) ?><br>
        <?php endif; ?>
        <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
    </p>

    <h3><?= e(__('terms.scope_title')) ?></h3>
    <p><?= e(__('terms.scope')) ?></p>

    <h3><?= e(__('terms.authority_title')) ?></h3>
    <p><?= e(__('terms.authority')) ?></p>

    <h3><?= e(__('terms.account_title')) ?></h3>
    <ul>
        <li><?= e(__('terms.account_accuracy')) ?></li>
        <li><?= e(__('terms.account_security')) ?></li>
        <li><?= e(__('terms.account_activity')) ?></li>
    </ul>

    <h3><?= e(__('terms.athlete_data_title')) ?></h3>
    <ul>
        <li><?= e(__('terms.athlete_authority')) ?></li>
        <li>
            <?= e(__('terms.notice_delivery')) ?>
            <a href="<?= e(base_url('/privacy')) ?>"><?= e(__('terms.privacy_link')) ?></a>
        </li>
        <li><?= e(__('terms.data_quality')) ?></li>
        <li><?= e(__('terms.sensitive_data')) ?></li>
        <li><?= e(__('terms.rights_cooperation')) ?></li>
    </ul>

    <h3><?= e(__('terms.acceptable_use_title')) ?></h3>
    <p><?= e(__('terms.acceptable_use_intro')) ?></p>
    <ul>
        <li><?= e(__('terms.no_unlawful_use')) ?></li>
        <li><?= e(__('terms.no_security_abuse')) ?></li>
        <li><?= e(__('terms.no_interference')) ?></li>
        <li><?= e(__('terms.no_unauthorized_access')) ?></li>
    </ul>

    <h3><?= e(__('terms.content_title')) ?></h3>
    <p><?= e(__('terms.content_rights')) ?></p>
    <p><?= e(__('terms.content_license')) ?></p>

    <h3><?= e(__('terms.events_title')) ?></h3>
    <p><?= e(__('terms.events')) ?></p>
    <p><?= e(__('terms.payments')) ?></p>

    <h3><?= e(__('terms.availability_title')) ?></h3>
    <p><?= e(__('terms.availability')) ?></p>
    <p><?= e(__('terms.liability')) ?></p>

    <h3><?= e(__('terms.duration_title')) ?></h3>
    <p><?= e(__('terms.duration')) ?></p>

    <h3><?= e(__('terms.changes_title')) ?></h3>
    <p><?= e(__('terms.changes')) ?></p>

    <h3><?= e(__('terms.privacy_title')) ?></h3>
    <p>
        <?= e(__('terms.privacy_relation')) ?>
        <a href="<?= e(base_url('/privacy')) ?>"><?= e(__('terms.privacy_link')) ?></a>
    </p>

    <h3><?= e(__('terms.law_title')) ?></h3>
    <p><?= e(__('terms.law')) ?></p>

    <h3><?= e(__('terms.contact_title')) ?></h3>
    <p><?= e(__('terms.contact')) ?> <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>.</p>
</section>
