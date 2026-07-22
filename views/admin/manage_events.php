<?php /** @var list<\App\Model\Event> $events */ ?>
<?php /** @var array{page: int, per_page: int, total: int, last_page: int, offset: int, links: string} $pagination */ ?>
<div class="card">
    <h2><?= e(__('admin.events.title')) ?> <span class="count-badge"><?= e((string) $pagination['total']) ?></span></h2>
    <table class="table-full admin-events-table">
        <thead>
            <tr>
                <th><?= e(__('admin.events.name')) ?></th>
                <th><?= e(__('admin.events.date')) ?></th>
                <th><?= e(__('admin.events.location')) ?></th>
                <th><?= e(__('admin.events.indicators')) ?></th>
                <th><?= e(__('admin.events.clubs_athletes')) ?></th>
                <th><?= e(__('admin.events.actions')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($events as $event) : ?>
                <tr>
                    <td class="truncate" title="<?= e($event->name) ?>"><?= e($event->name) ?></td>
                    <td><?= e($event->date) ?></td>
                    <td class="truncate" title="<?= e($event->location) ?>"><?= e($event->location) ?></td>
                    <td>
                        <?php
                        $typeEmoji = match($event->type) {
                            'precompetitive' => '🎖️',
                            'competitive' => '🏅',
                            'both' => '🏆',
                            default => '',
                        };
                        ?>
                        <span class="event-indicator emoji-tooltip" data-tooltip="<?= e(__('admin.events.type_tooltip.' . $event->type)) ?>"><?= e($typeEmoji) ?></span>
                        <?php if ($event->published) : ?>
                            <span class="event-indicator emoji-tooltip" data-tooltip="<?= e(__('admin.events.published_tooltip')) ?>"><?= e('👁️') ?></span>
                        <?php else : ?>
                            <span class="event-indicator emoji-tooltip" data-tooltip="<?= e(__('admin.events.hidden_tooltip')) ?>"><?= e('🚫') ?></span>
                        <?php endif; ?>
                        <?php if ($event->closed) : ?>
                            <span class="event-indicator emoji-tooltip" data-tooltip="<?= e(__('admin.events.closed_tooltip')) ?>"><?= e('🔒') ?></span>
                        <?php else : ?>
                            <span class="event-indicator emoji-tooltip" data-tooltip="<?= e(__('admin.events.open_tooltip')) ?>"><?= e('⬇️') ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= e(($entry_counts[$event->id]['clubs'] ?? 0) . ' | ' . ($entry_counts[$event->id]['athletes'] ?? 0)) ?>
                        <?php if ($event->max_participants !== null) : ?>
                            / <?= e((string) $event->max_participants) ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="btn btn-sm green" href="<?= e(base_url('/admin/events/add?event_id=' . (int) $event->id)) ?>"><?= e(__('admin.events.edit')) ?></a>
                        <form method="post" action="<?= e(base_url('/admin/events/delete?')) ?>" style="display:inline" onsubmit="return confirm('<?= e(__('admin.events.confirm_delete')) ?>')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="event_id" value="<?= (int) $event->id ?>">
                            <button class="btn btn-sm red" type="submit"><?= e(__('admin.events.delete')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($events)) : ?>
                <tr><td colspan="6"><?= e(__('admin.events.empty')) ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?= $pagination['links'] ?>
</div>

<style>
.event-indicator {
    margin-right: 4px;
    font-size: 1.1em;
}

.event-indicator:last-child {
    margin-right: 0;
}

.emoji-tooltip {
    cursor: default;
    position: relative;
}

.emoji-tooltip:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    left: 50%;
    bottom: 100%;
    transform: translateX(-50%) translateY(-4px);
    background: #333;
    color: #fff;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    white-space: nowrap;
    z-index: 100;
    pointer-events: none;
}

.admin-events-table {
    table-layout: fixed;
    width: 100%;
}

.admin-events-table th,
.admin-events-table td {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.admin-events-table th:nth-child(1),
.admin-events-table td:nth-child(1) {
    width: 28%;
}

.admin-events-table th:nth-child(2),
.admin-events-table td:nth-child(2) {
    width: 12%;
}

.admin-events-table th:nth-child(3),
.admin-events-table td:nth-child(3) {
    width: 22%;
}

.admin-events-table th:nth-child(4),
.admin-events-table td:nth-child(4) {
    width: 12%;
    white-space: normal;
}

.admin-events-table th:nth-child(5),
.admin-events-table td:nth-child(5) {
    width: 12%;
}

.admin-events-table th:nth-child(6),
.admin-events-table td:nth-child(6) {
    width: 14%;
}
</style>