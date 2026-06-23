<?php

/** @var \App\Model\Event $event */
/** @var array $athletes */
/** @var int[] $registered */
/** @var list<\App\Model\Event> $nextEvents */
/** @var list<\App\Model\Event> $upcomingEvents */
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
            </table>

            <div class="event-details-actions">
                <?php if (!empty($warning)) : ?>
                    <div class="notice"><?= e($warning) ?></div>
                <?php endif; ?>
                <?php if (empty($athletes)) : ?>
                    <p><?= e(__('events.register_no_athletes')) ?></p>
                <?php else : ?>
                    <form method="post">
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
                                            <?php if (in_array($athlete->id, $registered, true)) : ?>
                                                <?= e(__('events.already_registered')) ?>
                                            <?php else : ?>
                                                <input type="checkbox" name="athletes[]" value="<?= e($athlete->id) ?>">
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e($athlete->last_name . ' ' . $athlete->first_name) ?></td>
                                        <td><?= e($athlete->date_of_birth) ?></td>
                                        <td><?= e((string) $athlete->weight_kg) ?></td>
                                        <td><?= e($athlete->weight_category) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <button class="btn green" type="submit"><?= e(__('events.register_submit')) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <?php if ($event === null) : ?>
        <p><?= e(__('events.select_competition')) ?></p>
    <?php endif; ?>
    <h3><?= e($event !== null ? __('events.upcoming_events') : __('events.upcoming_heading')) ?></h3>
    <?php
    $eventsList = $event !== null ? $nextEvents : $upcomingEvents;
    ?>
    <?php if (!empty($eventsList)) : ?>
        <?php foreach ($eventsList as $next) : ?>
            <div class="event-line">
                <a class="btn green btn-sm event-details-btn" href="/event_register.php?id=<?= e($next->id) ?>"><?= e(__('events.registration')) ?></a>
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
