<?php

declare(strict_types=1);

/** @var list<\App\Model\Event> $events */
/** @var array{links:string} $pagination */
?>
<div class="card">
    <h2><?= e(__('club_events.title')) ?></h2>
    <p><?= e(__('club_events.help')) ?></p>
    <p><a class="btn green" href="<?= e(base_url('/clubs/events/details')) ?>"><?= e(__('club_events.create')) ?></a></p>
    <div class="table-scroll" role="region" tabindex="0" aria-label="<?= e(__('club_events.title')) ?>">
        <table>
            <thead><tr>
                <th scope="col"><?= e(__('admin.event_details.name')) ?></th>
                <th scope="col"><?= e(__('admin.event_details.date')) ?></th>
                <th scope="col"><?= e(__('admin.event_details.published')) ?></th>
                <th scope="col"><?= e(__('admin.event_details.closed')) ?></th>
                <th scope="col" data-sortable="false"><?= e(__('club_events.actions')) ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($events as $event) : ?>
                <tr>
                    <td><?= e($event->name) ?></td>
                    <td><?= e($event->date) ?></td>
                    <td><?= e(__($event->published ? 'club_events.yes' : 'club_events.no')) ?></td>
                    <td><?= e(__($event->closed ? 'club_events.yes' : 'club_events.no')) ?></td>
                    <td>
                        <a class="btn btn-sm" href="<?= e(base_url('/clubs/events/details?id=' . $event->id)) ?>"><?= e(__('admin.edit.title')) ?></a>
                        <form method="post" action="<?= e(base_url('/clubs/events/delete')) ?>" data-confirm="<?= e(__('club_events.delete_confirm')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="event_id" value="<?= $event->id ?>">
                            <button class="btn red btn-sm" type="submit"><?= e(__('club_events.delete')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($events === []) : ?>
                <tr><td colspan="5"><?= e(__('club_events.empty')) ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?= $pagination['links'] ?>
</div>
