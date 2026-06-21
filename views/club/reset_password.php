<div class="card">
    <h2><?= e(__('club.reset_password.heading')) ?></h2>

    <?php if (!empty($email) && $valid) : ?>
        <p><?= e(__('club.reset_password.email_notice', ['email' => ''])) ?><strong class="reset-email"><?= e($email) ?></strong></p>
    <?php endif; ?>

    <?php if (!empty($errors)) : ?>
        <div class="notice">
            <ul>
                <?php foreach ($errors as $error) : ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($valid) : ?>
        <form method="post" class="form-card">
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <label><?= e(__('club.reset_password.password')) ?></label>
            <input type="password" name="password" required>

            <label><?= e(__('club.reset_password.confirm_password')) ?></label>
            <input type="password" name="password2" required>

            <button class="btn green" type="submit"><?= e(__('club.reset_password.submit')) ?></button>
            <a class="btn" href="/club_login.php"><?= e(__('buttons.back_to_login')) ?></a>
        </form>
    <?php else : ?>
        <a class="btn green" href="/club_login.php"><?= e(__('buttons.back_to_login')) ?></a>
    <?php endif; ?>
</div>