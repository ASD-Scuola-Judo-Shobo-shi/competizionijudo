<?php

/** @var \App\Model\Event|null $event */
/** @var string|null $error */
/** @var list<string> $locations */
/** @var list<array{id: int, name: string}> $clubs */
/** @var list<array<string, mixed>>|null $enrolledClubs */
/** @var list<array<string, mixed>>|null $enrolledAthletes */
/** @var list<string>|null $enrollmentFields */
/** @var int|null $selectedEnrollmentClubId */
/** @var array{page:int, per_page:int, total:int, last_page:int, offset:int, links:string}|null $enrollmentPagination */
/** @var list<int> $exceptionClubIds */
/** @var list<array{id:int|null, name:string, fee_amount:string, fee_cents:int|null, is_default:bool}>|null $formRegistrationOptions */
/** @var string|null $formSepaAccountHolder */
/** @var string|null $formSepaIban */
/** @var string|null $formSepaBic */
/** @var list<\App\Model\Event> $upcomingEvents */
/** @var string $cspNonce */
$isEdit = !empty($event);
$formRegistrationOptions ??= [[
    'id' => null,
    'name' => '',
    'fee_amount' => '',
    'fee_cents' => null,
    'is_default' => true,
]];
$formSepaAccountHolder ??= $event?->sepa_account_holder ?? '';
$formSepaIban ??= $event?->sepa_iban ?? '';
$formSepaBic ??= $event?->sepa_bic ?? '';
$enrolledAthletes ??= [];
$enrolledClubs ??= [];
$enrollmentFields ??= [];
$selectedEnrollmentClubId ??= null;
$enrollmentPagination ??= paginate(0, 1, 50, 'enrollment_page');
?>
<div class="card">
    <h2><?= $isEdit ? e(__('admin.edit.title')) . ' - ' . e($event->name) : e(__('admin.event_details.title')) ?></h2>

    <?php if ($error) : ?>
        <div class="notice"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="form-card" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= \App\Validation\EventInputValidator::MAX_UPLOAD_BYTES ?>">
        <input type="hidden" name="event_id" value="<?= e($event?->id ?? '') ?>">

        <label><?= e($isEdit ? __('admin.edit.name') : __('admin.event_details.name')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
        <input name="name" value="<?= e($event?->name ?? '') ?>" required>

        <label><?= e($isEdit ? __('admin.edit.date') : __('admin.event_details.date')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
        <input type="date" name="date" value="<?= e($event?->date ?? '') ?>" required>

        <label><?= e($isEdit ? __('admin.edit.location') : __('admin.event_details.location')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
        <input name="location" value="<?= e($event?->location ?? '') ?>" list="locations_list" required>
        <datalist id="locations_list">
            <?php foreach ($locations as $loc) : ?>
                <option value="<?= e($loc) ?>">
            <?php endforeach; ?>
        </datalist>

        <label><?= e($isEdit ? __('admin.edit.organizer') : __('admin.event_details.organizer')) ?></label>
        <input name="organizer" value="<?= e($event?->organizer ?? '') ?>">

        <label><?= e($isEdit ? __('admin.edit.registration_deadline') : __('admin.event_details.registration_deadline')) ?></label>
        <input type="date" name="registration_deadline" value="<?= e($event?->registration_deadline ?? '') ?>">

        <label><?= e($isEdit ? __('admin.edit.type') : __('admin.event_details.type')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
        <select name="type" required>
            <option value="">—</option>
            <option value="only_precompetitive" <?= ($event?->type ?? '') === 'only_precompetitive' ? 'selected' : '' ?>><?= e(__('events.type.only_precompetitive')) ?></option>
            <option value="only_competitive" <?= ($event?->type ?? '') === 'only_competitive' ? 'selected' : '' ?>><?= e(__('events.type.only_competitive')) ?></option>
            <option value="precompetitive_and_competitive" <?= ($event?->type ?? '') === 'precompetitive_and_competitive' ? 'selected' : '' ?>><?= e(__('events.type.precompetitive_and_competitive')) ?></option>
        </select>

        <label><?= e($isEdit ? __('admin.edit.description') : __('admin.event_details.description')) ?></label>
        <textarea name="description" rows="3"><?= e($event?->description ?? '') ?></textarea>

        <label><?= e($isEdit ? __('admin.edit.notes') : __('admin.event_details.notes')) ?></label>
        <p class="field-help" id="event-notes-help"><?= e(__('admin.event_details.notes_privacy_help')) ?></p>
        <textarea name="notes" rows="3" aria-describedby="event-notes-help"><?= e($event?->notes ?? '') ?></textarea>

        <label><?= e($isEdit ? __('admin.edit.max_participants') : __('admin.event_details.max_participants')) ?></label>
        <input type="number" name="max_participants" value="<?= e($event?->max_participants ? (string) $event->max_participants : '') ?>" min="1" placeholder="<?= e(__('admin.event_details.max_participants_placeholder')) ?>">

        <fieldset class="event-payment-fieldset">
            <legend><?= e(__('admin.event_details.registration_options')) ?></legend>
            <p class="field-help"><?= e(__('admin.event_details.registration_options_help')) ?></p>
            <div id="registration-options-list">
                <?php foreach ($formRegistrationOptions as $index => $option) : ?>
                    <div class="registration-option-row" data-option-index="<?= e((string) $index) ?>">
                        <input
                            type="hidden"
                            name="registration_options[<?= e((string) $index) ?>][id]"
                            value="<?= e($option['id'] !== null ? (string) $option['id'] : '') ?>"
                        >
                        <div>
                            <label for="registration-option-name-<?= e((string) $index) ?>">
                                <?= e(__('admin.event_details.registration_option_name')) ?>
                            </label>
                            <input
                                id="registration-option-name-<?= e((string) $index) ?>"
                                type="text"
                                name="registration_options[<?= e((string) $index) ?>][name]"
                                value="<?= e($option['name']) ?>"
                                maxlength="120"
                                required
                            >
                        </div>
                        <div>
                            <label for="registration-option-fee-<?= e((string) $index) ?>">
                                <?= e(__('admin.event_details.registration_option_fee')) ?>
                            </label>
                            <input
                                id="registration-option-fee-<?= e((string) $index) ?>"
                                type="number"
                                name="registration_options[<?= e((string) $index) ?>][fee_amount]"
                                value="<?= e($option['fee_amount']) ?>"
                                min="0"
                                max="42949672.95"
                                step="0.01"
                                inputmode="decimal"
                                required
                            >
                        </div>
                        <label class="registration-option-default">
                            <input
                                type="radio"
                                name="registration_option_default"
                                value="<?= e((string) $index) ?>"
                                <?= $option['is_default'] ? 'checked' : '' ?>
                                required
                            >
                            <?= e(__('admin.event_details.registration_option_default')) ?>
                        </label>
                        <button type="button" class="btn gray btn-sm registration-option-remove">
                            <?= e(__('admin.event_details.registration_option_remove')) ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm" id="add-registration-option-btn">
                <?= e(__('admin.event_details.registration_option_add')) ?>
            </button>
        </fieldset>

        <fieldset class="event-payment-fieldset">
            <legend><?= e(__('admin.event_details.sepa_payment_details')) ?></legend>
            <p class="field-help"><?= e(__('admin.event_details.sepa_payment_details_help')) ?></p>

            <label for="sepa-account-holder"><?= e(__('events.payment_account_holder')) ?></label>
            <input
                id="sepa-account-holder"
                name="sepa_account_holder"
                value="<?= e($formSepaAccountHolder) ?>"
                maxlength="70"
                autocomplete="organization"
            >

            <label for="sepa-iban"><?= e(__('events.payment_iban')) ?></label>
            <input
                id="sepa-iban"
                name="sepa_iban"
                value="<?= e($formSepaIban) ?>"
                maxlength="34"
                autocomplete="off"
            >

            <label for="sepa-bic"><?= e(__('events.payment_bic')) ?></label>
            <input
                id="sepa-bic"
                name="sepa_bic"
                value="<?= e($formSepaBic) ?>"
                maxlength="11"
                autocomplete="off"
            >
        </fieldset>

        <label><?= e($isEdit ? __('admin.edit.poster') : __('admin.event_details.poster')) ?></label>
        <input type="file" name="poster_file" accept=".pdf,.jpg,.jpeg,.png">
        <?php if ($isEdit && !empty($event->poster_file)) : ?>
            <p><a href="<?= e(base_url((string) $event->poster_file)) ?>" target="_blank"><?= e(__('events.view_current_poster')) ?></a></p>
        <?php endif; ?>

        <label><?= e($isEdit ? __('admin.edit.info_file') : __('admin.event_details.info_file')) ?></label>
        <input type="file" name="info_file" accept=".pdf,.jpg,.jpeg,.png">
        <?php if ($isEdit && !empty($event->info_file)) : ?>
            <p><a href="<?= e(base_url((string) $event->info_file)) ?>" target="_blank"><?= e(__('events.view_current_info')) ?></a></p>
        <?php endif; ?>

        <p class="checkbox-group">
            <label><input type="checkbox" name="published" value="1" <?= $isEdit && !empty($event->published) ? 'checked' : '' ?>> <?= e($isEdit ? __('admin.edit.published') : __('admin.event_details.published')) ?></label>
            <label><input type="checkbox" name="closed" value="1" <?= $isEdit && !empty($event->closed) ? 'checked' : '' ?>> <?= e($isEdit ? __('admin.edit.closed') : __('admin.event_details.closed')) ?></label>
        </p>

        <?php if (!empty($clubs)) : ?>
            <label><?= e(__('admin.event_details.registration_exceptions')) ?></label>
            <p class="form-note"><?= e(__('admin.event_details.registration_exceptions_help')) ?></p>
            <div class="checkbox-dropdown">
                <button type="button" class="dropdown-toggle btn btn-sm" data-dropdown-toggle>
                    <span><?= e(__('admin.event_details.select_clubs')) ?></span>
                    <span class="dropdown-arrow">▼</span>
                </button>
                <div class="dropdown-menu hidden">
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

        <button class="btn green" type="submit"><?= e($isEdit ? __('admin.edit.save') : __('admin.event_details.save')) ?></button>
    </form>
</div>
<?php if ($isEdit) : ?>
    <?php require __DIR__ . '/_event_enrolled_athletes.php'; ?>
<?php endif; ?>
<?php
$eventExceptions = [];
$upcomingEventAction = 'admin_details';
require dirname(__DIR__) . '/components/upcoming_events.php';
?>
<script nonce="<?= e($cspNonce) ?>">
    let registrationOptionIndex = <?= count($formRegistrationOptions) ?>;

    function refreshRegistrationOptionRows() {
        const container = document.getElementById('registration-options-list');
        if (!container) {
            return;
        }
        const rows = Array.from(container.querySelectorAll('.registration-option-row'));
        rows.forEach(function(row) {
            const button = row.querySelector('.registration-option-remove');
            if (button) {
                button.disabled = rows.length === 1;
            }
        });
    }

    function addRegistrationOptionRow() {
        const container = document.getElementById('registration-options-list');
        if (!container) {
            return;
        }
        const index = registrationOptionIndex++;
        const row = document.createElement('div');
        row.className = 'registration-option-row';
        row.dataset.optionIndex = String(index);
        row.innerHTML =
            '<input type="hidden" name="registration_options[' + index + '][id]" value="">' +
            '<div><label for="registration-option-name-' + index + '">' +
                <?= json_encode(__('admin.event_details.registration_option_name'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> +
            '</label><input id="registration-option-name-' + index + '" type="text" ' +
                'name="registration_options[' + index + '][name]" maxlength="120" required></div>' +
            '<div><label for="registration-option-fee-' + index + '">' +
                <?= json_encode(__('admin.event_details.registration_option_fee'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> +
            '</label><input id="registration-option-fee-' + index + '" type="number" ' +
                'name="registration_options[' + index + '][fee_amount]" min="0" max="42949672.95" ' +
                'step="0.01" inputmode="decimal" required></div>' +
            '<label class="registration-option-default"><input type="radio" ' +
                'name="registration_option_default" value="' + index + '" required> ' +
                <?= json_encode(__('admin.event_details.registration_option_default'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> +
            '</label>' +
            '<button type="button" class="btn gray btn-sm registration-option-remove">' +
                <?= json_encode(__('admin.event_details.registration_option_remove'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> +
            '</button>';
        container.appendChild(row);
        refreshRegistrationOptionRows();
        row.querySelector('input[type="text"]').focus();
    }

    document.addEventListener('DOMContentLoaded', function() {
        const addButton = document.getElementById('add-registration-option-btn');
        if (addButton) {
            addButton.addEventListener('click', addRegistrationOptionRow);
        }
        const container = document.getElementById('registration-options-list');
        if (container) {
            container.addEventListener('click', function(event) {
                const button = event.target.closest('.registration-option-remove');
                if (!button) {
                    return;
                }
                const row = button.closest('.registration-option-row');
                const wasDefault = row.querySelector('input[type="radio"]').checked;
                row.remove();
                if (wasDefault) {
                    const firstDefault = container.querySelector('input[name="registration_option_default"]');
                    if (firstDefault) {
                        firstDefault.checked = true;
                    }
                }
                refreshRegistrationOptionRows();
            });
        }
        refreshRegistrationOptionRows();
    });

    // Dropdown toggle for registration exceptions
    document.querySelectorAll('[data-dropdown-toggle]').forEach(function(button) {
        button.addEventListener('click', function() {
            const menu = this.nextElementSibling;
            if (menu && menu.classList.contains('dropdown-menu')) {
                menu.classList.toggle('hidden');
            }
        });
    });
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.checkbox-dropdown')) {
            document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
                menu.classList.add('hidden');
            });
        }
    });
</script>
