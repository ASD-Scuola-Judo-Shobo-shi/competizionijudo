<?php

/** @var \App\Model\Event $event */
/** @var \App\Presentation\EventEntriesViewModel $entryReport */
/** @var list<array<string, mixed>> $currentClubEntries */
/** @var array{page:int, per_page:int, total:int, last_page:int, offset:int, links:string} $currentClubPagination */
/** @var array<string, string> $currentClubFilters */
/** @var array<string, string> $entriesQueryState */
/** @var string $currentClubFilterResetUrl */
/** @var string $clubEntriesExportUrl */
$weightCategoryOptions = ['' => __('events.entries_all_weights')];
foreach ($entryReport->currentClubWeightCategories as $weightCategory) {
    $weightCategoryOptions[$weightCategory] = $weightCategory;
}
$tableFilterId = 'current-club-entries';
$tableFilterLabel = __('events.entries_filter_table', [
    'table' => __('events.entries_current_club_title'),
]);
$tableFilterFields = [
    [
        'name' => 'club_entries_filter_athlete',
        'label' => __('club.area.table.athlete'),
        'value' => $currentClubFilters['athlete'] ?? '',
    ],
    [
        'name' => 'club_entries_filter_gender',
        'label' => __('club.area.table.gender'),
        'value' => $currentClubFilters['gender'] ?? '',
    ],
    [
        'name' => 'club_entries_filter_weight',
        'label' => __('club.area.table.weight'),
        'value' => $currentClubFilters['weight'] ?? '',
        'inputmode' => 'decimal',
    ],
    [
        'name' => 'weight_category',
        'label' => __('club.area.table.weight_category'),
        'value' => $currentClubFilters['weight_category'] ?? '',
        'options' => $weightCategoryOptions,
    ],
    [
        'name' => 'club_entries_filter_belt',
        'label' => __('club.area.table.belt'),
        'value' => $currentClubFilters['belt'] ?? '',
    ],
];
$tableFilterState = $entriesQueryState;
$tableFilterResetUrl = $currentClubFilterResetUrl;
$tableFilterDownloadUrl = $event->closed ? $clubEntriesExportUrl : null;
$currentClubFiltersActive = count(array_filter(
    $currentClubFilters,
    static fn(string $value): bool => $value !== ''
)) > 0;
?>

<section class="card closed-event-club-entries">
    <div class="club-entries-heading">
        <h2><?= e(__('events.entries_current_club_title')) ?></h2>
        <a class="btn" href="<?= e(base_url('/events/entries/print?event=' . $event->id)) ?>">
            <?= e(__('events.entries_print_pdf')) ?>
        </a>
    </div>
    <p><?= e(__('events.entries_current_club_help')) ?></p>

    <?php require __DIR__ . '/_entries_table_filters.php'; ?>

    <p class="closed-event-club-entries__count">
        <?= e(__('events.entries_filtered_count', [
            'count' => (string) $currentClubPagination['total'],
        ])) ?>
    </p>

    <?php if ($currentClubEntries === [] && $currentClubFiltersActive) : ?>
        <p><?= e(__('events.entries_no_filter_results')) ?></p>
    <?php elseif ($currentClubEntries === []) : ?>
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
                                <?php
                                $beltBadge = \App\Model\Belt::tryFromValue((string) $athlete['belt']);
                                $beltBadgeFallback = (string) $athlete['belt_label'];
                                require dirname(__DIR__) . '/components/belt_badge.php';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $currentClubPagination['links'] ?>
    <?php endif; ?>
</section>
