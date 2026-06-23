<div class="card">
    <h2><?= e(__('club.login.heading')) ?></h2>

    <?php if (!empty($errors)) : ?>
        <div class="notice">
            <ul>
                <?php foreach ($errors as $error) : ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="form-card">
        <?= csrf_field() ?>
        <label><?= e(__('club.login.club_email')) ?></label>
        <input type="email" name="email" required placeholder="<?= e(__('club.login.club_email')) ?>">

        <label><?= e(__('club.login.password')) ?></label>
        <input type="password" name="password" required>

        <p class="form-footer">
            <a href="/club_forgot_password.php"><?= e(__('club.login.forgot_password_link')) ?></a>
        </p>
        <button class="btn green" type="submit"><?= e(__('club.login.login_button')) ?></button>
        <a class="btn" href="/club_register.php"><?= e(__('club.login.register_link')) ?></a>
    </form>
</div>
