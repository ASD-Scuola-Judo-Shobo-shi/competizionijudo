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

        <label><?= e(__('club.area.last_name')) ?></label>
        <input name="last_name" required value="<?= e($edit?->last_name ?? '') ?>">

        <label><?= e(__('club.area.first_name')) ?></label>
        <input name="first_name" required value="<?= e($edit?->first_name ?? '') ?>">

        <label><?= e(__('club.area.gender')) ?></label>
        <select name="gender" required>
            <option value="">—</option>
            <?php foreach (App\Model\Gender::cases() as $genderEnum) : ?>
                <option value="<?= e($genderEnum->value) ?>" <?= ($edit?->gender ?? '') === $genderEnum->value ? 'selected' : '' ?>><?= $genderEnum->iconLabel(App\Localization::getLocale()) ?></option>
            <?php endforeach; ?>
        </select>
        <label><?= e(__('club.area.birth_date')) ?></label>
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <input type="date" name="date_of_birth" id="date_of_birth" required value="<?= e($edit?->date_of_birth ?? '') ?>" max="<?= e(date('Y-m-d', strtotime('-2 years'))) ?>" style="flex:1;min-width:0;">
            <span id="age_class_display" class="age-class-badge" style="flex:0 0 20%;text-align:center;">—</span>
        </div>

        <label><?= e(__('club.area.weight_kg')) ?></label>
        <div style="display:flex;align-items:center;gap:0.5rem;">
            <div class="weight-slider-group" style="flex:1;min-width:0;">
                <input type="number" name="weight_kg" min="0.1" max="200" step="0.1" required value="<?= e($edit?->weight_kg ?? '') ?>" class="weight-input" style="width:100px;flex-shrink:0;">
                <input type="range" min="0" max="200" step="0.1" value="<?= e($edit?->weight_kg ?? '') ?>" class="weight-slider" style="flex:1;min-width:0;">
            </div>
            <span id="weight_category_display" class="weight-category-badge" style="flex:0 0 20%;text-align:center;">—</span>
        </div>

        <label><?= e(__('club.area.belt')) ?></label>
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
            const dob = this.querySelector('[name="date_of_birth"]');
            if (dob && dob.value) {
                dob.value = dob.value.split('T')[0];
            }
        });

        const ageClasses = <?= App\Model\AgeClass::definitionsJson(App\Localization::getLocale()) ?>;
        const dobInput = document.getElementById('date_of_birth');
        const ageDisplay = document.getElementById('age_class_display');
        const eventYear = new Date().getFullYear();

        function computeAgeClass(birthDate) {
            if (!birthDate) return null;
            const year = parseInt(birthDate.substring(0, 4), 10);
            if (isNaN(year)) return null;
            const age = eventYear - year;
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
                const range = ac.ageMin === ac.ageMax || ac.ageMax === null
                    ? (ac.ageMin >= 36 ? ac.ageMin + '+' : String(ac.ageMin))
                    : ac.ageMin + '-' + ac.ageMax + ' anni';
                ageDisplay.textContent = ac.name + ' ' + range;
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
            if (!dob) return null;
            const birthYear = parseInt(dob.substring(0, 4), 10);
            if (isNaN(birthYear)) return null;
            const age = eventYear - birthYear;

            let className = null;
            for (const ac of ageClasses) {
                if (age >= ac.ageMin && (ac.ageMax === null || age <= ac.ageMax)) {
                    className = ac.name;
                    break;
                }
            }
            if (!className) {
                if (ageClasses.length > 0 && age < ageClasses[0].ageMin) {
                    className = ageClasses[0].name;
                }
            }
            if (!className) return null;

            const gender = genderVal.toUpperCase();
            const childClassKey = weightDefs.childMap[className] ?? null;
            if (childClassKey !== null && weightDefs.child[childClassKey]) {
                const limits = weightDefs.child[childClassKey];
                for (const limit of limits) {
                    if (weight <= limit) return '-' + limit + ' kg';
                }
                return '+' + limits[limits.length - 1] + ' kg';
            }

            const adultMapVal = weightDefs.adultMap[className] ??
                (className.startsWith('Master') || className.startsWith('Masters') ? 'Senior' : null);
            if (adultMapVal !== null && weightDefs.adult[adultMapVal] && weightDefs.adult[adultMapVal][gender]) {
                const limits = weightDefs.adult[adultMapVal][gender];
                for (const limit of limits) {
                    if (weight <= limit) return '-' + limit + ' kg';
                }
                return '+' + limits[limits.length - 1] + ' kg';
            }

            return null;
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
    .belt-badge {
        display: inline-block;
        padding: 0.2em 0.6em;
        font-size: 0.85em;
        font-weight: 600;
        border-radius: 4px;
        white-space: nowrap;
    }
    </style>
</div>

<div class="card">
    <h3><?= e(__('club.area.athlete_archive')) ?> <span class="count-badge"><?= e((string) ($pagination['total'] ?? 0)) ?></span></h3>
    <table class="table-full">
        <thead>
            <tr>
                <th><?= e(__('club.area.athlete')) ?></th>
                <th><?= e(__('club.area.gender')) ?></th>
                <th><?= e(__('club.area.birth')) ?></th>
                <th><?= e(__('club.area.age_class')) ?></th>
                <th><?= e(__('club.area.weight')) ?></th>
                <th><?= e(__('club.area.belt')) ?></th>
                <th><?= e(__('club.area.weight_category')) ?></th>
                <th><?= e(__('club.area.actions')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($athletes)) : ?>
                <tr>
                    <td colspan="8"><?= e(__('club.area.no_athletes')) ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($athletes as $athlete) : ?>
                    <tr>
                        <td><?= e($athlete->last_name . ' ' . $athlete->first_name) ?></td>
                        <td><?= $athlete->genderIconLabel(App\Localization::getLocale()) ?></td>
                        <td><?= e($athlete->date_of_birth) ?></td>
                        <td><?= e($athlete->ageClassLabel()) ?></td>
                        <td><?= e((string) $athlete->weight_kg) ?></td>
                        <td>
                            <?php foreach ($athlete->beltEnum()?->components() ?? [['label' => $athlete->beltLabel(App\Localization::getLocale()), 'color' => '#ccc', 'textColor' => '#000']] as $component) : ?>
                                <span class="belt-badge" style="background-color: <?= e($component['color']) ?>; color: <?= e($component['textColor']) ?>"><?= e($component['label']) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td><?= e($athlete->weight_category) ?></td>
                        <td>
                            <a class="btn btn-sm" href="/club_area.php?view=add&edit=<?= e((string) $athlete->id) ?>"><?= e(__('club.area.edit')) ?></a>
                            <form method="post" action="/club_delete_athlete.php" style="display:inline" onsubmit="return confirm('<?= e(__('club.area.confirm_delete_athlete')) ?>')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="athlete_id" value="<?= e((string) $athlete->id) ?>">
                                <button class="btn btn-sm red" type="submit"><?= e(__('club.area.delete')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?= $pagination['links'] ?? '' ?>
</div>
