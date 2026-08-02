<?php /** @var list<\App\Model\Club> $clubs */ ?>
<?php /** @var array<int, int> $athlete_counts */ ?>
<?php /** @var array{page: int, per_page: int, total: int, last_page: int, offset: int, links: string} $pagination */ ?>
<?php /** @var array{type: string, message: string}|null $inlineFeedback */ ?>
<?php /** @var array{column:string, direction:'asc'|'desc'}|null $tableSort */ ?>
<?php $tableSort ??= ['column' => 'name', 'direction' => 'asc']; ?>
<section class="card admin-list-page">
    <header class="admin-list-heading">
        <h2>
            <?= e(__('admin.clubs.title')) ?>
            <span class="count-badge"><?= e((string) $pagination['total']) ?></span>
        </h2>
    </header>

    <?php if (is_array($inlineFeedback ?? null)) : ?>
        <div class="notice<?= $inlineFeedback['type'] === 'success' ? ' success' : '' ?>" role="status">
            <?= e($inlineFeedback['message']) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($clubs)) : ?>
        <p class="admin-list-empty"><?= e(__('admin.clubs.empty')) ?></p>
    <?php else : ?>
        <div
            class="table-scroll table-scroll--wide table-scroll--responsive admin-list-table-wrap"
            role="region"
            tabindex="0"
            aria-label="<?= e(__('admin.clubs.title')) ?>"
        >
            <table
                class="table-full responsive-table admin-list-table"
                data-sort-mode="server"
                data-sort-page-parameter="page"
                data-sort-default="name"
            >
                <thead>
                    <tr>
                        <th scope="col" data-sort-key="name"><?= e(__('admin.clubs.table.name')) ?></th>
                        <th scope="col" data-sort-key="federal_code"><?= e(__('admin.clubs.table.federal_code')) ?></th>
                        <th scope="col" data-sort-key="email"><?= e(__('admin.clubs.table.email')) ?></th>
                        <th scope="col" data-sort-key="phone"><?= e(__('admin.clubs.table.phone')) ?></th>
                        <th scope="col" data-sort-key="contact"><?= e(__('admin.clubs.table.contact')) ?></th>
                        <th scope="col" data-sort-key="address"><?= e(__('admin.clubs.table.address')) ?></th>
                        <th scope="col" data-sort-key="affiliation"><?= e(__('admin.clubs.table.affiliation')) ?></th>
                        <th scope="col" data-sort-key="athletes"><?= e(__('admin.clubs.table.athletes')) ?></th>
                        <th scope="col" data-sortable="false"><?= e(__('admin.clubs.table.actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clubs as $club) : ?>
                        <?php
                        $contactName = trim($club->contact_first_name . ' ' . $club->contact_last_name);
                        $locality = trim(implode(' ', array_filter([
                            $club->postal_code,
                            $club->city,
                            $club->province !== '' ? '(' . $club->province . ')' : null,
                        ])));
                        $address = implode(', ', array_filter([$club->address_line, $locality]));
                        $affiliations = implode(', ', $club->affiliations());
                        $athleteCount = $athlete_counts[$club->id] ?? 0;
                        $inlineFormId = 'club-inline-form-' . $club->id;
                        ?>
                        <tr id="club-row-<?= (int) $club->id ?>" data-inline-edit-row>
                            <td class="admin-list-table__primary" data-label="<?= e(__('admin.clubs.name')) ?>">
                                <span data-inline-display><?= e($club->name) ?></span>
                                <input
                                    class="inline-edit-control"
                                    data-inline-editor
                                    form="<?= e($inlineFormId) ?>"
                                    name="name"
                                    value="<?= e($club->name) ?>"
                                    aria-label="<?= e(__('admin.clubs.name')) ?>"
                                    required
                                >
                            </td>
                            <td data-label="<?= e(__('admin.clubs.federal_code')) ?>">
                                <span class="admin-code-badge" data-inline-display><?= e($club->federal_code) ?></span>
                                <input
                                    class="inline-edit-control inline-edit-control--compact"
                                    data-inline-editor
                                    form="<?= e($inlineFormId) ?>"
                                    name="federal_code"
                                    value="<?= e($club->federal_code) ?>"
                                    aria-label="<?= e(__('admin.clubs.federal_code')) ?>"
                                    required
                                >
                            </td>
                            <td data-label="<?= e(__('admin.clubs.email')) ?>">
                                <a data-inline-display href="<?= e('mailto:' . $club->email) ?>"><?= e($club->email) ?></a>
                                <input
                                    class="inline-edit-control"
                                    data-inline-editor
                                    form="<?= e($inlineFormId) ?>"
                                    type="email"
                                    name="email"
                                    value="<?= e($club->email) ?>"
                                    aria-label="<?= e(__('admin.clubs.email')) ?>"
                                    required
                                >
                            </td>
                            <td data-label="<?= e(__('admin.clubs.phone')) ?>">
                                <span data-inline-display>
                                    <?php if ($club->phone !== '') : ?>
                                        <a href="<?= e('tel:' . $club->phone) ?>"><?= e($club->phone) ?></a>
                                    <?php else : ?>
                                        <?= e(__('admin.common.not_available')) ?>
                                    <?php endif; ?>
                                </span>
                                <input
                                    class="inline-edit-control inline-edit-control--compact"
                                    data-inline-editor
                                    form="<?= e($inlineFormId) ?>"
                                    name="phone"
                                    value="<?= e($club->phone) ?>"
                                    inputmode="tel"
                                    aria-label="<?= e(__('admin.clubs.phone')) ?>"
                                    required
                                >
                            </td>
                            <td data-label="<?= e(__('admin.clubs.contact')) ?>">
                                <span data-inline-display>
                                    <?= e($contactName !== '' ? $contactName : __('admin.common.not_available')) ?>
                                </span>
                                <span class="inline-edit-stack" data-inline-editor>
                                    <input
                                        class="inline-edit-control"
                                        form="<?= e($inlineFormId) ?>"
                                        name="contact_first_name"
                                        value="<?= e($club->contact_first_name) ?>"
                                        aria-label="<?= e(__('admin.clubs.contact_first_name')) ?>"
                                        placeholder="<?= e(__('admin.clubs.contact_first_name')) ?>"
                                    >
                                    <input
                                        class="inline-edit-control"
                                        form="<?= e($inlineFormId) ?>"
                                        name="contact_last_name"
                                        value="<?= e($club->contact_last_name) ?>"
                                        aria-label="<?= e(__('admin.clubs.contact_last_name')) ?>"
                                        placeholder="<?= e(__('admin.clubs.contact_last_name')) ?>"
                                    >
                                </span>
                            </td>
                            <td data-label="<?= e(__('admin.clubs.address')) ?>">
                                <?= e($address !== '' ? $address : __('admin.common.not_available')) ?>
                            </td>
                            <td data-label="<?= e(__('admin.clubs.affiliation')) ?>">
                                <?= e($affiliations !== '' ? $affiliations : __('admin.common.not_available')) ?>
                            </td>
                            <td class="numeric-cell" data-label="<?= e(__('admin.clubs.athletes')) ?>">
                                <strong title="<?= e($athleteCount . ' ' . __('admin.clubs.athlete_count')) ?>">
                                    <?= e((string) $athleteCount) ?>
                                </strong>
                            </td>
                            <td class="table-actions-cell" data-label="<?= e(__('admin.clubs.actions')) ?>">
                                <div class="table-actions admin-table-actions" data-inline-display>
                                    <a class="btn table-action-button" href="<?= e(base_url('/admin/clubs/athletes?club_id=' . (int) $club->id)) ?>" aria-label="<?= e(__('admin.clubs.view_athletes')) ?>" title="<?= e(__('admin.clubs.view_athletes')) ?>"><span aria-hidden="true">👥</span><span class="table-action-label"><?= e(__('admin.clubs.view_athletes')) ?></span></a>
                                    <a class="btn table-action-button" href="<?= e(base_url('/admin/clubs/athletes/export?club_id=' . (int) $club->id)) ?>" aria-label="<?= e(__('admin.clubs.export_athletes')) ?>" title="<?= e(__('admin.clubs.export_athletes')) ?>"><span aria-hidden="true">⬇️</span><span class="table-action-label"><?= e(__('admin.clubs.export_athletes')) ?></span></a>
                                    <button class="btn table-action-button green" type="button" data-inline-edit aria-label="<?= e(__('tables.edit_row')) ?>" title="<?= e(__('tables.edit_row')) ?>"><span aria-hidden="true">✏️</span><span class="table-action-label"><?= e(__('tables.edit_row')) ?></span></button>
                                    <a class="btn table-action-button gray" href="<?= e(base_url('/admin/clubs/edit?id=' . (int) $club->id)) ?>" aria-label="<?= e(__('tables.full_edit')) ?>" title="<?= e(__('tables.full_edit')) ?>"><span aria-hidden="true">⚙️</span><span class="table-action-label"><?= e(__('tables.full_edit')) ?></span></a>
                                    <form method="post" action="<?= e(base_url('/admin/clubs/delete?')) ?>" onsubmit="return confirm('<?= e(__('admin.clubs.confirm_delete')) ?>')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="club_id" value="<?= (int) $club->id ?>">
                                        <button class="btn red table-action-button" type="submit" aria-label="<?= e(__('admin.clubs.delete')) ?>" title="<?= e(__('admin.clubs.delete')) ?>"><span aria-hidden="true">🗑️</span><span class="table-action-label"><?= e(__('admin.clubs.delete')) ?></span></button>
                                    </form>
                                </div>
                                <form id="<?= e($inlineFormId) ?>" class="table-actions inline-edit-actions" data-inline-editor method="post" action="<?= e(base_url('/admin/clubs/update-inline')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="club_id" value="<?= (int) $club->id ?>">
                                    <input type="hidden" name="page" value="<?= (int) $pagination['page'] ?>">
                                    <input type="hidden" name="sort" value="<?= e($tableSort['column']) ?>">
                                    <input type="hidden" name="direction" value="<?= e($tableSort['direction']) ?>">
                                    <button class="btn green table-action-button" type="submit" aria-label="<?= e(__('tables.save')) ?>" title="<?= e(__('tables.save')) ?>"><span aria-hidden="true">💾</span><span class="table-action-label"><?= e(__('tables.save')) ?></span></button>
                                    <button class="btn gray table-action-button" type="button" data-inline-cancel aria-label="<?= e(__('tables.cancel')) ?>" title="<?= e(__('tables.cancel')) ?>"><span aria-hidden="true">↩️</span><span class="table-action-label"><?= e(__('tables.cancel')) ?></span></button>
                                    <a class="btn table-action-button" href="<?= e(base_url('/admin/clubs/edit?id=' . (int) $club->id)) ?>" aria-label="<?= e(__('tables.full_edit')) ?>" title="<?= e(__('tables.full_edit')) ?>"><span aria-hidden="true">⚙️</span><span class="table-action-label"><?= e(__('tables.full_edit')) ?></span></a>
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
