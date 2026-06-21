<?php
/** @var array $errors */
?>
<div class="card">
    <h2><?= e(__('admin.login.title')) ?></h2>

    <?php if (!empty($errors)) : ?>
        <div class="notice">
            <?php foreach ($errors as $err) : ?>
                <div><?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <div>
            <label><?= e(__('admin.login.user')) ?></label>
            <input name="user" required>
        </div>
        <div>
            <label><?= e(__('admin.login.pass')) ?></label>
            <input type="password" name="pass" required>
        </div>
        <button class="btn green" type="submit"><?= e(__('admin.login.login_button')) ?></button>
    </form>
</div>
