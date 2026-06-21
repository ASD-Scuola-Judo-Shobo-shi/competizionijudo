<?php
/** @var \App\Model\Event|null $event */
/** @var string $error */
/** @var array $locations */
$isEdit = !empty($event);
?>
<div class="card">
    <h2><?= $isEdit ? e(__('admin.edit.title')) . ' - ' . e($event->name) : e(__('admin.add.title')) ?></h2>

    <?php if ($error) : ?>
        <div class="notice"><strong>Errore tecnico:</strong><br><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="form-card" enctype="multipart/form-data">
        <input type="hidden" name="event_id" value="<?= e($event?->id ?? '') ?>">

        <label><?= e($isEdit ? __('admin.edit.name') : __('admin.add.name')) ?></label>
        <input name="name" value="<?= e($event?->name ?? '') ?>" required>

        <label><?= e($isEdit ? __('admin.edit.date') : __('admin.add.date')) ?></label>
        <input type="date" name="date" value="<?= e($event?->date ?? '') ?>" required>

        <label><?= e($isEdit ? __('admin.edit.location') : __('admin.add.location')) ?></label>
        <input name="location" value="<?= e($event?->location ?? '') ?>" list="locations_list" required>
        <datalist id="locations_list">
            <?php foreach (($locations ?? []) as $loc) : ?>
                <option value="<?= e($loc) ?>">
            <?php endforeach; ?>
        </datalist>

        <label><?= e($isEdit ? __('admin.edit.organizer') : __('admin.add.organizer')) ?></label>
        <input name="organizer" value="<?= e($event?->organizer ?? '') ?>">

        <label><?= e($isEdit ? __('admin.edit.registration_deadline') : __('admin.add.registration_deadline')) ?></label>
        <input type="date" name="registration_deadline" value="<?= e($event?->registration_deadline ?? '') ?>">

        <label><?= e($isEdit ? __('admin.edit.type') : __('admin.add.type')) ?></label>
        <select name="type" required>
            <option value="">—</option>
            <option value="only_precompetitive" <?= ($event?->type ?? '') === 'only_precompetitive' ? 'selected' : '' ?>><?= e(__('events.type.only_precompetitive')) ?></option>
            <option value="only_competitive" <?= ($event?->type ?? '') === 'only_competitive' ? 'selected' : '' ?>><?= e(__('events.type.only_competitive')) ?></option>
            <option value="precompetitive_and_competitive" <?= ($event?->type ?? '') === 'precompetitive_and_competitive' ? 'selected' : '' ?>><?= e(__('events.type.precompetitive_and_competitive')) ?></option>
        </select>

        <label><?= e($isEdit ? __('admin.edit.description') : __('admin.add.description')) ?></label>
        <textarea name="description" rows="3"><?= e($event?->description ?? '') ?></textarea>

        <label><?= e($isEdit ? __('admin.edit.notes') : __('admin.add.notes')) ?></label>
        <textarea name="notes" rows="3"><?= e($event?->notes ?? '') ?></textarea>

        <label><?= e($isEdit ? __('admin.edit.poster') : __('admin.add.poster')) ?></label>
        <input type="file" name="poster_file" accept=".pdf,.jpg,.jpeg,.png">
        <?php if ($isEdit && !empty($event->poster_file)) : ?>
            <p><a href="/<?= e($event->poster_file) ?>" target="_blank"><?= e($isEdit ? __('events.view_current_poster') : __('events.view_uploaded_file')) ?></a></p>
        <?php endif; ?>

        <label><?= e($isEdit ? __('admin.edit.info_file') : __('admin.add.info_file')) ?></label>
        <input type="file" name="info_file" accept=".pdf,.jpg,.jpeg,.png">
        <?php if ($isEdit && !empty($event->info_file)) : ?>
            <p><a href="/<?= e($event->info_file) ?>" target="_blank"><?= e($isEdit ? __('events.view_current_info') : __('events.view_uploaded_file')) ?></a></p>
        <?php endif; ?>

        <p class="checkbox-group">
            <label><input type="checkbox" name="published" value="1" <?= $isEdit && !empty($event->published) ? 'checked' : '' ?>> <?= e($isEdit ? __('admin.edit.published') : __('admin.add.published')) ?></label>
            <label><input type="checkbox" name="closed" value="1" <?= $isEdit && !empty($event->closed) ? 'checked' : '' ?>> <?= e($isEdit ? __('admin.edit.closed') : __('admin.add.closed')) ?></label>
        </p>

        <button class="btn green" type="submit"><?= e($isEdit ? __('admin.edit.save') : __('admin.add.save')) ?></button>
    </form>
</div>
</parameter>
</parameter>
</write_to_file>