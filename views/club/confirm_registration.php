<?php /** @var bool $success */ /** @var string|null $error */ ?>
<div class="card">
    <h2><?= e(__('club.confirm_registration.heading')) ?></h2>

    <?php if ($success) : ?>
        <div class="notice success"><?= e(__('club.confirm_registration.success')) ?></div>
        <a class="btn green" href="<?= e(base_url('/club_login.php')) ?>"><?= e(__('buttons.back_to_login')) ?></a>
    <?php else : ?>
        <div class="notice"><?= e($error ?? __('club.confirm_registration.invalid_token')) ?></div>
        <a class="btn" href="<?= e(base_url('/club_register.php')) ?>"><?= e(__('nav.register')) ?></a>
    <?php endif; ?>
</div>
