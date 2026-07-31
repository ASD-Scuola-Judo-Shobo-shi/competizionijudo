<?php

/** @var \App\Model\Event|null $event */
/** @var list<\App\Model\Athlete> $athletes */
/** @var list<int> $registered */
/** @var list<\App\Model\Event> $upcomingEvents */
/** @var array<int, bool> $eventExceptions */
/** @var bool $hasRegistrationException */
/** @var array<string, mixed>|null $registrationFeedback */
/** @var array<int, array{age_below: int|null, type: string, weight_category: string}> $athleteCategories */
/** @var list<\App\Model\EventRegistrationOption> $registrationOptions */
/** @var int|null $defaultRegistrationOptionId */
/** @var array<int, array{athlete_id:int, athlete_name:string, option_id:int, option_name:string, fee_cents:int}> $registeredEnrollmentDetails */
$showRegistrationFeedback = $registrationFeedback !== null
    && empty($registrationFeedback['option_required_error'])
    && empty($registrationFeedback['option_configuration_error']);
$hasAthletesMissingWeight = false;
foreach ($athletes as $candidateAthlete) {
    if ($candidateAthlete->weight_kg === null || $candidateAthlete->weight_kg <= 0) {
        $hasAthletesMissingWeight = true;
        break;
    }
}
?>

