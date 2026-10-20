<?php

/** @var \App\Presentation\EventEntriesViewModel $entryReport */
/** @var int|null $loggedInClubId */
/** @var list<array{category:string, weight:string, ageMin:int, athletes:list<array<string, mixed>>}> $entryAthleteGroups */
/** @var array{page:int, per_page:int, total:int, last_page:int, offset:int, links:string} $entryAthletesPagination */
/** @var array<string, string> $entryAthleteFilters */
/** @var array<string, string> $entriesQueryState */
/** @var string $entryAthletesFilterResetUrl */
$tableFilterId = 'entry-athletes';
$tableFilterLabel = __('events.entries_filter_table', [
    'table' => __('events.entries_athletes_heading'),
]);
$tableFilterFields = [
    [
        'name' => 'athletes_filter_age_class',
        'label' => __('club.area.table.age_class'),
        'value' => $entryAthleteFilters['age_class'] ?? '',
    ],
    [
        'name' => 'athletes_filter_weight_category',
        'label' => __('club.area.table.weight_category'),
        'value' => $entryAthleteFilters['weight_category'] ?? '',
    ],
    [
        'name' => 'athletes_filter_athlete',
        'label' => __('club.area.table.athlete'),
        'value' => $entryAthleteFilters['athlete'] ?? '',
    ],
    [
        'name' => 'athletes_filter_club',
        'label' => __('club.area.table.club'),
        'value' => $entryAthleteFilters['club'] ?? '',
    ],
    [
        'name' => 'athletes_filter_gender',
        'label' => __('club.area.table.gender'),
        'value' => $entryAthleteFilters['gender'] ?? '',
    ],
    [
        'name' => 'athletes_filter_belt',
        'label' => __('club.area.table.belt'),
        'value' => $entryAthleteFilters['belt'] ?? '',
    ],
];
$tableFilterState = $entriesQueryState;
$tableFilterResetUrl = $entryAthletesFilterResetUrl;
$tableFilterDownloadUrl = null;
$entryAthleteFiltersActive = count(array_filter(
    $entryAthleteFilters,
    static fn(string $value): bool => $value !== ''
)) > 0;
?>

<section class="card">
    <h2><?= e(__('events.entries_athletes_heading')) ?></h2>
    <?php if ($loggedInClubId !== null) : ?>
        <p class="field-help">
            <?= e(__('events.entries_shared_roster_help')) ?>
            <a href="<?= e(base_url('/privacy')) ?>"><?= e(__('events.entries_shared_roster_privacy')) ?></a>
            <a href="<?= e(base_url('/terms')) ?>"><?= e(__('events.entries_shared_roster_terms')) ?></a>
        </p>
    <?php endif; ?>
    <?php require __DIR__ . '/_entries_table_filters.php'; ?>
    <?php if ($entryAthleteGroups === [] && $entryAthleteFiltersActive) : ?>
        <p><?= e(__('events.entries_no_filter_results')) ?></p>
    <?php elseif ($entryAthleteGroups === []) : ?>
        <p><?= e(__('club.area.no_entries')) ?></p>
    <?php else : ?>
        <div
            class="table-scroll table-scroll--wide table-scroll--responsive"
            role="region"
            tabindex="0"
            aria-label="<?= e(__('events.entries_athletes_heading')) ?>"
        >
            <table
                class="responsive-table"
                data-sort-mode="server"
                data-sort-parameter="athletes_sort"
                data-sort-direction-parameter="athletes_direction"
                data-sort-page-parameter="athletes_page"
                data-sort-default="age_class"
            >
                <thead>
                    <tr>
                        <th scope="col" data-sort-key="age_class"><?= e(__('club.area.table.age_class')) ?></th>
                        <th scope="col" data-sort-key="weight_category"><?= e(__('club.area.table.weight_category')) ?></th>
                        <th scope="col" data-sort-key="athlete"><?= e(__('club.area.table.athlete')) ?></th>
                        <th scope="col" data-sort-key="club"><?= e(__('club.area.table.club')) ?></th>
                        <th scope="col" data-sort-key="gender"><?= e(__('club.area.table.gender')) ?></th>
                        <th scope="col" data-sort-key="belt"><?= e(__('club.area.table.belt')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entryAthleteGroups as $group) : ?>
                        <?php foreach ($group['athletes'] as $athlete) : ?>
                            <?php
                            $isCurrentClub = $loggedInClubId !== null
                                && (int) $athlete['club_id'] === $loggedInClubId;
                            ?>
                            <tr<?= $isCurrentClub ? ' class="club-row--current"' : '' ?>>
                                <td data-label="<?= e(__('club.area.age_class')) ?>">
                                    <?= e((string) $group['category']) ?>
                                </td>
                                <td data-label="<?= e(__('club.area.weight_category')) ?>">
                                    <?= e((string) $group['weight']) ?>
                                </td>
                                <td data-label="<?= e(__('club.area.athlete')) ?>">
                                    <?= e((string) $athlete['athlete_name']) ?>
                                </td>
                                <td data-label="<?= e(__('club.area.club')) ?>">
                                    <?= e((string) $athlete['club_name']) ?>
                                    <?php if ($isCurrentClub) : ?>
                                        <span class="club-badge--current"><?= e(__('club.list.current_club')) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="<?= e(__('club.area.gender')) ?>">
                                    <?php
                                    $genderBadge = \App\Model\Gender::tryFromValue((string) $athlete['gender']);
                                    $genderBadgeFallback = (string) $athlete['gender'];
                                    require dirname(__DIR__) . '/components/gender_badge.php';
                                    ?>
                                </td>
                                <td data-label="<?= e(__('club.area.belt')) ?>">
                                    <?php
                                    $beltBadge = \App\Model\Belt::tryFromValue((string) $athlete['belt']);
                                    $beltBadgeFallback = (string) $athlete['belt_label'];
                                    require dirname(__DIR__) . '/components/belt_badge.php';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $entryAthletesPagination['links'] ?>
    <?php endif; ?>
</section>
