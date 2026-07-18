<?php
/** @var \App\Model\Event|null $event */
/** @var list<\App\Model\Athlete> $athletes */
/** @var list<int> $registered */
/** @var list<\App\Model\Event> $nextEvents */
/** @var list<\App\Model\Event> $upcomingEvents */
/** @var array{added?: int, already_registered?: int, rejected?: int, capacity_exceeded?: int, failed?: int, removed?: int, unsubscribed_failed?: int}|null $registrationFeedback */
/** @var array<int, array{age_below: int|null, program: string, weight_category: string}> $athleteCategories */
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
                    <td><strong><?= e(__('admin.events.type')) ?>:</strong></td>
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
        <?php if (empty($athletes)) : ?>
            <p><?= e(__('events.register_no_athletes')) ?></p>
        <?php else : ?>
            <form method="post" id="registration-form">
                        <?= csrf_field() ?>
                        <p><?= e(__('events.register_select')) ?></p>
                        <table>
                            <thead>
                                <tr>
                                    <th><?= e(__('admin.dashboard.actions')) ?></th>
                                    <th><?= e(__('club.area.athlete')) ?></th>
                                    <th><?= e(__('club.area.birth')) ?></th>
                                    <th><?= e(__('club.area.weight')) ?></th>
                                    <th><?= e(__('club.area.weight_category')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($athletes as $athlete) : ?>
                                    <tr>
                                        <td>
                                            <?php $isRegistered = in_array($athlete->id, $registered, true); ?>
                                            <input type="checkbox" 
                                                name="athletes[]" 
                                                value="<?= e((string) $athlete->id) ?>" 
                                                <?= $isRegistered ? 'checked' : '' ?>
                                                data-registered="<?= $isRegistered ? 'true' : 'false' ?>">
                                        </td>
                                        <td><?= e($athlete->last_name . ' ' . $athlete->first_name) ?></td>
                                        <td><?= e($athlete->date_of_birth) ?></td>
                                        <td><?= e((string) $athlete->weight_kg) ?></td>
                                        <td><?= e($athleteCategories[$athlete->id]['weight_category'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="registration-actions">
                            <button class="btn green" type="submit" id="save-changes-btn" disabled><?= e(__('events.register_save_changes')) ?></button>
                            <?php if ($registrationFeedback !== null) : ?>
                                <span class="change-indicator" id="feedback-indicator" style="margin-left: 0.5rem;">
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
                                <span class="change-indicator" id="change-indicator" style="display: none; margin-left: 0.5rem;">
                                    <?= e(__('events.register_unsaved_changes')) ?>
                                </span>
                            <?php endif; ?>
                        </div>
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
        
        saveBtn.disabled = !hasChanges;
        if (changeIndicator) {
            changeIndicator.style.display = hasChanges ? 'inline' : 'none';
        }
    }
    
    checkboxes.forEach(cb => {
        cb.addEventListener('change', checkForChanges);
    });
});
</script>

<?php endif; ?>

<div class="card">
    <?php if ($event === null) : ?>
        <p><?= e(__('events.select_event')) ?></p>
    <?php endif; ?>
    <h3><?= e($event !== null ? __('events.upcoming_events') : __('events.upcoming_heading')) ?></h3>
    <?php
    $eventsList = $event !== null ? $nextEvents : $upcomingEvents;
    ?>
    <?php if (!empty($eventsList)) : ?>
        <?php foreach ($eventsList as $next) : ?>
            <div class="event-line">
                <a class="btn green btn-sm event-details-btn" href="<?= e(base_url('/events/register?event=' . (string) $next->id)) ?>"><?= e(__('events.registration')) ?></a>
                <?= e($next->date) ?>
                - <?= e($next->name) ?>
                - <?= e($next->location) ?>
                - (<?= e(__('events.registration_deadline')) ?>: <?= e($next->registration_deadline) ?>)
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <p><?= e(__('events.none')) ?></p>
    <?php endif; ?>
</div>
