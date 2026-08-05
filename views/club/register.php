<?php
/** @var array{name: string, federal_code: string, email: string, phone: string, address_line: string, postal_code: string, city: string, province: string, contact_first_name: string, contact_last_name: string, affiliation: list<string>, terms_accepted: bool, athlete_data_rights_declaration: bool} $formData */
/** @var string $cspNonce */
$formData = $formData ?? [
    'name' => '',
    'federal_code' => '',
    'email' => '',
    'phone' => '',
    'address_line' => '',
    'postal_code' => '',
    'city' => '',
    'province' => '',
    'contact_first_name' => '',
    'contact_last_name' => '',
    'affiliation' => [],
    'terms_accepted' => false,
    'athlete_data_rights_declaration' => false,
];
$sardinianLocations = $sardinianLocations ?? \App\Model\SardinianLocation::all();
$sardinianPostalCodes = $sardinianPostalCodes ?? \App\Model\SardinianLocation::postalCodes();
$affiliationOptions = $affiliationOptions ?? \App\Model\Affiliation::options();
?>
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
        <?php if (!empty($confirmation_link)) : ?>
            <p><a class="btn green" href="<?= e($confirmation_link) ?>"><?= e(__('club.register.confirm_now')) ?></a></p>
        <?php endif; ?>
        <a class="btn green" href="<?= e(base_url('/clubs/login?')) ?>"><?= e(__('buttons.back_to_login')) ?></a>
    <?php else : ?>
        <form method="post" class="form-card">
            <?= csrf_field() ?>
            <label><?= e(__('club.register.club_email')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
            <input type="email" name="email" id="email" required value="<?= e($formData['email']) ?>">
            <small class="field-warning" id="email-warning" role="status" aria-live="polite" hidden><?= e(__('club.register.email_warning')) ?></small>

            <label><?= e(__('club.register.password')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
            <input type="password" name="password" minlength="<?= \App\Security\PasswordPolicy::MINIMUM_LENGTH ?>" required>
            <small><?= e(__('errors.password_too_short', [
                'minimum' => (string) \App\Security\PasswordPolicy::MINIMUM_LENGTH,
            ])) ?></small>

            <label><?= e(__('club.register.confirm_password')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
            <input type="password" name="password2" minlength="<?= \App\Security\PasswordPolicy::MINIMUM_LENGTH ?>" required>

            <label><?= e(__('club.register.club_name')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
            <input name="name" required value="<?= e($formData['name']) ?>">

            <label><?= e(__('club.register.federal_code')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
            <input name="federal_code" required value="<?= e($formData['federal_code']) ?>">

            <label><?= e(__('club.register.club_phone')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
            <input name="phone" id="phone" inputmode="tel" required value="<?= e($formData['phone']) ?>">
            <small class="field-warning" id="phone-warning" role="status" aria-live="polite" hidden><?= e(__('club.register.phone_warning')) ?></small>

            <label><?= e(__('club.register.address_line')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
            <input name="address_line" required value="<?= e($formData['address_line']) ?>">

            <label><?= e(__('club.register.postal_code')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
            <input name="postal_code" id="postal_code" inputmode="numeric" pattern="[0-9]{5}" required value="<?= e($formData['postal_code']) ?>">

            <label><?= e(__('club.register.province')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
            <select name="province" id="province" required>
                <option value="">—</option>
                <?php foreach (array_keys($sardinianLocations) as $province) : ?>
                    <option value="<?= e($province) ?>" <?= $formData['province'] === $province ? 'selected' : '' ?>><?= e($province) ?></option>
                <?php endforeach; ?>
            </select>

            <label><?= e(__('club.register.city')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
            <select name="city" id="city" required disabled data-selected-city="<?= e($formData['city']) ?>">
                <option value="">—</option>
            </select>

            <label><?= e(__('club.register.contact_first_name')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
            <input name="contact_first_name" required value="<?= e($formData['contact_first_name']) ?>">

            <label><?= e(__('club.register.contact_last_name')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
            <input name="contact_last_name" required value="<?= e($formData['contact_last_name']) ?>">

            <label for="affiliation"><?= e(__('club.register.affiliation')) ?></label>
            <div class="multi-select" data-multi-select>
                <input
                    class="multi-select-input"
                    id="affiliation"
                    type="text"
                    readonly
                    placeholder="<?= e(__('club.register.affiliation_placeholder')) ?>"
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
                                <?= in_array($code, $formData['affiliation'], true) ? 'checked' : '' ?>
                            >
                            <?= e($label) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <label class="consent-field">
                <input type="checkbox" name="terms_accepted" value="1" required <?= $formData['terms_accepted'] ? 'checked' : '' ?>>
                <span>
                    <?= e(__('club.register.terms_acceptance', [
                        'version' => \App\Model\ClubTermsAcceptance::VERSION,
                    ])) ?>
                    <a href="<?= e(base_url('/terms')) ?>" target="_blank" rel="noopener noreferrer"><?= e(__('club.register.terms_link')) ?></a>
                    <span class="required-marker" aria-hidden="true">*</span>
                </span>
            </label>

            <label class="consent-field">
                <input type="checkbox" name="athlete_data_rights_declaration" value="1" required <?= $formData['athlete_data_rights_declaration'] ? 'checked' : '' ?>>
                <span>
                    <?= e(__('club.register.athlete_data_rights_declaration')) ?>
                    <a href="<?= e(base_url('/privacy')) ?>" target="_blank" rel="noopener noreferrer"><?= e(__('club.register.privacy_notice')) ?></a>
                    <span class="required-marker" aria-hidden="true">*</span>
                </span>
            </label>

            <button class="btn green" type="submit"><?= e(__('club.register.register_button')) ?></button>
            <a class="btn" href="<?= e(base_url('/clubs/login?')) ?>"><?= e(__('nav.club_login')) ?></a>
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
    <?php endif; ?>
</div>
