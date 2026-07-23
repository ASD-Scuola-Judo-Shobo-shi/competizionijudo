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
                <th><?= e(__('admin.events.info')) ?></th>
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
                    <td class="admin-events-info">
                        <?php
                        $typeEmoji = match ($event->type) {
                            'only_precompetitive' => '🎖️',
                            'only_competitive' => '🏅',
                            'precompetitive_and_competitive' => '🏆',
                            default => '',
                        };
                        $typeLabel = match ($event->type) {
                            'only_precompetitive' => __('admin.events.type_tooltip.precompetitive'),
                            'only_competitive' => __('admin.events.type_tooltip.competitive'),
                            'precompetitive_and_competitive' => __('admin.events.type_tooltip.both'),
                            default => '',
                        };
    ?>
                        <span class="info-icons">
                            <span class="event-indicator emoji-tooltip" data-tooltip="<?= e($typeLabel) ?>" tabindex="0"><?= e($typeEmoji) ?></span>
                            <?php if ($event->published) : ?>
                                <span class="event-indicator emoji-tooltip" data-tooltip="<?= e(__('admin.events.published_tooltip')) ?>" tabindex="0"><?= e('👁️') ?></span>
                            <?php else : ?>
                                <span class="event-indicator emoji-tooltip" data-tooltip="<?= e(__('admin.events.hidden_tooltip')) ?>" tabindex="0"><?= e('🚫') ?></span>
                            <?php endif; ?>
                            <?php if ($event->closed) : ?>
                                <span class="event-indicator emoji-tooltip" data-tooltip="<?= e(__('admin.events.closed_tooltip')) ?>" tabindex="0"><?= e('🔒') ?></span>
                            <?php else : ?>
                                <span class="event-indicator emoji-tooltip" data-tooltip="<?= e(__('admin.events.open_tooltip')) ?>" tabindex="0"><?= e('⬇️') ?></span>
                            <?php endif; ?>
                        </span>
                    </td>
                    <td>
                        <?= e(($entry_counts[$event->id]['clubs'] ?? 0) . ' | ' . ($entry_counts[$event->id]['athletes'] ?? 0)) ?>
                        <?php if ($event->max_participants !== null) : ?>
                            / <?= e((string) $event->max_participants) ?>
                        <?php endif; ?>
                    </td>
                    <td class="admin-events-actions table-actions-cell">
                        <div class="admin-event-actions table-actions">
                            <a class="btn btn-sm green action-icon table-action-icon" href="<?= e(base_url('/admin/events/add?event_id=' . (int) $event->id)) ?>" aria-label="<?= e(__('admin.events.edit')) ?>" title="<?= e(__('admin.events.edit')) ?>">✏️</a>
                            <a class="btn btn-sm action-icon table-action-icon" href="<?= e(base_url('/admin/events/export?event_id=' . (int) $event->id)) ?>" aria-label="<?= e(__('admin.events.export')) ?>" title="<?= e(__('admin.events.export')) ?>">📤</a>
                            <form method="post" action="<?= e(base_url('/admin/events/delete?')) ?>" onsubmit="return confirm('<?= e(__('admin.events.confirm_delete')) ?>')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="event_id" value="<?= (int) $event->id ?>">
                                <button class="btn btn-sm red action-icon table-action-icon" type="submit" aria-label="<?= e(__('admin.events.delete')) ?>" title="<?= e(__('admin.events.delete')) ?>">❌</button>
                            </form>
                        </div>
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
    width: 11%;
}

.admin-events-table th:nth-child(3),
.admin-events-table td:nth-child(3) {
    width: 18%;
}

.admin-events-table th:nth-child(4),
.admin-events-table td:nth-child(4) {
    width: 10%;
}

.admin-events-table th:nth-child(5),
.admin-events-table td:nth-child(5) {
    width: 15%;
}

.admin-events-table th:nth-child(6),
.admin-events-table td:nth-child(6) {
    width: 18%;
}

.admin-events-table td.admin-events-info {
    position: relative;
    overflow: visible;
    white-space: nowrap;
    z-index: 1;
}

.admin-events-table td.admin-events-info:hover,
.admin-events-table td.admin-events-info:focus-within {
    z-index: 2;
}

.info-icons {
    display: inline-grid;
    grid-template-columns: repeat(3, 1.25em);
    column-gap: .2em;
    inline-size: 4.15em;
    max-inline-size: 100%;
    align-items: center;
}

.event-indicator {
    display: inline-grid;
    place-items: center;
    inline-size: 1.25em;
    font-size: 1.1em;
}

.emoji-tooltip {
    cursor: default;
    position: relative;
}

.emoji-tooltip::after {
    content: attr(data-tooltip);
    position: absolute;
    left: 50%;
    bottom: calc(100% + 6px);
    transform: translateX(-50%);
    background: #333;
    color: #fff;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 400;
    line-height: 1.2;
    white-space: nowrap;
    visibility: hidden;
    opacity: 0;
    transition: opacity .15s ease;
    z-index: 10;
    pointer-events: none;
}

.emoji-tooltip:hover::after,
.emoji-tooltip:focus::after {
    visibility: visible;
    opacity: 1;
}

.emoji-tooltip:hover,
.emoji-tooltip:focus {
    z-index: 3;
}

@media screen and (max-width: 480px) {
    .admin-events-table th:nth-child(1),
    .admin-events-table td:nth-child(1) {
        width: 20%;
    }

    .admin-events-table th:nth-child(2),
    .admin-events-table td:nth-child(2) {
        width: 11%;
    }

    .admin-events-table th:nth-child(3),
    .admin-events-table td:nth-child(3) {
        width: 12%;
    }

    .admin-events-table th:nth-child(4),
    .admin-events-table td:nth-child(4) {
        width: 21%;
    }

    .admin-events-table th:nth-child(5),
    .admin-events-table td:nth-child(5) {
        width: 15%;
    }

    .admin-events-table th:nth-child(6),
    .admin-events-table td:nth-child(6) {
        width: 21%;
    }

    .admin-events-table th,
    .admin-events-table td {
        padding: 8px 4px;
    }
}
</style>
