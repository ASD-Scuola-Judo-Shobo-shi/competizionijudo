<?php
/** @var array<int, int> $registrationCounts */
/** @var array{page: int, per_page: int, total: int, last_page: int, offset: int, links: string} $pagination */
/** @var array<int, array{age_below: int|null, type: string, weight_category: string}> $athleteCategories */
/** @var list<array{id: int, name: string, date: string}> $events */
/** @var int $eventFilter */
/** @var array{type: string, message: string}|null $athleteInlineFeedback */
?>
<?php require __DIR__ . '/_athlete_csv_tools.php'; ?>
<?php if (!empty($events)) : ?>
<div class="card">
    <h3><?= e(__('club.area.filter_by_event')) ?></h3>
    <form method="get" class="form-inline">
        <input type="hidden" name="view" value="list">
        <label><?= e(__('club.area.event')) ?></label>
        <select name="event" onchange="this.form.submit()">
            <option value="0"><?= e(__('club.area.all_events')) ?></option>
            <?php foreach ($events as $c) : ?>
                <option value="<?= e((string) $c['id']) ?>" <?= $eventFilter === (int) $c['id'] ? 'selected' : '' ?>>
                    <?= e($c['name'] . ' - ' . $c['date']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h3>
        <?= e(__('club.area.athlete_archive')) ?>
        <span class="count-badge"><?= e((string) $pagination['total']) ?></span>
    </h3>
    <?php if (is_array($athleteInlineFeedback ?? null)) : ?>
        <div class="notice<?= $athleteInlineFeedback['type'] === 'success' ? ' success' : '' ?>" role="status">
            <?= e($athleteInlineFeedback['message']) ?>
        </div>
    <?php endif; ?>
    <?php if (empty($athletes)) : ?>
        <p><?= e(__('club.area.no_athletes')) ?></p>
    <?php else : ?>
        <div
            class="table-scroll table-scroll--wide table-scroll--responsive"
            role="region"
            tabindex="0"
            aria-label="<?= e(__('club.area.athlete_archive')) ?>"
        >
            <table class="table-full responsive-table">
            <thead>
                <tr>
                    <th scope="col"><?= e(__('club.area.table.athlete')) ?></th>
                    <th scope="col"><?= e(__('club.area.table.gender')) ?></th>
                    <th scope="col"><?= e(__('club.area.table.birth')) ?></th>
                    <th scope="col"><?= e(__('club.area.table.age_class')) ?></th>
                    <th scope="col"><?= e(__('club.area.table.weight')) ?></th>
                    <th scope="col"><?= e(__('club.area.table.belt')) ?></th>
                    <th scope="col"><?= e(__('club.area.table.weight_category')) ?></th>
                    <th scope="col"><?= e(__('club.area.table.registrations')) ?></th>
                    <th scope="col"><?= e(__('club.area.table.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($athletes as $athlete) :
                    $_birthYear = (int) substr($athlete->birth_date, 0, 4);
                    $_eventYear = (int) date('Y');
                    $_ac = App\Model\AgeClass::calculate($_birthYear, $_eventYear, App\Localization::getLocale());
                    $_ageClassLabel = $_ac['label'];
                    $_inlineFormId = 'athlete-inline-list-' . $athlete->id;
                    $_gender = $athlete->genderEnum();
                    ?>
                    <tr id="athlete-row-<?= (int) $athlete->id ?>" data-inline-edit-row>
                        <td data-label="<?= e(__('club.area.athlete')) ?>">
                            <span data-inline-display><?= e($athlete->last_name . ' ' . $athlete->first_name) ?></span>
                            <span class="inline-edit-stack" data-inline-editor>
                                <input class="inline-edit-control" form="<?= e($_inlineFormId) ?>" name="last_name" value="<?= e($athlete->last_name) ?>" aria-label="<?= e(__('club.area.last_name')) ?>" placeholder="<?= e(__('club.area.last_name')) ?>" required>
                                <input class="inline-edit-control" form="<?= e($_inlineFormId) ?>" name="first_name" value="<?= e($athlete->first_name) ?>" aria-label="<?= e(__('club.area.first_name')) ?>" placeholder="<?= e(__('club.area.first_name')) ?>" required>
                            </span>
                        </td>
                        <td data-label="<?= e(__('club.area.gender')) ?>">
                            <span data-inline-display>
                                <span class="table-density-value" title="<?= e($athlete->genderLabel()) ?>"><?= e($_gender?->icon() ?? $athlete->gender) ?></span>
                                <span class="card-density-value"><?= e($athlete->genderIconLabel()) ?></span>
                            </span>
                            <select class="inline-edit-control" data-inline-editor form="<?= e($_inlineFormId) ?>" name="gender" aria-label="<?= e(__('club.area.gender')) ?>" required>
                                <?php foreach (App\Model\Gender::cases() as $_genderOption) : ?>
                                    <option value="<?= e($_genderOption->value) ?>" <?= $athlete->gender === $_genderOption->value ? 'selected' : '' ?>><?= e($_genderOption->iconLabel()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td data-label="<?= e(__('club.area.birth')) ?>">
                            <time data-inline-display datetime="<?= e($athlete->birth_date) ?>"><?= e($athlete->birth_date) ?></time>
                            <input class="inline-edit-control inline-edit-control--date" data-inline-editor form="<?= e($_inlineFormId) ?>" type="date" name="birth_date" value="<?= e($athlete->birth_date) ?>" aria-label="<?= e(__('club.area.birth_date')) ?>" required>
                        </td>
                        <td data-label="<?= e(__('club.area.age_class')) ?>"><?= e($_ageClassLabel) ?></td>
                        <td data-label="<?= e(__('club.area.weight')) ?>">
                            <span data-inline-display>
                                <?= e($athlete->weight_kg !== null
                                    ? (string) $athlete->weight_kg
                                    : __('events.no_weight')) ?>
                            </span>
                            <input class="inline-edit-control inline-edit-control--number" data-inline-editor form="<?= e($_inlineFormId) ?>" type="number" name="weight_kg" min="0.1" max="200" step="0.1" value="<?= e($athlete->weight_kg !== null ? (string) $athlete->weight_kg : '') ?>" aria-label="<?= e(__('club.area.weight_kg')) ?>" required>
                        </td>
                        <td data-label="<?= e(__('club.area.belt')) ?>">
                            <span data-inline-display><?php require dirname(__DIR__) . '/components/belt_badge.php'; ?></span>
                            <select class="inline-edit-control" data-inline-editor form="<?= e($_inlineFormId) ?>" name="belt" aria-label="<?= e(__('club.area.belt')) ?>" required>
                                <?php foreach (App\Model\Belt::cases() as $_beltOption) : ?>
                                    <option value="<?= e($_beltOption->value) ?>" <?= $athlete->belt === $_beltOption->value ? 'selected' : '' ?>><?= e($_beltOption->circleLabel()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td data-label="<?= e(__('club.area.weight_category')) ?>">
                            <?= e($athleteCategories[$athlete->id]['weight_category'] ?? '') ?>
                        </td>
                        <td class="numeric-cell" data-label="<?= e(__('club.area.registrations')) ?>">
                            <?= e((string) ($registrationCounts[$athlete->id] ?? 0)) ?>
                        </td>
                        <td class="table-actions-cell" data-label="<?= e(__('club.area.actions')) ?>">
                            <div class="table-actions" data-inline-display>
                                <button class="btn green table-action-button" type="button" data-inline-edit aria-label="<?= e(__('tables.edit_row')) ?>" title="<?= e(__('tables.edit_row')) ?>"><span aria-hidden="true">✏️</span><span class="table-action-label"><?= e(__('tables.edit_row')) ?></span></button>
                                <a class="btn gray table-action-button" href="<?= e(base_url('/clubs/area?view=add&edit=' . (string) $athlete->id)) ?>" aria-label="<?= e(__('tables.full_edit')) ?>" title="<?= e(__('tables.full_edit')) ?>"><span aria-hidden="true">⚙️</span><span class="table-action-label"><?= e(__('tables.full_edit')) ?></span></a>
                                <form method="post" action="<?= e(base_url('/clubs/delete-athlete?')) ?>" onsubmit="return confirm('<?= e(__('club.area.confirm_delete_athlete')) ?>')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="athlete_id" value="<?= e((string) $athlete->id) ?>">
                                    <button class="btn red table-action-button" type="submit" aria-label="<?= e(__('club.area.delete')) ?>" title="<?= e(__('club.area.delete')) ?>"><span aria-hidden="true">🗑️</span><span class="table-action-label"><?= e(__('club.area.delete')) ?></span></button>
                                </form>
                            </div>
                            <form id="<?= e($_inlineFormId) ?>" class="table-actions inline-edit-actions" data-inline-editor method="post" action="<?= e(base_url('/clubs/athletes/update-inline')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="athlete_id" value="<?= (int) $athlete->id ?>">
                                <input type="hidden" name="membership_number" value="<?= e($athlete->membership_number ?? '') ?>">
                                <input type="hidden" name="notes" value="<?= e($athlete->notes ?? '') ?>">
                                <input type="hidden" name="return_view" value="list">
                                <input type="hidden" name="page" value="<?= (int) $pagination['page'] ?>">
                                <input type="hidden" name="event" value="<?= (int) $eventFilter ?>">
                                <button class="btn green table-action-button" type="submit" aria-label="<?= e(__('tables.save')) ?>" title="<?= e(__('tables.save')) ?>"><span aria-hidden="true">💾</span><span class="table-action-label"><?= e(__('tables.save')) ?></span></button>
                                <button class="btn gray table-action-button" type="button" data-inline-cancel aria-label="<?= e(__('tables.cancel')) ?>" title="<?= e(__('tables.cancel')) ?>"><span aria-hidden="true">↩️</span><span class="table-action-label"><?= e(__('tables.cancel')) ?></span></button>
                                <a class="btn table-action-button" href="<?= e(base_url('/clubs/area?view=add&edit=' . (string) $athlete->id)) ?>" aria-label="<?= e(__('tables.full_edit')) ?>" title="<?= e(__('tables.full_edit')) ?>"><span aria-hidden="true">⚙️</span><span class="table-action-label"><?= e(__('tables.full_edit')) ?></span></a>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?= $pagination['links'] ?>
</div>
