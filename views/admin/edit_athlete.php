<?php

declare(strict_types=1);

/** @var \App\Model\Club $club */
/** @var \App\Model\Athlete|null $athlete */
/** @var array<string, string> $values */
/** @var list<string> $errors */
$formUrl = '/admin/clubs/athletes/edit?club_id=' . $club->id;
if ($athlete !== null) {
    $formUrl .= '&athlete_id=' . $athlete->id;
}
?>
<section class="card">
    <header class="admin-list-heading admin-list-heading--with-actions">
        <div>
            <p class="admin-list-eyebrow"><?= e($club->name) ?></p>
            <h2><?= e($athlete !== null ? __('club.area.edit_athlete') : __('club.area.add_athlete')) ?></h2>
        </div>
        <a class="btn gray" href="<?= e(base_url('/admin/clubs/athletes?club_id=' . $club->id)) ?>">
            <?= e(__('club.area.athlete_archive')) ?>
        </a>
    </header>
    <?php if ($errors !== []) : ?>
        <div class="notice" role="alert">
            <ul>
                <?php foreach ($errors as $error) : ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <form class="form-card" method="post" action="<?= e(base_url($formUrl)) ?>">
        <?= csrf_field() ?>
        <label for="last_name"><?= e(__('club.area.last_name')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
        <input id="last_name" name="last_name" value="<?= e($values['last_name']) ?>" required>

        <label for="first_name"><?= e(__('club.area.first_name')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
        <input id="first_name" name="first_name" value="<?= e($values['first_name']) ?>" required>

        <label for="gender"><?= e(__('club.area.gender')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
        <select id="gender" name="gender" required>
            <option value="">—</option>
            <?php foreach (\App\Model\Gender::cases() as $gender) : ?>
                <option value="<?= e($gender->value) ?>" <?= $values['gender'] === $gender->value ? 'selected' : '' ?>><?= e($gender->iconLabel()) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="birth_date"><?= e(__('club.area.birth_date')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
        <input id="birth_date" type="date" name="birth_date" value="<?= e($values['birth_date']) ?>" max="<?= e(gmdate('Y-m-d')) ?>" required>

        <label for="weight_kg"><?= e(__('club.area.weight_kg')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
        <input id="weight_kg" type="number" name="weight_kg" min="0.1" max="200" step="0.1" value="<?= e($values['weight_kg']) ?>" required>

        <label for="belt"><?= e(__('club.area.belt')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
        <select id="belt" name="belt" required>
            <option value="">—</option>
            <?php foreach (\App\Model\Belt::cases() as $belt) : ?>
                <option value="<?= e($belt->value) ?>" <?= $values['belt'] === $belt->value ? 'selected' : '' ?>><?= e($belt->circleLabel()) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="membership_number"><?= e(__('club.area.membership_number')) ?></label>
        <input id="membership_number" name="membership_number" value="<?= e($values['membership_number']) ?>">

        <label for="notes"><?= e(__('club.area.notes')) ?></label>
        <p class="field-help" id="athlete-notes-help"><?= e(__('club.area.notes_privacy_help')) ?></p>
        <textarea id="notes" name="notes" rows="3" maxlength="2000" aria-describedby="athlete-notes-help"><?= e($values['notes']) ?></textarea>
        <button class="btn green" type="submit"><?= e(__('club.area.save_athlete')) ?></button>
    </form>
</section>
