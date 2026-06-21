<div class="card">
    <h2><?= e(__('club.register.heading')) ?></h2>

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
        <div class="notice success"><?= e($success) ?></div>
        <a class="btn green" href="/club_login.php"><?= e(__('buttons.back_to_login')) ?></a>
    <?php else : ?>
        <form method="post" class="form-card">
            <label><?= e(__('club.register.club_name')) ?></label>
            <input name="name" required>

            <label><?= e(__('club.register.federal_code')) ?></label>
            <input name="federal_code">

            <label><?= e(__('club.register.club_email')) ?></label>
            <input type="email" name="email" required>

            <label><?= e(__('club.register.club_phone')) ?></label>
            <input name="phone">

            <label><?= e(__('club.register.contact_name')) ?></label>
            <input name="contact">

            <label><?= e(__('club.register.password')) ?></label>
            <input type="password" name="password" required>

            <label><?= e(__('club.register.confirm_password')) ?></label>
            <input type="password" name="password2" required>

            <button class="btn green" type="submit"><?= e(__('club.register.register_button')) ?></button>
            <a class="btn" href="/club_login.php"><?= e(__('nav.club_login')) ?></a>
        </form>
    <?php endif; ?>
</div>
