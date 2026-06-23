<?php
/** @var \App\Model\Event $event */
/** @var string $error */
?>
<div class="card">
    <h2><?= e(__('admin.edit.title')) ?> - <?= e($event->name) ?></h2>

    <?php if ($error) : ?>
        <div class="notice"><strong>Errore tecnico:</strong><br><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="row">
            <div>
                <label><?= e(__('admin.edit.name')) ?></label>
                <input name="name" value="<?= e($event->name) ?>" required>
            </div>
            <div>
                <label><?= e(__('admin.edit.date')) ?></label>
                <input name="date" value="<?= e($event->date) ?>" required>
            </div>
            <div>
                <label><?= e(__('admin.edit.location')) ?></label>
                <input name="location" value="<?= e($event->location) ?>">
            </div>
        </div>

        <div class="row">
            <div>
                <label><?= e(__('admin.edit.organizer')) ?></label>
                <input name="organizer" value="<?= e($event->organizer) ?>">
            </div>
            <div>
                <label><?= e(__('admin.edit.registration_deadline')) ?></label>
                <input name="registration_deadline" value="<?= e($event->registration_deadline) ?>">
            </div>
            <div>
                <label><?= e(__('admin.edit.type')) ?></label>
                <select name="type">
                    <option value="only_precompetitive" <?= $event->type === 'only_precompetitive' ? 'selected' : '' ?>><?= e(__('events.type.only_precompetitive')) ?></option>
                    <option value="only_competitive" <?= $event->type === 'only_competitive' ? 'selected' : '' ?>><?= e(__('events.type.only_competitive')) ?></option>
                    <option value="precompetitive_and_competitive" <?= $event->type === 'precompetitive_and_competitive' ? 'selected' : '' ?>><?= e(__('events.type.precompetitive_and_competitive')) ?></option>
                </select>
            </div>
        </div>

        <div class="row2">
            <div>
                <label><?= e(__('admin.edit.description')) ?></label>
                <textarea name="description"><?= e($event->description ?? '') ?></textarea>
            </div>
            <div>
                <label><?= e(__('admin.edit.notes')) ?></label>
                <textarea name="notes"><?= e($event->notes ?? '') ?></textarea>
            </div>
        </div>

        <div class="row2">
            <div>
                <label><?= e(__('admin.edit.poster')) ?></label>
                <input type="file" name="poster_file" accept=".pdf,.jpg,.jpeg,.png">
                <?php if ($event->poster_file) : ?>
                    <p><a href="/<?= e($event->poster_file) ?>" target="_blank"><?= e(__('events.view_current_poster')) ?></a></p>
                <?php endif; ?>
            </div>
            <div>
                <label><?= e(__('admin.edit.info_file')) ?></label>
                <input type="file" name="info_file" accept=".pdf,.jpg,.jpeg,.png">
                <?php if ($event->info_file) : ?>
                    <p><a href="/<?= e($event->info_file) ?>" target="_blank"><?= e(__('events.view_current_info')) ?></a></p>
                <?php endif; ?>
            </div>
        </div>

        <p class="checkbox-group">
            <label><input type="checkbox" name="published" value="1" <?= $event->published ? 'checked' : '' ?>> <?= e(__('admin.edit.published')) ?></label>
            <label><input type="checkbox" name="closed" value="1" <?= $event->closed ? 'checked' : '' ?>> <?= e(__('admin.edit.closed')) ?></label>
        </p>

        <button class="btn green" type="submit"><?= e(__('admin.edit.save')) ?></button>
    </form>
</div>
