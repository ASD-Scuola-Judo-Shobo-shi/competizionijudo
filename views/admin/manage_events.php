<?php /** @var list<\App\Model\Event> $events */ ?>
<?php /** @var array{page: int, per_page: int, total: int, last_page: int, offset: int, links: string} $pagination */ ?>
<div class="card">
    <h2><?= e(__('admin.events.title')) ?> <span class="count-badge"><?= e((string) ($pagination['total'] ?? 0)) ?></span></h2>
    <table class="table-full">
        <thead>
            <tr>
                <th><?= e(__('admin.events.name')) ?></th>
                <th><?= e(__('admin.events.date')) ?></th>
                <th><?= e(__('admin.events.location')) ?></th>
                <th><?= e(__('admin.events.type')) ?></th>
                <th><?= e(__('admin.events.clubs_athletes')) ?></th>
                <th><?= e(__('admin.events.status')) ?></th>
                <th><?= e(__('admin.events.actions')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($events as $event) : ?>
                <tr>
                    <td><?= e($event->name) ?></td>
                    <td><?= e($event->date) ?></td>
                    <td><?= e($event->location) ?></td>
                    <td><?= e(__('events.type.' . $event->type)) ?></td>
                    <td><?= e(($entry_counts[$event->id]['clubs'] ?? 0) . ' / ' . ($entry_counts[$event->id]['athletes'] ?? 0)) ?></td>
                    <td>
                        <?php if ($event->published) : ?>
                            <span class="badge green"><?= e(__('admin.events.published')) ?></span>
                        <?php else : ?>
                            <span class="badge gray"><?= e(__('admin.events.hidden')) ?></span>
                        <?php endif; ?>
                        <?php if ($event->closed) : ?>
                            <span class="badge red"><?= e(__('admin.events.closed')) ?></span>
                        <?php else : ?>
                            <span class="badge blue"><?= e(__('admin.events.open')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="btn btn-sm green" href="/admin_add_event.php?event_id=<?= (int) $event->id ?>"><?= e(__('admin.events.edit')) ?></a>
                        <form method="post" action="/admin_delete_event.php" style="display:inline" onsubmit="return confirm('<?= e(__('admin.events.confirm_delete')) ?>')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="event_id" value="<?= (int) $event->id ?>">
                            <button class="btn btn-sm red" type="submit"><?= e(__('admin.events.delete')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($events)) : ?>
                <tr><td colspan="7"><?= e(__('admin.events.empty')) ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?= $pagination['links'] ?? '' ?>
</div>
