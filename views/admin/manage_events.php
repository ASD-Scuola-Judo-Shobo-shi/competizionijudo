<?php /** @var list<\App\Model\Event> $events */ ?>
<?php /** @var array<int, array{clubs: int, athletes: int}> $entry_counts */ ?>
<?php /** @var array{page: int, per_page: int, total: int, last_page: int, offset: int, links: string} $pagination */ ?>
<?php /** @var array{type: string, message: string}|null $inlineFeedback */ ?>
<section class="card admin-list-page">
    <header class="admin-list-heading">
        <h2>
            <?= e(__('admin.events.title')) ?>
            <span class="count-badge"><?= e((string) $pagination['total']) ?></span>
        </h2>
    </header>

    <?php if (is_array($inlineFeedback ?? null)) : ?>
        <div class="notice<?= $inlineFeedback['type'] === 'success' ? ' success' : '' ?>" role="status">
            <?= e($inlineFeedback['message']) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($events)) : ?>
        <p class="admin-list-empty"><?= e(__('admin.events.empty')) ?></p>
    <?php else : ?>
        <div
            class="table-scroll table-scroll--wide table-scroll--responsive admin-list-table-wrap"
            role="region"
            tabindex="0"
            aria-label="<?= e(__('admin.events.title')) ?>"
        >
            <table class="table-full responsive-table admin-list-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('admin.events.table.name')) ?></th>
                        <th scope="col"><?= e(__('admin.events.table.date')) ?></th>
                        <th scope="col"><?= e(__('admin.events.table.location')) ?></th>
                        <th scope="col"><?= e(__('admin.events.table.type')) ?></th>
                        <th scope="col"><?= e(__('admin.events.table.visibility')) ?></th>
                        <th scope="col"><?= e(__('admin.events.table.registration_status')) ?></th>
                        <th scope="col"><?= e(__('admin.events.table.clubs')) ?></th>
                        <th scope="col"><?= e(__('admin.events.table.athletes')) ?></th>
                        <th scope="col"><?= e(__('admin.events.table.max_participants')) ?></th>
                        <th scope="col"><?= e(__('admin.events.table.actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event) : ?>
                        <?php
                        $typeLabel = match ($event->type) {
                            'only_precompetitive' => __('admin.events.type_tooltip.precompetitive'),
                            'only_competitive' => __('admin.events.type_tooltip.competitive'),
                            'precompetitive_and_competitive' => __('admin.events.type_tooltip.both'),
                            default => __('admin.common.not_available'),
                        };
                        $typeTableLabel = match ($event->type) {
                            'only_precompetitive' => __('admin.events.table.precompetitive'),
                            'only_competitive' => __('admin.events.table.competitive'),
                            'precompetitive_and_competitive' => __('admin.events.table.both'),
                            default => __('admin.common.not_available'),
                        };
                        $clubCount = $entry_counts[$event->id]['clubs'] ?? 0;
                        $athleteCount = $entry_counts[$event->id]['athletes'] ?? 0;
                        $inlineFormId = 'event-inline-form-' . $event->id;
    ?>
                        <tr id="event-row-<?= (int) $event->id ?>" data-inline-edit-row>
                            <td class="admin-list-table__primary" data-label="<?= e(__('admin.events.name')) ?>">
                                <span data-inline-display><?= e($event->name) ?></span>
                                <input
                                    class="inline-edit-control"
                                    data-inline-editor
                                    form="<?= e($inlineFormId) ?>"
                                    name="name"
                                    value="<?= e($event->name) ?>"
                                    aria-label="<?= e(__('admin.events.name')) ?>"
                                    required
                                >
                            </td>
                            <td data-label="<?= e(__('admin.events.date')) ?>">
                                <time datetime="<?= e($event->date) ?>" class="admin-date-badge" data-inline-display>
                                    <?= e($event->date) ?>
                                </time>
                                <input
                                    class="inline-edit-control inline-edit-control--date"
                                    data-inline-editor
                                    form="<?= e($inlineFormId) ?>"
                                    type="date"
                                    name="date"
                                    value="<?= e($event->date) ?>"
                                    aria-label="<?= e(__('admin.events.date')) ?>"
                                    required
                                >
                            </td>
                            <td data-label="<?= e(__('admin.events.location')) ?>">
                                <span data-inline-display><?= e($event->location) ?></span>
                                <input
                                    class="inline-edit-control"
                                    data-inline-editor
                                    form="<?= e($inlineFormId) ?>"
                                    name="location"
                                    value="<?= e($event->location) ?>"
                                    aria-label="<?= e(__('admin.events.location')) ?>"
                                    required
                                >
                            </td>
                            <td data-label="<?= e(__('admin.events.type')) ?>">
                                <span data-inline-display>
                                    <span class="table-density-value" title="<?= e($typeLabel) ?>"><?= e($typeTableLabel) ?></span>
                                    <span class="card-density-value"><?= e($typeLabel) ?></span>
                                </span>
                                <select class="inline-edit-control" data-inline-editor form="<?= e($inlineFormId) ?>" name="type" aria-label="<?= e(__('admin.events.type')) ?>" required>
                                    <option value="only_precompetitive" <?= $event->type === 'only_precompetitive' ? 'selected' : '' ?>><?= e(__('admin.events.type_tooltip.precompetitive')) ?></option>
                                    <option value="only_competitive" <?= $event->type === 'only_competitive' ? 'selected' : '' ?>><?= e(__('admin.events.type_tooltip.competitive')) ?></option>
                                    <option value="precompetitive_and_competitive" <?= $event->type === 'precompetitive_and_competitive' ? 'selected' : '' ?>><?= e(__('admin.events.type_tooltip.both')) ?></option>
                                </select>
                            </td>
                            <td data-label="<?= e(__('admin.events.visibility')) ?>">
                                <?php $visibilityLabel = $event->published ? __('admin.events.published_tooltip') : __('admin.events.hidden_tooltip'); ?>
                                <span class="status-symbol" data-inline-display aria-label="<?= e($visibilityLabel) ?>" title="<?= e($visibilityLabel) ?>">
                                    <span aria-hidden="true"><?= $event->published ? '👁️' : '🙈' ?></span>
                                    <span class="status-symbol__label"><?= e($visibilityLabel) ?></span>
                                </span>
                                <label class="inline-edit-check" data-inline-editor>
                                    <input type="hidden" form="<?= e($inlineFormId) ?>" name="published" value="0">
                                    <input type="checkbox" form="<?= e($inlineFormId) ?>" name="published" value="1" <?= $event->published ? 'checked' : '' ?>>
                                    <span><?= e(__('admin.events.visibility')) ?></span>
                                </label>
                            </td>
                            <td data-label="<?= e(__('admin.events.registration_status')) ?>">
                                <?php $registrationLabel = $event->closed ? __('admin.events.closed_tooltip') : __('admin.events.open_tooltip'); ?>
                                <span class="status-symbol" data-inline-display aria-label="<?= e($registrationLabel) ?>" title="<?= e($registrationLabel) ?>">
                                    <span aria-hidden="true"><?= $event->closed ? '🔴' : '🟢' ?></span>
                                    <span class="status-symbol__label"><?= e($registrationLabel) ?></span>
                                </span>
                                <label class="inline-edit-check" data-inline-editor>
                                    <input type="hidden" form="<?= e($inlineFormId) ?>" name="closed" value="0">
                                    <input type="checkbox" form="<?= e($inlineFormId) ?>" name="closed" value="1" <?= $event->closed ? 'checked' : '' ?>>
                                    <span><?= e(__('admin.events.closed_tooltip')) ?></span>
                                </label>
                            </td>
                            <td class="numeric-cell" data-label="<?= e(__('admin.events.clubs')) ?>">
                                <strong><?= e((string) $clubCount) ?></strong>
                            </td>
                            <td class="numeric-cell" data-label="<?= e(__('admin.events.athletes')) ?>">
                                <strong><?= e((string) $athleteCount) ?></strong>
                            </td>
                            <td data-label="<?= e(__('admin.events.max_participants')) ?>">
                                <span data-inline-display>
                                    <?= e($event->max_participants !== null
                                        ? (string) $event->max_participants
                                        : __('admin.events.unlimited')) ?>
                                </span>
                                <input
                                    class="inline-edit-control inline-edit-control--number"
                                    data-inline-editor
                                    form="<?= e($inlineFormId) ?>"
                                    type="number"
                                    name="max_participants"
                                    min="1"
                                    value="<?= e($event->max_participants !== null ? (string) $event->max_participants : '') ?>"
                                    placeholder="∞"
                                    aria-label="<?= e(__('admin.events.max_participants')) ?>"
                                >
                            </td>
                            <td class="table-actions-cell" data-label="<?= e(__('admin.events.actions')) ?>">
                                <div class="table-actions admin-table-actions" data-inline-display>
                                    <button class="btn green table-action-button" type="button" data-inline-edit aria-label="<?= e(__('tables.edit_row')) ?>" title="<?= e(__('tables.edit_row')) ?>"><span aria-hidden="true">✏️</span><span class="table-action-label"><?= e(__('tables.edit_row')) ?></span></button>
                                    <a class="btn table-action-button" href="<?= e(base_url('/admin/events/export?event_id=' . (int) $event->id)) ?>" aria-label="<?= e(__('admin.events.export')) ?>" title="<?= e(__('admin.events.export')) ?>"><span aria-hidden="true">⬇️</span><span class="table-action-label"><?= e(__('admin.events.export')) ?></span></a>
                                    <a class="btn gray table-action-button" href="<?= e(base_url('/admin/events/details?event_id=' . (int) $event->id)) ?>" aria-label="<?= e(__('tables.full_edit')) ?>" title="<?= e(__('tables.full_edit')) ?>"><span aria-hidden="true">⚙️</span><span class="table-action-label"><?= e(__('tables.full_edit')) ?></span></a>
                                    <form method="post" action="<?= e(base_url('/admin/events/delete?')) ?>" onsubmit="return confirm('<?= e(__('admin.events.confirm_delete')) ?>')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="event_id" value="<?= (int) $event->id ?>">
                                        <button class="btn red table-action-button" type="submit" aria-label="<?= e(__('admin.events.delete')) ?>" title="<?= e(__('admin.events.delete')) ?>"><span aria-hidden="true">🗑️</span><span class="table-action-label"><?= e(__('admin.events.delete')) ?></span></button>
                                    </form>
                                </div>
                                <form id="<?= e($inlineFormId) ?>" class="table-actions inline-edit-actions" data-inline-editor method="post" action="<?= e(base_url('/admin/events/update-inline')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="event_id" value="<?= (int) $event->id ?>">
                                    <input type="hidden" name="page" value="<?= (int) $pagination['page'] ?>">
                                    <button class="btn green table-action-button" type="submit" aria-label="<?= e(__('tables.save')) ?>" title="<?= e(__('tables.save')) ?>"><span aria-hidden="true">💾</span><span class="table-action-label"><?= e(__('tables.save')) ?></span></button>
                                    <button class="btn gray table-action-button" type="button" data-inline-cancel aria-label="<?= e(__('tables.cancel')) ?>" title="<?= e(__('tables.cancel')) ?>"><span aria-hidden="true">↩️</span><span class="table-action-label"><?= e(__('tables.cancel')) ?></span></button>
                                    <a class="btn table-action-button" href="<?= e(base_url('/admin/events/details?event_id=' . (int) $event->id)) ?>" aria-label="<?= e(__('tables.full_edit')) ?>" title="<?= e(__('tables.full_edit')) ?>"><span aria-hidden="true">⚙️</span><span class="table-action-label"><?= e(__('tables.full_edit')) ?></span></a>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?= $pagination['links'] ?>
</section>
