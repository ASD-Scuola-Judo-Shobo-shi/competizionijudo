<?php
/** @var \App\Model\Event|null $event */
/** @var string $error */
/** @var list<string> $locations */
/** @var list<array{id: int, name: string}> $clubs */
/** @var list<int> $exceptionClubIds */
$isEdit = !empty($event);
?>
<div class="card">
    <h2><?= $isEdit ? e(__('admin.edit.title')) . ' - ' . e($event->name) : e(__('admin.add.title')) ?></h2>

    <?php if ($error) : ?>
        <div class="notice"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="form-card" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= \App\Validation\EventInputValidator::MAX_UPLOAD_BYTES ?>">
        <input type="hidden" name="event_id" value="<?= e($event?->id ?? '') ?>">

        <label><?= e($isEdit ? __('admin.edit.name') : __('admin.add.name')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
        <input name="name" value="<?= e($event?->name ?? '') ?>" required>

        <label><?= e($isEdit ? __('admin.edit.date') : __('admin.add.date')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
        <input type="date" name="date" value="<?= e($event?->date ?? '') ?>" required>

        <label><?= e($isEdit ? __('admin.edit.location') : __('admin.add.location')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
        <input name="location" value="<?= e($event?->location ?? '') ?>" list="locations_list" required>
        <datalist id="locations_list">
            <?php foreach ($locations as $loc) : ?>
                <option value="<?= e($loc) ?>">
            <?php endforeach; ?>
        </datalist>

        <label><?= e($isEdit ? __('admin.edit.organizer') : __('admin.add.organizer')) ?></label>
        <input name="organizer" value="<?= e($event?->organizer ?? '') ?>">

        <label><?= e($isEdit ? __('admin.edit.registration_deadline') : __('admin.add.registration_deadline')) ?></label>
        <input type="date" name="registration_deadline" value="<?= e($event?->registration_deadline ?? '') ?>">

        <label><?= e($isEdit ? __('admin.edit.type') : __('admin.add.type')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
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

        <label><?= e($isEdit ? __('admin.edit.max_participants') : __('admin.add.max_participants')) ?></label>
        <input type="number" name="max_participants" value="<?= e($event?->max_participants ? (string) $event->max_participants : '') ?>" min="1" placeholder="<?= e(__('admin.add.max_participants_placeholder')) ?>">

        <label><?= e($isEdit ? __('admin.edit.poster') : __('admin.add.poster')) ?></label>
        <input type="file" name="poster_file" accept=".pdf,.jpg,.jpeg,.png">
        <?php if ($isEdit && !empty($event->poster_file)) : ?>
            <p><a href="<?= e(base_url((string) $event->poster_file)) ?>" target="_blank"><?= e(__('events.view_current_poster')) ?></a></p>
        <?php endif; ?>

        <label><?= e($isEdit ? __('admin.edit.info_file') : __('admin.add.info_file')) ?></label>
        <input type="file" name="info_file" accept=".pdf,.jpg,.jpeg,.png">
        <?php if ($isEdit && !empty($event->info_file)) : ?>
            <p><a href="<?= e(base_url((string) $event->info_file)) ?>" target="_blank"><?= e(__('events.view_current_info')) ?></a></p>
        <?php endif; ?>

        <p class="checkbox-group">
            <label><input type="checkbox" name="published" value="1" <?= $isEdit && !empty($event->published) ? 'checked' : '' ?>> <?= e($isEdit ? __('admin.edit.published') : __('admin.add.published')) ?></label>
            <label><input type="checkbox" name="closed" value="1" <?= $isEdit && !empty($event->closed) ? 'checked' : '' ?>> <?= e($isEdit ? __('admin.edit.closed') : __('admin.add.closed')) ?></label>
        </p>

        <?php if (!empty($clubs)) : ?>
        <label><?= e(__('admin.add.registration_exceptions')) ?></label>
        <p style="font-size: 0.9em; color: #666; margin-bottom: 0.5rem;"><?= e(__('admin.add.registration_exceptions_help')) ?></p>
        <div class="checkbox-dropdown">
            <button type="button" class="dropdown-toggle btn btn-sm" onclick="toggleDropdown(this)">
                <span><?= e(__('admin.add.select_clubs')) ?></span>
                <span class="dropdown-arrow">▼</span>
            </button>
            <div class="dropdown-menu" style="display: none;">
                <?php foreach ($clubs as $club) : ?>
                    <?php $isChecked = in_array($club['id'], $exceptionClubIds, true); ?>
                    <label class="dropdown-item">
                        <input type="checkbox" name="registration_exceptions[]" value="<?= e((string) $club['id']) ?>" <?= $isChecked ? 'checked' : '' ?>>
                        <?= e($club['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <button class="btn green" type="submit"><?= e($isEdit ? __('admin.edit.save') : __('admin.add.save')) ?></button>
    </form>
</div>
    <script>
    // Dropdown toggle for registration exceptions
    function toggleDropdown(button) {
        const menu = button.nextElementSibling;
        if (menu && menu.classList.contains('dropdown-menu')) {
            menu.style.display = (menu.style.display === 'none' || menu.style.display === '') ? 'block' : 'none';
        }
    }
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.checkbox-dropdown')) {
            document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
                menu.style.display = 'none';
            });
        }
    });
    </script>
