<div class="card">
    <h2><?= e(__('club.forgot_password.heading')) ?></h2>

    <?php if (!empty($errors)) : ?>
        <div class="notice">
            <ul>
                <?php foreach ($errors as $error) : ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)) : ?>
        <div class="success">
            <p><?= e($success) ?></p>
            <?php if (!empty($dev_link)) : ?>
                <p class="small"><?= e(__('club.forgot_password.dev_link_label')) ?></p>
                <p><code><?= e($dev_link) ?></code></p>
            <?php endif; ?>
        </div>
        <a class="btn green" href="/club_login.php"><?= e(__('buttons.back_to_login')) ?></a>
    <?php else : ?>
        <form method="post" class="form-card">
            <label><?= e(__('club.forgot_password.email')) ?></label>
            <input type="email" name="email" required placeholder="<?= e(__('club.forgot_password.email')) ?>">

            <button class="btn green" type="submit"><?= e(__('club.forgot_password.submit')) ?></button>
            <a class="btn" href="/club_login.php"><?= e(__('buttons.back_to_login')) ?></a>
        </form>
    <?php endif; ?>
</div>