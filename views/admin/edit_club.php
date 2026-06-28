<?php /** @var \App\Model\Club $club */ /** @var string $error */ ?>
<div class="card">
    <h2><?= e(__('admin.clubs.edit_title')) ?> - <?= e($club->name) ?></h2>

    <?php if ($error) : ?>
        <div class="notice"><strong>Errore tecnico:</strong><br><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <div class="row">
            <div>
                <label><?= e(__('admin.clubs.name')) ?></label>
                <input name="name" value="<?= e($club->name) ?>" required>
            </div>
            <div>
                <label><?= e(__('admin.clubs.email')) ?></label>
                <input type="email" name="email" value="<?= e($club->email) ?>" required>
            </div>
            <div>
                <label><?= e(__('admin.clubs.federal_code')) ?></label>
                <input name="federal_code" value="<?= e($club->federal_code) ?>" required>
            </div>
        </div>

        <div class="row">
            <div>
                <label><?= e(__('admin.clubs.phone')) ?></label>
                <input name="phone" value="<?= e($club->phone) ?>">
            </div>
            <div>
                <label><?= e(__('admin.clubs.contact_first_name')) ?></label>
                <input name="contact_first_name" value="<?= e($club->contact_first_name) ?>">
            </div>
            <div>
                <label><?= e(__('admin.clubs.contact_last_name')) ?></label>
                <input name="contact_last_name" value="<?= e($club->contact_last_name) ?>">
            </div>
        </div>

        <div class="row">
            <div>
                <label><?= e(__('admin.clubs.contact_phone')) ?></label>
                <input name="contact_phone" value="<?= e($club->contact_phone) ?>">
            </div>
            <div>
                <label><?= e(__('admin.clubs.contact_email')) ?></label>
                <input type="email" name="contact_email" value="<?= e($club->contact_email ?? '') ?>">
            </div>
            <div>
                <label><?= e(__('admin.clubs.organization')) ?></label>
                <input name="organization" value="<?= e($club->organization) ?>">
            </div>
        </div>

        <div class="row">
            <div>
                <label><?= e(__('admin.clubs.recovery_email')) ?></label>
                <input type="email" name="recovery_email" value="<?= e($club->recovery_email) ?>" required>
            </div>
            <div>
                <label><?= e(__('admin.clubs.password')) ?></label>
                <input type="password" name="password_hash" placeholder="..." autocomplete="new-password" minlength="<?= \App\Security\PasswordPolicy::MINIMUM_LENGTH ?>">
                <small><?= e(__('errors.password_too_short', [
                    'minimum' => (string) \App\Security\PasswordPolicy::MINIMUM_LENGTH,
                ])) ?></small>
            </div>
        </div>

        <button class="btn green" type="submit"><?= e(__('admin.clubs.save')) ?></button>
    </form>
</div>
