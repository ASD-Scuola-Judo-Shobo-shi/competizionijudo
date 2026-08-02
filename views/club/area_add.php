<?php
/** @var \App\Model\Athlete|null $edit */
/** @var list<string> $errors */
/** @var array<int, array{age_below: int|null, type: string, weight_category: string}> $athleteCategories */
/** @var array{type: string, message: string}|null $athleteInlineFeedback */
/** @var array{column:string, direction:'asc'|'desc'}|null $tableSort */
$tableSort ??= ['column' => 'athlete', 'direction' => 'asc'];
?>
<?php require __DIR__ . '/_athlete_csv_tools.php'; ?>
<div class="card">
    <h3><?= e($edit ? __('club.area.edit_athlete') : __('club.area.add_athlete')) ?></h3>
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
        <input type="hidden" name="athlete_id" value="<?= e($edit?->id ?? '') ?>">

        <label><?= e(__('club.area.last_name')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
        <input name="last_name" required value="<?= e($edit?->last_name ?? '') ?>">

        <label><?= e(__('club.area.first_name')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
        <input name="first_name" required value="<?= e($edit?->first_name ?? '') ?>">

        <label><?= e(__('club.area.gender')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
        <select name="gender" required>
            <option value="">—</option>
            <?php foreach (App\Model\Gender::cases() as $genderEnum) : ?>
                <option value="<?= e($genderEnum->value) ?>" <?= ($edit?->gender ?? '') === $genderEnum->value ? 'selected' : '' ?>><?= $genderEnum->iconLabel(App\Localization::getLocale()) ?></option>
            <?php endforeach; ?>
        </select>
        <label><?= e(__('club.area.birth_date')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <input type="date" name="birth_date" id="birth_date" required value="<?= e($edit?->birth_date ?? '') ?>" max="<?= e(date('Y-m-d', strtotime('-2 years'))) ?>" style="flex:1;min-width:0;">
            <span id="age_class_display" class="age-class-badge" style="flex:0 0 20%;text-align:center;">—</span>
        </div>

        <label><?= e(__('club.area.weight_kg')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <div class="weight-slider-group" style="flex:1;min-width:0;">
                <input type="number" name="weight_kg" min="0.1" max="200" step="0.1" required value="<?= e($edit?->weight_kg ?? '') ?>" class="weight-input" style="width:100px;flex-shrink:0;">
                <input type="range" min="0" max="200" step="0.1" value="<?= e($edit?->weight_kg ?? '') ?>" class="weight-slider" style="flex:1;min-width:0;">
            </div>
            <span id="weight_category_display" class="weight-category-badge" style="flex:0 0 20%;text-align:center;">—</span>
        </div>

        <label><?= e(__('club.area.belt')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
        <select name="belt" required>
            <option value="">—</option>
            <?php foreach (App\Model\Belt::cases() as $beltEnum) : ?>
                <option value="<?= e($beltEnum->value) ?>" <?= ($edit?->belt ?? '') === $beltEnum->value ? 'selected' : '' ?>><?= $beltEnum->circleLabel(App\Localization::getLocale()) ?></option>
            <?php endforeach; ?>
        </select>

        <label><?= e(__('club.area.membership_number')) ?></label>
        <input name="membership_number" value="<?= e($edit?->membership_number ?? '') ?>">

        <label><?= e(__('club.area.notes')) ?></label>
        <textarea name="notes" rows="3"><?= e($edit?->notes ?? '') ?></textarea>

        <button class="btn green" type="submit"><?= e(__('club.area.save_athlete')) ?></button>
    </form>
    <script>
    (function() {
        const form = document.querySelector('.form-card');
        if (!form) return;

        const slider = form.querySelector('.weight-slider');
        const numberInput = form.querySelector('.weight-input');
        if (slider && numberInput) {
            slider.addEventListener('input', function () {
                numberInput.value = this.value;
                updateWeightDisplay();
            });
            numberInput.addEventListener('input', function () {
                slider.value = this.value;
                updateWeightDisplay();
            });
        }

        form.addEventListener('submit', function () {
            const birthDate = this.querySelector('[name="birth_date"]');
            if (birthDate && birthDate.value) {
                birthDate.value = birthDate.value.split('T')[0];
            }
        });

        const ageClasses = <?= App\Model\AgeClass::definitionsJson(App\Localization::getLocale()) ?>;
        const dobInput = document.getElementById('birth_date');
        const ageDisplay = document.getElementById('age_class_display');
        const eventYear = new Date().getFullYear();

        function computeAgeClass(birthDate) {
            if (!birthDate) return null;
            const year = parseInt(birthDate.substring(0, 4), 10);
            if (isNaN(year)) return null;
            const age = eventYear - year;
            if (age < 0) return null;
            for (const ac of ageClasses) {
                if (age >= ac.ageMin && (ac.ageMax === null || age <= ac.ageMax)) {
                    return ac;
                }
            }
            if (ageClasses.length > 0 && age < ageClasses[0].ageMin) {
                return ageClasses[0];
            }
            return null;
        }

        function updateAgeDisplay() {
            const ac = computeAgeClass(dobInput.value);
            if (ac) {
                ageDisplay.textContent = ac.label;
                ageDisplay.className = 'age-class-badge has-value';
            } else {
                ageDisplay.textContent = '—';
                ageDisplay.className = 'age-class-badge';
            }
        }

        if (dobInput) {
            dobInput.addEventListener('change', updateAgeDisplay);
            dobInput.addEventListener('input', updateAgeDisplay);
            updateAgeDisplay();
        }

        const weightDefs = <?= \App\Model\JudoCategory::weightCategoryDefinitionsJson() ?>;
        const weightInput = form.querySelector('.weight-input');
        const genderInput = form.querySelector('[name="gender"]');
        const weightDisplay = document.getElementById('weight_category_display');

        function computeWeightCategory(weightStr, genderVal) {
            if (!weightStr || !genderVal) return null;
            const weight = parseFloat(weightStr);
            if (isNaN(weight) || weight <= 0) return null;
            const dob = dobInput ? dobInput.value : '';
            const ageClass = computeAgeClass(dob);
            if (!ageClass) return null;

            const gender = genderVal.toUpperCase();
            const classKey = weightDefs.aliases[ageClass.key] ?? ageClass.key;
            const definition = weightDefs.limits[classKey] ?? null;
            if (!definition) return null;
            const limits = definition['*'] ?? definition[gender] ?? null;
            if (!limits) return null;
            for (const limit of limits) {
                if (weight <= limit) return '-' + limit + ' kg';
            }
            return '+' + limits[limits.length - 1] + ' kg';
        }

        function updateWeightDisplay() {
            const result = computeWeightCategory(weightInput ? weightInput.value : '', genderInput ? genderInput.value : '');
            if (result) {
                weightDisplay.textContent = result;
                weightDisplay.className = 'weight-category-badge has-value';
            } else {
                weightDisplay.textContent = '—';
                weightDisplay.className = 'weight-category-badge';
            }
        }

        if (weightInput && genderInput && weightDisplay) {
            weightInput.addEventListener('input', updateWeightDisplay);
            genderInput.addEventListener('change', updateWeightDisplay);
            if (dobInput) dobInput.addEventListener('input', updateWeightDisplay);
            if (dobInput) dobInput.addEventListener('change', updateWeightDisplay);
            updateWeightDisplay();
        }

    })();
    </script>
    <style>
    .weight-slider-group {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .weight-slider-group .weight-slider {
        flex: 1;
        min-width: 120px;
    }
    .weight-slider-group .weight-input {
        width: 100px;
        flex-shrink: 0;
    }
    .age-class-badge {
        display: inline-block;
        padding: 0.2em 0.6em;
        font-size: 0.85em;
        font-weight: 600;
        background: #f0f0f0;
        border-radius: 4px;
        color: #666;
        white-space: nowrap;
    }
    .age-class-badge.has-value {
        background: #d4edda;
        color: #155724;
    }
    .weight-category-badge {
        display: inline-block;
        padding: 0.2em 0.6em;
        font-size: 0.85em;
        font-weight: 600;
        background: #f0f0f0;
        border-radius: 4px;
        color: #666;
        white-space: nowrap;
    }
    .weight-category-badge.has-value {
        background: #d4edda;
        color: #155724;
    }
    </style>
</div>

<div class="card">
    <h3><?= e(__('club.area.athlete_archive')) ?> <span class="count-badge"><?= e((string) ($pagination['total'] ?? 0)) ?></span></h3>
    <?php if (is_array($athleteInlineFeedback ?? null)) : ?>
        <div class="notice<?= $athleteInlineFeedback['type'] === 'success' ? ' success' : '' ?>" role="status">
            <?= e($athleteInlineFeedback['message']) ?>
        </div>
    <?php endif; ?>
    <div
        class="table-scroll table-scroll--wide table-scroll--responsive"
        role="region"
        tabindex="0"
        aria-label="<?= e(__('club.area.athlete_archive')) ?>"
    >
        <table
            class="table-full responsive-table"
            data-sort-mode="server"
            data-sort-page-parameter="page"
            data-sort-default="athlete"
        >
        <thead>
            <tr>
                <th scope="col" data-sort-key="athlete"><?= e(__('club.area.table.athlete')) ?></th>
                <th scope="col" data-sort-key="gender"><?= e(__('club.area.table.gender')) ?></th>
                <th scope="col" data-sort-key="birth"><?= e(__('club.area.table.birth')) ?></th>
                <th scope="col" data-sort-key="age_class"><?= e(__('club.area.table.age_class')) ?></th>
                <th scope="col" data-sort-key="weight"><?= e(__('club.area.table.weight')) ?></th>
                <th scope="col" data-sort-key="belt"><?= e(__('club.area.table.belt')) ?></th>
                <th scope="col" data-sort-key="weight_category"><?= e(__('club.area.table.weight_category')) ?></th>
                <th scope="col" data-sortable="false"><?= e(__('club.area.table.actions')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($athletes)) : ?>
                <tr>
                    <td class="responsive-table__empty" colspan="8"><?= e(__('club.area.no_athletes')) ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($athletes as $athlete) : ?>
                    <?php
                    $_inlineFormId = 'athlete-inline-add-' . $athlete->id;
                    $_gender = $athlete->genderEnum();
                    ?>
                    <tr id="athlete-row-<?= (int) $athlete->id ?>" data-inline-edit-row>
                        <td data-label="<?= e(__('club.area.athlete')) ?>">
                            <span data-inline-display><?= e($athlete->last_name . ' ' . $athlete->first_name) ?></span>
                            <span class="inline-edit-stack" data-inline-editor>
                                <input class="inline-edit-control" form="<?= e($_inlineFormId) ?>" name="last_name" value="<?= e($athlete->last_name) ?>" aria-label="<?= e(__('club.area.last_name')) ?>" placeholder="<?= e(__('club.area.last_name')) ?>" required>
                                <input class="inline-edit-control" form="<?= e($_inlineFormId) ?>" name="first_name" value="<?= e($athlete->first_name) ?>" aria-label="<?= e(__('club.area.first_name')) ?>" placeholder="<?= e(__('club.area.first_name')) ?>" required>
                            </span>
                        </td>
                        <td data-label="<?= e(__('club.area.gender')) ?>">
                            <span data-inline-display>
                                <?php
                                $genderBadge = $_gender;
                                $genderBadgeFallback = $athlete->gender;
                                require dirname(__DIR__) . '/components/gender_badge.php';
                                ?>
                            </span>
                            <select class="inline-edit-control" data-inline-editor form="<?= e($_inlineFormId) ?>" name="gender" aria-label="<?= e(__('club.area.gender')) ?>" required>
                                <?php foreach (App\Model\Gender::cases() as $_genderOption) : ?>
                                    <option value="<?= e($_genderOption->value) ?>" <?= $athlete->gender === $_genderOption->value ? 'selected' : '' ?>><?= e($_genderOption->iconLabel()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td data-label="<?= e(__('club.area.birth')) ?>">
                            <time data-inline-display datetime="<?= e($athlete->birth_date) ?>"><?= e($athlete->birth_date) ?></time>
                            <input class="inline-edit-control inline-edit-control--date" data-inline-editor form="<?= e($_inlineFormId) ?>" type="date" name="birth_date" value="<?= e($athlete->birth_date) ?>" aria-label="<?= e(__('club.area.birth_date')) ?>" required>
                        </td>
                        <td data-label="<?= e(__('club.area.age_class')) ?>"><?= e($athlete->ageClassLabel()) ?></td>
                        <td data-label="<?= e(__('club.area.weight')) ?>">
                            <span data-inline-display>
                                <?= e($athlete->weight_kg !== null
                                    ? (string) $athlete->weight_kg
                                    : __('events.no_weight')) ?>
                            </span>
                            <input class="inline-edit-control inline-edit-control--number" data-inline-editor form="<?= e($_inlineFormId) ?>" type="number" name="weight_kg" min="0.1" max="200" step="0.1" value="<?= e($athlete->weight_kg !== null ? (string) $athlete->weight_kg : '') ?>" aria-label="<?= e(__('club.area.weight_kg')) ?>" required>
                        </td>
                        <td data-label="<?= e(__('club.area.belt')) ?>">
                            <span data-inline-display><?php require dirname(__DIR__) . '/components/belt_badge.php'; ?></span>
                            <select class="inline-edit-control" data-inline-editor form="<?= e($_inlineFormId) ?>" name="belt" aria-label="<?= e(__('club.area.belt')) ?>" required>
                                <?php foreach (App\Model\Belt::cases() as $_beltOption) : ?>
                                    <option value="<?= e($_beltOption->value) ?>" <?= $athlete->belt === $_beltOption->value ? 'selected' : '' ?>><?= e($_beltOption->circleLabel()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td data-label="<?= e(__('club.area.weight_category')) ?>">
                            <?= e($athleteCategories[$athlete->id]['weight_category'] ?? '') ?>
                        </td>
                        <td class="table-actions-cell" data-label="<?= e(__('club.area.actions')) ?>">
                            <div class="table-actions" data-inline-display>
                                <button class="btn green table-action-button" type="button" data-inline-edit aria-label="<?= e(__('tables.edit_row')) ?>" title="<?= e(__('tables.edit_row')) ?>"><span aria-hidden="true">✏️</span><span class="table-action-label"><?= e(__('tables.edit_row')) ?></span></button>
                                <a class="btn gray table-action-button" href="<?= e(base_url('/clubs/area?view=add&edit=' . (string) $athlete->id)) ?>" aria-label="<?= e(__('tables.full_edit')) ?>" title="<?= e(__('tables.full_edit')) ?>"><span aria-hidden="true">⚙️</span><span class="table-action-label"><?= e(__('tables.full_edit')) ?></span></a>
                                <form method="post" action="<?= e(base_url('/clubs/delete-athlete?')) ?>" onsubmit="return confirm('<?= e(__('club.area.confirm_delete_athlete')) ?>')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="athlete_id" value="<?= e((string) $athlete->id) ?>">
                                    <button class="btn red table-action-button" type="submit" aria-label="<?= e(__('club.area.delete')) ?>" title="<?= e(__('club.area.delete')) ?>"><span aria-hidden="true">🗑️</span><span class="table-action-label"><?= e(__('club.area.delete')) ?></span></button>
                                </form>
                            </div>
                            <form id="<?= e($_inlineFormId) ?>" class="table-actions inline-edit-actions" data-inline-editor method="post" action="<?= e(base_url('/clubs/athletes/update-inline')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="athlete_id" value="<?= (int) $athlete->id ?>">
                                <input type="hidden" name="membership_number" value="<?= e($athlete->membership_number ?? '') ?>">
                                <input type="hidden" name="notes" value="<?= e($athlete->notes ?? '') ?>">
                                <input type="hidden" name="return_view" value="add">
                                <input type="hidden" name="page" value="<?= (int) ($pagination['page'] ?? 1) ?>">
                                <input type="hidden" name="sort" value="<?= e($tableSort['column']) ?>">
                                <input type="hidden" name="direction" value="<?= e($tableSort['direction']) ?>">
                                <button class="btn green table-action-button" type="submit" aria-label="<?= e(__('tables.save')) ?>" title="<?= e(__('tables.save')) ?>"><span aria-hidden="true">💾</span><span class="table-action-label"><?= e(__('tables.save')) ?></span></button>
                                <button class="btn gray table-action-button" type="button" data-inline-cancel aria-label="<?= e(__('tables.cancel')) ?>" title="<?= e(__('tables.cancel')) ?>"><span aria-hidden="true">↩️</span><span class="table-action-label"><?= e(__('tables.cancel')) ?></span></button>
                                <a class="btn table-action-button" href="<?= e(base_url('/clubs/area?view=add&edit=' . (string) $athlete->id)) ?>" aria-label="<?= e(__('tables.full_edit')) ?>" title="<?= e(__('tables.full_edit')) ?>"><span aria-hidden="true">⚙️</span><span class="table-action-label"><?= e(__('tables.full_edit')) ?></span></a>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        </table>
    </div>
    <?= $pagination['links'] ?? '' ?>
</div>
