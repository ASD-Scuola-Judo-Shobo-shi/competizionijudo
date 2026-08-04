<?php

use App\Model\ClubTermsAcceptance;

/** @var list<string> $errors */
/** @var bool $termsCurrent */
/** @var bool $privacyCurrent */
?>
<section class="content-panel" id="club-agreements">
    <h2><?= e(__('club.agreements.title')) ?></h2>
    <p><?= e(__('club.agreements.intro')) ?></p>

    <?php if ($errors !== []) : ?>
        <div class="notice" role="alert">
            <ul>
                <?php foreach ($errors as $error) : ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($termsCurrent && $privacyCurrent) : ?>
        <div class="notice success" role="status"><?= e(__('club.agreements.current')) ?></div>
    <?php else : ?>
        <form method="post" action="<?= e(base_url('/clubs/agreements')) ?>" class="form-card">
            <?= csrf_field() ?>

            <?php if ($termsCurrent) : ?>
                <div class="notice success" role="status"><?= e(__('club.agreements.terms_current')) ?></div>
            <?php else : ?>
                <label class="consent-field">
                    <input type="checkbox" name="terms_accepted" value="1" required>
                    <span>
                        <?= e(__('club.register.terms_acceptance', [
                            'version' => ClubTermsAcceptance::VERSION,
                        ])) ?>
                        <a href="<?= e(base_url('/terms')) ?>" target="_blank" rel="noopener noreferrer"><?= e(__('club.register.terms_link')) ?></a>
                        <span class="required-marker" aria-hidden="true">*</span>
                    </span>
                </label>
            <?php endif; ?>

            <?php if ($privacyCurrent) : ?>
                <div class="notice success" role="status"><?= e(__('club.agreements.privacy_current')) ?></div>
            <?php else : ?>
                <label class="consent-field">
                    <input type="checkbox" name="athlete_privacy_obligations" value="1" required>
                    <span>
                        <?= e(__('club.register.athlete_data_rights_declaration')) ?>
                        <a href="<?= e(base_url('/privacy')) ?>" target="_blank" rel="noopener noreferrer"><?= e(__('club.register.privacy_notice')) ?></a>
                        <span class="required-marker" aria-hidden="true">*</span>
                    </span>
                </label>
            <?php endif; ?>

            <button class="btn green" type="submit"><?= e(__('club.agreements.accept')) ?></button>
        </form>
    <?php endif; ?>

    <a class="btn" href="<?= e(base_url('/clubs/area')) ?>"><?= e(__('club.agreements.back')) ?></a>
</section>