<?php if ($event !== null) : ?>
    <div class="card event-details-card">
        <div class="event-details-layout">
            <div class="event-details-info">
                <table class="event-info-table">
                    <tr>
                        <td><strong><?= e(__('events.name')) ?>:</strong></td>
                        <td><?= e($event->name) ?></td>
                    </tr>
                    <tr>
                        <td><strong><?= e(__('events.date')) ?>:</strong></td>
                        <td><?= e($event->date) ?></td>
                    </tr>
                    <tr>
                        <td><strong><?= e(__('events.location')) ?>:</strong></td>
                        <td><?= e($event->location) ?></td>
                    </tr>
                    <?php if ($event->organizer) : ?>
                        <tr>
                            <td><strong><?= e(__('admin.add.organizer')) ?>:</strong></td>
                            <td><?= e($event->organizer) ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td><strong><?= e(__('events.type_label')) ?>:</strong></td>
                        <td><?= e(__('events.type.' . $event->type)) ?></td>
                    </tr>
                    <tr>
                        <td><strong><?= e(__('events.registration_deadline')) ?>:</strong></td>
                        <td><?= e($event->registration_deadline) ?></td>
                    </tr>
                    <?php if ($event->max_participants !== null) : ?>
                        <tr>
                            <td><strong><?= e(__('admin.events.max_participants')) ?>:</strong></td>
                            <td><?= e(__('events.max_participants_format', ['count' => (string) $event->max_participants])) ?></td>
                        </tr>
                    <?php endif; ?>
                </table>

                <div class="event-details-actions">
                    <?php if ($event->closed && !empty($athletes)) : ?>
                        <div class="notice" style="margin-bottom: 1rem; background: #fff4d7; border-color: #e9ce82;">
                            <?= e(__('events.registration_exception_notice')) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($registrationFeedback['option_required_error'])) : ?>
                        <div class="notice notice-error">
                            <?= e(__('events.registration_option_required')) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($registrationOptions === [] || !empty($registrationFeedback['option_configuration_error'])) : ?>
                        <div class="notice notice-error">
                            <?= e(__('events.registration_options_unavailable')) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($athletes)) : ?>
                        <p><?= e(__('events.register_no_athletes')) ?></p>
                    <?php elseif ($registrationOptions !== []) : ?>
                        <?php if ($hasAthletesMissingWeight) : ?>
                            <div class="notice warning">
                                <?= e(__('events.registration_missing_weight_notice')) ?>
                            </div>
                        <?php endif; ?>
                        <form method="post" id="registration-form">
                            <?= csrf_field() ?>
                            <p><?= e(__('events.register_select')) ?></p>
                            <div
                                class="table-scroll table-scroll--wide table-scroll--responsive"
                                role="region"
                                tabindex="0"
                                aria-label="<?= e(__('events.register_select')) ?>"
                            >
                                <table class="responsive-table">
                                <thead>
                                    <tr>
                                        <th scope="col"><?= e(__('admin.dashboard.actions')) ?></th>
                                        <th scope="col"><?= e(__('club.area.athlete')) ?></th>
                                        <th scope="col"><?= e(__('club.area.birth')) ?></th>
                                        <th scope="col"><?= e(__('club.area.weight')) ?></th>
                                        <th scope="col"><?= e(__('club.area.weight_category')) ?></th>
                                        <th scope="col"><?= e(__('events.registration_option_current')) ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($athletes as $athlete) : ?>
                                        <?php
                                        $athleteName = $athlete->last_name . ' ' . $athlete->first_name;
                                        $isRegistered = in_array($athlete->id, $registered, true);
                                        $hasWeight = $athlete->weight_kg !== null && $athlete->weight_kg > 0;
                                        $cannotRegister = !$hasWeight && !$isRegistered;
                                        ?>
                                        <tr>
                                            <td data-label="<?= e(__('admin.dashboard.actions')) ?>">
                                                <input type="checkbox"
                                                    name="athletes[]"
                                                    value="<?= e((string) $athlete->id) ?>"
                                                    <?= $isRegistered ? 'checked' : '' ?>
                                                    <?= $cannotRegister ? 'disabled' : '' ?>
                                                    aria-label="<?= e(__('events.register_select') . ': ' . $athleteName) ?>"
                                                    data-registered="<?= $isRegistered ? 'true' : 'false' ?>">
                                            </td>
                                            <td data-label="<?= e(__('club.area.athlete')) ?>"><?= e($athleteName) ?></td>
                                            <td data-label="<?= e(__('club.area.birth')) ?>">
                                                <?= e($athlete->birth_date) ?>
                                            </td>
                                            <td data-label="<?= e(__('club.area.weight')) ?>">
                                                <?php if ($hasWeight) : ?>
                                                    <?= e((string) $athlete->weight_kg) ?>
                                                <?php else : ?>
                                                    <span class="field-warning"><?= e(__('events.no_weight')) ?></span>
                                                    <a href="<?= e(base_url('/clubs/area?view=add&edit=' . $athlete->id)) ?>">
                                                        <?= e(__('events.registration_weight_required')) ?>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="<?= e(__('club.area.weight_category')) ?>">
                                                <?= e($athleteCategories[$athlete->id]['weight_category'] ?? '') ?>
                                            </td>
                                            <td data-label="<?= e(__('events.registration_option_current')) ?>">
                                                <?php if (isset($registeredEnrollmentDetails[$athlete->id])) : ?>
                                                    <?= e(
                                                        $registeredEnrollmentDetails[$athlete->id]['option_name']
                                                        . ' — '
                                                        . \App\Service\RegistrationPaymentService::formatAmount(
                                                            $registeredEnrollmentDetails[$athlete->id]['fee_cents']
                                                        )
                                                    ) ?>
                                                <?php else : ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                </table>
                            </div>

                            <div class="registration-option-selector">
                                <label for="registration_option_id">
                                    <?= e(__('events.registration_option')) ?>
                                    <span class="required-marker" aria-hidden="true">*</span>
                                </label>
                                <select id="registration_option_id" name="registration_option_id" required>
                                    <option value=""><?= e(__('events.registration_option_select')) ?></option>
                                    <?php foreach ($registrationOptions as $option) : ?>
                                        <option
                                            value="<?= e((string) $option->id) ?>"
                                            <?= $defaultRegistrationOptionId === $option->id ? 'selected' : '' ?>
                                        >
                                            <?= e(
                                                $option->name
                                                . ' — '
                                                . \App\Service\RegistrationPaymentService::formatAmount(
                                                    $option->fee_cents
                                                )
                                            ) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="field-help"><?= e(__('events.registration_option_help')) ?></p>
                            </div>

                            <div class="registration-actions">
                                <button class="btn green" type="submit" id="save-changes-btn" disabled><?= e(__('events.register_save_changes')) ?></button>
                                <?php if ($showRegistrationFeedback) : ?>
                                    <span class="change-indicator" id="feedback-indicator">
                                        <?php
                                        $parts = [];
                                        if (($registrationFeedback['added'] ?? 0) > 0) {
                                            $parts[] = __('events.registration_added', ['count' => (string) $registrationFeedback['added']]);
                                        }
                                        if (($registrationFeedback['removed'] ?? 0) > 0) {
                                            $parts[] = __('events.unregistration_removed', ['count' => (string) $registrationFeedback['removed']]);
                                        }
                                        if (($registrationFeedback['rejected'] ?? 0) > 0) {
                                            $parts[] = __('events.registration_rejected', ['count' => (string) $registrationFeedback['rejected']]);
                                        }
                                        if (($registrationFeedback['missing_weight'] ?? 0) > 0) {
                                            $parts[] = __('events.registration_missing_weight', ['count' => (string) $registrationFeedback['missing_weight']]);
                                        }
                                        if (($registrationFeedback['capacity_exceeded'] ?? 0) > 0) {
                                            $parts[] = __('events.registration_capacity_exceeded', ['count' => (string) $registrationFeedback['capacity_exceeded']]);
                                        }
                                        if (($registrationFeedback['failed'] ?? 0) > 0) {
                                            $parts[] = __('events.registration_failed', ['count' => (string) $registrationFeedback['failed']]);
                                        }
                                        echo e(implode('; ', $parts));
                                        ?>
                                    </span>
                                <?php else : ?>
                                    <span class="change-indicator" id="change-indicator" hidden>
                                        <?= e(__('events.register_unsaved_changes')) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if (($registrationFeedback['payment_summary'] ?? null) !== null) : ?>
                                <?php
                                $paymentSummary = $registrationFeedback['payment_summary'];
                                $newEnrollments = $paymentSummary['new_enrollments'] ?? [];
                                $removedEnrollments = $paymentSummary['removed_enrollments'] ?? [];
                                $paymentInfo = $paymentSummary['payment_info'] ?? [];
                                $amountDueCents = (int) ($paymentSummary['amount_due_cents'] ?? 0);
                                ?>
                                <section class="registration-payment-summary" aria-labelledby="payment-summary-title">
                                    <h3 id="payment-summary-title"><?= e(__('events.payment_summary_title')) ?></h3>
                                    <p>
                                        <strong><?= e(__('events.registration_option_selected')) ?>:</strong>
                                        <?= e((string) ($paymentSummary['selected_option_name'] ?? '')) ?>
                                        (<?= e(\App\Service\RegistrationPaymentService::formatAmount(
                                            (int) ($paymentSummary['selected_option_fee_cents'] ?? 0)
                                        )) ?>)
                                    </p>

                                    <div class="registration-change-grid">
                                        <div>
                                            <h4><?= e(__('events.payment_summary_new_enrollments')) ?></h4>
                                            <?php if ($newEnrollments === []) : ?>
                                                <p>—</p>
                                            <?php else : ?>
                                                <ul class="registration-change-list">
                                                    <?php foreach ($newEnrollments as $change) : ?>
                                                        <li>
                                                            <span>
                                                                <?= e((string) ($change['athlete_name'] ?? '')) ?>
                                                                — <?= e((string) ($change['option_name'] ?? '')) ?>
                                                            </span>
                                                            <strong>+<?= e(\App\Service\RegistrationPaymentService::formatAmount(
                                                                (int) ($change['fee_cents'] ?? 0)
                                                            )) ?></strong>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h4><?= e(__('events.payment_summary_removed_enrollments')) ?></h4>
                                            <?php if ($removedEnrollments === []) : ?>
                                                <p>—</p>
                                            <?php else : ?>
                                                <ul class="registration-change-list">
                                                    <?php foreach ($removedEnrollments as $change) : ?>
                                                        <li>
                                                            <span>
                                                                <?= e((string) ($change['athlete_name'] ?? '')) ?>
                                                                — <?= e((string) ($change['option_name'] ?? '')) ?>
                                                            </span>
                                                            <strong>−<?= e(\App\Service\RegistrationPaymentService::formatAmount(
                                                                (int) ($change['fee_cents'] ?? 0)
                                                            )) ?></strong>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <dl class="registration-payment-totals">
                                        <div>
                                            <dt><?= e(__('events.payment_summary_new_total')) ?></dt>
                                            <dd>+<?= e(\App\Service\RegistrationPaymentService::formatAmount(
                                                (int) ($paymentSummary['new_enrollment_cents'] ?? 0)
                                            )) ?></dd>
                                        </div>
                                        <div>
                                            <dt><?= e(__('events.payment_summary_removed_total')) ?></dt>
                                            <dd>−<?= e(\App\Service\RegistrationPaymentService::formatAmount(
                                                (int) ($paymentSummary['removed_enrollment_cents'] ?? 0)
                                            )) ?></dd>
                                        </div>
                                        <div class="registration-amount-due">
                                            <dt><?= e(__('events.amount_due')) ?></dt>
                                            <dd><?= e(\App\Service\RegistrationPaymentService::formatAmount(
                                                $amountDueCents
                                            )) ?></dd>
                                        </div>
                                        <?php if (($paymentSummary['credit_cents'] ?? 0) > 0) : ?>
                                            <div>
                                                <dt><?= e(__('events.payment_summary_credit')) ?></dt>
                                                <dd><?= e(\App\Service\RegistrationPaymentService::formatAmount(
                                                    (int) $paymentSummary['credit_cents']
                                                )) ?></dd>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <dt><?= e(__('events.payment_summary_total_athletes')) ?></dt>
                                            <dd><?= e((string) ($paymentSummary['total_athletes'] ?? 0)) ?></dd>
                                        </div>
                                    </dl>

                                    <?php if ($amountDueCents > 0) : ?>
                                        <div class="sepa-payment-layout">
                                            <div>
                                                <h4><?= e(__('events.payment_info')) ?></h4>
                                                <dl class="sepa-payment-details">
                                                    <div>
                                                        <dt><?= e(__('events.payment_account_holder')) ?></dt>
                                                        <dd><?= e((string) ($paymentInfo['account_holder'] ?? '')) ?></dd>
                                                    </div>
                                                    <div>
                                                        <dt><?= e(__('events.payment_iban')) ?></dt>
                                                        <dd><?= e((string) ($paymentInfo['iban'] ?? '')) ?></dd>
                                                    </div>
                                                    <?php if (!empty($paymentInfo['bic'])) : ?>
                                                        <div>
                                                            <dt><?= e(__('events.payment_bic')) ?></dt>
                                                            <dd><?= e((string) $paymentInfo['bic']) ?></dd>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <dt><?= e(__('events.payment_reason')) ?></dt>
                                                        <dd><?= e((string) ($paymentSummary['payment_reason'] ?? '')) ?></dd>
                                                    </div>
                                                </dl>
                                            </div>

                                            <?php if (!empty($paymentSummary['qr_code_data_uri'])) : ?>
                                                <figure class="epc-qr-code">
                                                    <img
                                                        src="<?= e((string) $paymentSummary['qr_code_data_uri']) ?>"
                                                        alt="<?= e(__('events.payment_qr_code_alt')) ?>"
                                                        width="240"
                                                        height="240"
                                                    >
                                                    <figcaption>
                                                        <strong><?= e(__('events.payment_qr_code')) ?></strong><br>
                                                        <?= e(__('events.payment_qr_code_help')) ?>
                                                    </figcaption>
                                                </figure>
                                            <?php else : ?>
                                                <p class="notice notice-error">
                                                    <?= e(__('events.payment_qr_code_unavailable')) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    <?php else : ?>
                                        <p class="notice success"><?= e(__('events.payment_not_required')) ?></p>
                                    <?php endif; ?>
                                </section>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('input[name="athletes[]"]');
            const saveBtn = document.getElementById('save-changes-btn');
            const changeIndicator = document.getElementById('change-indicator');

            function checkForChanges() {
                let hasChanges = false;
                checkboxes.forEach(cb => {
                    const wasRegistered = cb.dataset.registered === 'true';
                    const isNowChecked = cb.checked;
                    if (wasRegistered !== isNowChecked) {
                        hasChanges = true;
                    }
                });

                if (saveBtn) {
                    saveBtn.disabled = !hasChanges;
                }
                if (changeIndicator) {
                    changeIndicator.hidden = !hasChanges;
                }
            }

            checkboxes.forEach(cb => {
                cb.addEventListener('change', checkForChanges);
            });
        });
    </script>
