<?php

/** @var \App\Model\Event $event */
/** @var \App\Presentation\EventEntriesViewModel $entryReport */
/** @var list<array<string, mixed>> $currentClubEntries */
/** @var array{page:int, per_page:int, total:int, last_page:int, offset:int, links:string} $currentClubPagination */
/** @var array{column:string, direction:'asc'|'desc'} $currentClubSort */
/** @var array{column:string, direction:'asc'|'desc'} $entryClubsSort */
/** @var array{column:string, direction:'asc'|'desc'} $entryAthletesSort */
$clubExportUrl = '/events/entries/export?event=' . rawurlencode((string) $event->id);
if ($entryReport->selectedWeightCategory !== '') {
    $clubExportUrl .= '&weight_category=' . rawurlencode($entryReport->selectedWeightCategory);
}
?>

<section class="card closed-event-club-entries">
    <h2><?= e(__('events.entries_current_club_title')) ?></h2>
    <p><?= e(__('events.entries_current_club_help')) ?></p>

    <form method="get" action="<?= e(base_url('/events/entries')) ?>" class="closed-event-club-filter">
        <input type="hidden" name="event" value="<?= e((string) $event->id) ?>">
        <input type="hidden" name="club_entries_sort" value="<?= e($currentClubSort['column']) ?>">
        <input type="hidden" name="club_entries_direction" value="<?= e($currentClubSort['direction']) ?>">
        <input type="hidden" name="clubs_sort" value="<?= e($entryClubsSort['column']) ?>">
        <input type="hidden" name="clubs_direction" value="<?= e($entryClubsSort['direction']) ?>">
        <input type="hidden" name="athletes_sort" value="<?= e($entryAthletesSort['column']) ?>">
        <input type="hidden" name="athletes_direction" value="<?= e($entryAthletesSort['direction']) ?>">
        <div class="closed-event-club-filter__field">
            <label for="club-weight-category"><?= e(__('events.entries_weight_filter')) ?></label>
            <select id="club-weight-category" name="weight_category">
                <option value=""><?= e(__('events.entries_all_weights')) ?></option>
                <?php foreach ($entryReport->currentClubWeightCategories as $weightCategory) : ?>
                    <option
                        value="<?= e($weightCategory) ?>"
                        <?= $entryReport->selectedWeightCategory === $weightCategory ? 'selected' : '' ?>
                    ><?= e($weightCategory) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn" type="submit"><?= e(__('events.entries_apply_weight_filter')) ?></button>
        <a class="btn green" href="<?= e(base_url($clubExportUrl)) ?>">
            <?= e(__('events.entries_download_filtered')) ?>
        </a>
    </form>

    <p class="closed-event-club-entries__count">
        <?= e(__('events.entries_filtered_count', [
            'count' => (string) $currentClubPagination['total'],
        ])) ?>
    </p>

    <?php if ($currentClubEntries === []) : ?>
        <p><?= e(__('club.area.no_entries')) ?></p>
    <?php else : ?>
        <div
            class="table-scroll table-scroll--responsive"
            role="region"
            tabindex="0"
            aria-label="<?= e(__('events.entries_current_club_title')) ?>"
        >
            <table
                class="responsive-table"
                data-sort-mode="server"
                data-sort-parameter="club_entries_sort"
                data-sort-direction-parameter="club_entries_direction"
                data-sort-page-parameter="club_entries_page"
                data-sort-default="weight"
            >
                <thead>
                    <tr>
                        <th scope="col" data-sort-key="athlete"><?= e(__('club.area.table.athlete')) ?></th>
                        <th scope="col" data-sort-key="gender"><?= e(__('club.area.table.gender')) ?></th>
                        <th scope="col" data-sort-key="weight"><?= e(__('club.area.table.weight')) ?></th>
                        <th scope="col" data-sort-key="weight_category"><?= e(__('club.area.table.weight_category')) ?></th>
                        <th scope="col" data-sort-key="belt"><?= e(__('club.area.table.belt')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($currentClubEntries as $athlete) : ?>
                        <tr>
                            <td data-label="<?= e(__('club.area.athlete')) ?>">
                                <?= e((string) $athlete['athlete_name']) ?>
                            </td>
                            <td data-label="<?= e(__('club.area.gender')) ?>">
                                <?php
                                $genderBadge = \App\Model\Gender::tryFromValue((string) $athlete['gender']);
                                $genderBadgeFallback = (string) $athlete['gender'];
                                require dirname(__DIR__) . '/components/gender_badge.php';
                                ?>
                            </td>
                            <td data-label="<?= e(__('club.area.weight')) ?>">
                                <?= e((string) $athlete['weight_display']) ?>
                            </td>
                            <td data-label="<?= e(__('club.area.weight_category')) ?>">
                                <?= e((string) $athlete['weight_category']) ?>
                            </td>
                            <td data-label="<?= e(__('club.area.belt')) ?>">
                                <?= e((string) $athlete['belt_label']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $currentClubPagination['links'] ?>
    <?php endif; ?>
</section>
