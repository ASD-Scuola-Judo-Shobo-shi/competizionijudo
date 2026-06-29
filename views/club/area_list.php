<?php
/** @var array<int, int> $registrationCounts */
/** @var array{page: int, per_page: int, total: int, last_page: int, offset: int, links: string} $pagination */
?>
<?php if (!empty($competitions)) : ?>
<div class="card">
    <h3><?= e(__('club.area.filter_by_competition')) ?></h3>
    <form method="get" class="form-inline">
        <input type="hidden" name="view" value="list">
        <label><?= e(__('club.area.competition')) ?></label>
        <select name="event" onchange="this.form.submit()">
            <option value="0"><?= e(__('club.area.all_competitions')) ?></option>
            <?php foreach ($competitions as $c) : ?>
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
        <table class="table-full">
            <thead>
                <tr>
                    <th><?= e(__('club.area.athlete')) ?></th>
                    <th><?= e(__('club.area.gender')) ?></th>
                    <th><?= e(__('club.area.birth')) ?></th>
                    <th><?= e(__('club.area.age_class')) ?></th>
                    <th><?= e(__('club.area.weight')) ?></th>
                    <th><?= e(__('club.area.belt')) ?></th>
                    <th><?= e(__('club.area.weight_category')) ?></th>
                    <th><?= e(__('club.area.registrations')) ?></th>
                    <th><?= e(__('club.area.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($athletes as $athlete) :
                    $_birthYear = (int) substr($athlete->date_of_birth, 0, 4);
                    $_eventYear = date('Y');
                    $_ac = App\Model\AgeClass::calculate($_birthYear, $_eventYear, App\Localization::getLocale());
                    $_ageClassLabel = $_ac['label'] ?? '';
                    ?>
                    <tr>
                        <td><?= e($athlete->last_name . ' ' . $athlete->first_name) ?></td>
                        <td><?= e($athlete->genderLabel()) ?></td>
                        <td><?= e($athlete->date_of_birth) ?></td>
                        <td><?= e($_ageClassLabel) ?></td>
                        <td><?= e((string) $athlete->weight_kg) ?></td>
                        <td>
                            <?php foreach ($athlete->beltEnum()?->components() ?? [['label' => $athlete->beltLabel(), 'color' => '#ccc', 'textColor' => '#000']] as $component) : ?>
                                <span class="belt-badge" style="background-color: <?= e($component['color']) ?>; color: <?= e($component['textColor']) ?>"><?= e($component['label']) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td><?= e($athlete->weight_category) ?></td>
                        <td><?= e((string) ($registrationCounts[$athlete->id] ?? 0)) ?></td>
                        <td>
                            <a class="btn btn-sm" href="/club_area.php?view=add&edit=<?= e((string) $athlete->id) ?>"><?= e(__('club.area.edit')) ?></a>
                            <form method="post" action="/club_delete_athlete.php" style="display:inline" onsubmit="return confirm('<?= e(__('club.area.confirm_delete_athlete')) ?>')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="athlete_id" value="<?= e((string) $athlete->id) ?>">
                                <button class="btn btn-sm red" type="submit"><?= e(__('club.area.delete')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <?= $pagination['links'] ?>
</div>