<?php endif; ?>

<!-- Bottom Upcoming / Next Events Card -->
<?php if ($event === null && empty($upcomingEvents)) : ?>
    <div class="card">
        <p><?= e(__('events.none')) ?></p>
    </div>
<?php elseif (!empty($upcomingEvents)) : ?>
    <div class="card">
        <?php if ($event === null) : ?>
            <p><?= e(__('events.select_event')) ?></p>
        <?php endif; ?>

        <h3><?= e($event !== null ? __('events.upcoming_events') : __('events.upcoming_heading')) ?></h3>

        <?php foreach ($upcomingEvents as $next) : ?>
            <?php
            $hasException = !empty($eventExceptions[$next->id]);
            $canRegister = !$next->closed || $hasException;
            ?>
            <div class="event-line" style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <!-- Show Registration button if open OR if club has registration exception -->
                <?php if ($canRegister) : ?>
                    <a class="btn green btn-sm event-details-btn" href="<?= e(base_url('/events/register?event=' . (string) $next->id)) ?>">
                        <?= e(__('events.registration')) ?>
                    </a>
                <?php endif; ?>

                <!-- Display Closed Badge if event is closed -->
                <?php if ($next->closed) : ?>
                    <span class="badge badge-closed" style="background-color: #6c757d; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">
                        <?= e(__('events.closed')) ?>
                    </span>
                <?php endif; ?>

                <span>
                    <?= e($next->date) ?> - <?= e($next->name) ?> - <?= e($next->location) ?> - (<?= e(__('events.registration_deadline')) ?>: <?= e($next->registration_deadline) ?>)
                </span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
