<?php /** @var \App\Model\Club $club */ /** @var string $error */ ?>
<?php
if (!isset($sardinianLocations)) {
    $sardinianLocations = \App\Model\SardinianLocation::all();
}
if (!isset($sardinianPostalCodes)) {
    $sardinianPostalCodes = \App\Model\SardinianLocation::postalCodes();
}
/** @var array<string, list<string>> $sardinianLocations */
/** @var array<string, array<string, string>> $sardinianPostalCodes */
/** @var string $cspNonce */
$affiliationOptions = $affiliationOptions ?? \App\Model\Affiliation::options();
?>
<div class="card">
    <h2><?= e(__('admin.clubs.edit_title')) ?> - <?= e($club->name) ?></h2>

    <?php if ($error) : ?>
        <div class="notice"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <div class="row">
            <div>
                <label><?= e(__('admin.clubs.name')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
                <input name="name" value="<?= e($club->name) ?>" required>
            </div>
            <div>
                <label><?= e(__('admin.clubs.email')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
                <input type="email" name="email" id="email" value="<?= e($club->email) ?>" required>
                <small class="field-warning" id="email-warning" role="status" aria-live="polite" hidden><?= e(__('admin.clubs.email_warning')) ?></small>
            </div>
            <div>
                <label><?= e(__('admin.clubs.federal_code')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
                <input name="federal_code" value="<?= e($club->federal_code) ?>" required>
            </div>
        </div>

        <div class="row">
            <div>
                <label><?= e(__('admin.clubs.phone')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
                <input name="phone" id="phone" inputmode="tel" required value="<?= e($club->phone) ?>">
                <small class="field-warning" id="phone-warning" role="status" aria-live="polite" hidden><?= e(__('admin.clubs.phone_warning')) ?></small>
            </div>
            <div>
                <label><?= e(__('admin.clubs.contact_first_name')) ?></label>
                <input name="contact_first_name" value="<?= e($club->contact_first_name) ?>">
            </div>
            <div>
                <label><?= e(__('admin.clubs.contact_last_name')) ?></label>
                <input name="contact_last_name" value="<?= e($club->contact_last_name) ?>">
            </div>
        </div>

        <div class="row">
            <div>
                <label><?= e(__('admin.clubs.address_line')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
                <input name="address_line" required value="<?= e($club->address_line ?? '') ?>">
            </div>
            <div>
                <label><?= e(__('admin.clubs.postal_code')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
                <input name="postal_code" id="postal_code" inputmode="numeric" pattern="[0-9]{5}" required value="<?= e($club->postal_code ?? '') ?>">
            </div>
            <div>
                <label><?= e(__('admin.clubs.province')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
                <select name="province" id="province" required>
                    <option value="">—</option>
                    <?php foreach (array_keys($sardinianLocations) as $province) : ?>
                        <option value="<?= e($province) ?>" <?= $club->province === $province ? 'selected' : '' ?>><?= e($province) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="row">
            <div>
                <label><?= e(__('admin.clubs.city')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
                <select name="city" id="city" required disabled data-selected-city="<?= e($club->city) ?>">
                    <option value="">—</option>
                </select>
            </div>
            <div>
                <label for="affiliation"><?= e(__('admin.clubs.affiliation')) ?></label>
                <div class="multi-select" data-multi-select>
                    <input
                        class="multi-select-input"
                        id="affiliation"
                        type="text"
                        readonly
                        placeholder="<?= e(__('admin.clubs.affiliation_placeholder')) ?>"
                        aria-controls="affiliation-options"
                        aria-expanded="false"
                        aria-haspopup="true"
                    >
                    <div class="multi-select-dropdown" id="affiliation-options" hidden>
                        <?php foreach ($affiliationOptions as $code => $label) : ?>
                            <label>
                                <input
                                    type="checkbox"
                                    name="affiliation[]"
                                    value="<?= e($code) ?>"
                                    data-affiliation-label="<?= e($code) ?>"
                                    <?= in_array($code, $club->affiliations(), true) ? 'checked' : '' ?>
                                >
                                <?= e($label) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div>
                <label><?= e(__('admin.clubs.password')) ?></label>
                <input type="password" name="password_hash" placeholder="..." autocomplete="new-password" minlength="<?= \App\Security\PasswordPolicy::MINIMUM_LENGTH ?>">
                <small><?= e(__('errors.password_too_short', [
                    'minimum' => (string) \App\Security\PasswordPolicy::MINIMUM_LENGTH,
                ])) ?></small>
            </div>
        </div>

        <button class="btn green" type="submit"><?= e(__('admin.clubs.save')) ?></button>
    </form>
    <script nonce="<?= e($cspNonce) ?>">
    (() => {
        const locations = <?= json_encode($sardinianLocations, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>;
        const postalCodes = <?= json_encode($sardinianPostalCodes, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>;
        const province = document.getElementById('province');
        const city = document.getElementById('city');
        const postalCode = document.getElementById('postal_code');
        const phone = document.getElementById('phone');
        const phoneWarning = document.getElementById('phone-warning');
        const email = document.getElementById('email');
        const emailWarning = document.getElementById('email-warning');
        const locationsByPostalCode = {};

        for (const [provinceName, cities] of Object.entries(postalCodes)) {
            for (const [cityName, code] of Object.entries(cities)) {
                if (locationsByPostalCode[code] === undefined) {
                    locationsByPostalCode[code] = { province: provinceName, city: cityName };
                } else {
                    locationsByPostalCode[code] = null;
                }
            }
        }

        function updateCities() {
            city.replaceChildren(new Option('—', ''));
            const cities = locations[province.value] ?? [];
            city.disabled = cities.length === 0;
            for (const name of cities) {
                city.add(new Option(name, name, false, name === city.dataset.selectedCity));
            }
        }

        province.addEventListener('change', () => {
            city.dataset.selectedCity = '';
            updateCities();
        });
        city.addEventListener('change', () => {
            const code = postalCodes[province.value]?.[city.value];
            if (code) {
                postalCode.value = code;
            }
        });
        postalCode.addEventListener('input', () => {
            const location = locationsByPostalCode[postalCode.value];
            if (location === null || location === undefined) {
                return;
            }

            province.value = location.province;
            city.dataset.selectedCity = location.city;
            updateCities();
        });

        function validatePhone() {
            const normalized = phone.value.replace(/[\s-]/g, '');
            const recognized = normalized === ''
                || /^\+[1-9]\d{7,14}$/.test(normalized)
                || /^3\d{9}$/.test(normalized)
                || /^(?:070|078[1-4]|0789|079)\d{5,8}$/.test(normalized);
            phoneWarning.hidden = recognized;
        }

        function normalizeAndValidateEmail() {
            email.value = email.value.trim().toLowerCase();
            emailWarning.hidden = email.value === '' || email.validity.valid;
        }

        function configureAffiliationDropdown() {
            const multiSelect = document.querySelector('[data-multi-select]');
            if (multiSelect === null) {
                return;
            }

            const input = multiSelect.querySelector('.multi-select-input');
            const dropdown = multiSelect.querySelector('.multi-select-dropdown');
            const checkboxes = [...multiSelect.querySelectorAll('input[type="checkbox"]')];
            if (input === null || dropdown === null) {
                return;
            }

            function updateInput() {
                input.value = checkboxes
                    .filter((checkbox) => checkbox.checked)
                    .map((checkbox) => checkbox.dataset.affiliationLabel)
                    .join(', ');
            }

            function setOpen(open) {
                dropdown.hidden = !open;
                input.setAttribute('aria-expanded', String(open));
            }

            input.addEventListener('click', () => setOpen(dropdown.hidden));
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    setOpen(false);
                } else if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    setOpen(dropdown.hidden);
                }
            });
            checkboxes.forEach((checkbox) => checkbox.addEventListener('change', updateInput));
            document.addEventListener('click', (event) => {
                if (!multiSelect.contains(event.target)) {
                    setOpen(false);
                }
            });
            updateInput();
        }

        phone.addEventListener('blur', validatePhone);
        email.addEventListener('blur', normalizeAndValidateEmail);
        updateCities();
        configureAffiliationDropdown();
    })();
    </script>
</div>
