<?php
/** @var array<int, int> $registrationCounts */
/** @var array{page: int, per_page: int, total: int, last_page: int, offset: int, links: string} $pagination */
/** @var array<int, array{age_below: int|null, type: string, weight_category: string}> $athleteCategories */
/** @var list<array{id: int, name: string, date: string}> $events */
/** @var int $eventFilter */
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
    <h3><?= e(__('club.area.athlete_archive')) ?></h3>
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
                    <th scope="col"><?= e(__('club.area.athlete')) ?></th>
                    <th scope="col"><?= e(__('club.area.gender')) ?></th>
                    <th scope="col"><?= e(__('club.area.birth')) ?></th>
                    <th scope="col"><?= e(__('club.area.age_class')) ?></th>
                    <th scope="col"><?= e(__('club.area.weight')) ?></th>
                    <th scope="col"><?= e(__('club.area.belt')) ?></th>
                    <th scope="col"><?= e(__('club.area.weight_category')) ?></th>
                    <th scope="col"><?= e(__('club.area.registrations')) ?></th>
                    <th scope="col"><?= e(__('club.area.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($athletes as $athlete) :
                    $_birthYear = (int) substr($athlete->birth_date, 0, 4);
                    $_eventYear = (int) date('Y');
                    $_ac = App\Model\AgeClass::calculate($_birthYear, $_eventYear, App\Localization::getLocale());
                    $_ageClassLabel = $_ac['label'];
                    ?>
                    <tr>
                        <td data-label="<?= e(__('club.area.athlete')) ?>">
                            <?= e($athlete->last_name . ' ' . $athlete->first_name) ?>
                        </td>
                        <td data-label="<?= e(__('club.area.gender')) ?>"><?= e($athlete->genderLabel()) ?></td>
                        <td data-label="<?= e(__('club.area.birth')) ?>"><?= e($athlete->birth_date) ?></td>
                        <td data-label="<?= e(__('club.area.age_class')) ?>"><?= e($_ageClassLabel) ?></td>
                        <td data-label="<?= e(__('club.area.weight')) ?>"><?= e((string) $athlete->weight_kg) ?></td>
                        <td data-label="<?= e(__('club.area.belt')) ?>">
                            <?php require dirname(__DIR__) . '/components/belt_badge.php'; ?>
                        </td>
                        <td data-label="<?= e(__('club.area.weight_category')) ?>">
                            <?= e($athleteCategories[$athlete->id]['weight_category'] ?? '') ?>
                        </td>
                        <td data-label="<?= e(__('club.area.registrations')) ?>">
                            <?= e((string) ($registrationCounts[$athlete->id] ?? 0)) ?>
                        </td>
                        <td class="table-actions-cell" data-label="<?= e(__('club.area.actions')) ?>">
                            <div class="table-actions">
                                <a class="btn btn-sm table-action-icon" href="<?= e(base_url('/clubs/area?view=add&edit=' . (string) $athlete->id)) ?>" aria-label="<?= e(__('club.area.edit')) ?>" title="<?= e(__('club.area.edit')) ?>">✏️</a>
                                <form method="post" action="<?= e(base_url('/clubs/delete-athlete?')) ?>" onsubmit="return confirm('<?= e(__('club.area.confirm_delete_athlete')) ?>')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="athlete_id" value="<?= e((string) $athlete->id) ?>">
                                    <button class="btn btn-sm red table-action-icon" type="submit" aria-label="<?= e(__('club.area.delete')) ?>" title="<?= e(__('club.area.delete')) ?>">❌</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?= $pagination['links'] ?>
</div>
