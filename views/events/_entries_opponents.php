<?php

/** @var list<array{ageClass:string, weightCategory:string, ageMin:int, athletes:list<array<string, mixed>>}> $entryOpponentGroups */
/** @var array<string, string> $entryOpponentFilters */
/** @var array<string, string> $entriesQueryState */
/** @var string $entryOpponentsFilterResetUrl */
$tableFilterId = 'entry-opponents';
$tableFilterLabel = __('events.entries_filter_table', [
    'table' => __('events.entries_opponents_title'),
]);
$tableFilterFields = [
    [
        'name' => 'opponents_filter_age_class',
        'label' => __('club.area.table.age_class'),
        'value' => $entryOpponentFilters['age_class'] ?? '',
    ],
    [
        'name' => 'opponents_filter_weight_category',
        'label' => __('club.area.table.weight_category'),
        'value' => $entryOpponentFilters['weight_category'] ?? '',
    ],
    [
        'name' => 'opponents_filter_athlete',
        'label' => __('club.area.table.athlete'),
        'value' => $entryOpponentFilters['athlete'] ?? '',
    ],
    [
        'name' => 'opponents_filter_club',
        'label' => __('club.area.table.club'),
        'value' => $entryOpponentFilters['club'] ?? '',
    ],
    [
        'name' => 'opponents_filter_gender',
        'label' => __('club.area.table.gender'),
        'value' => $entryOpponentFilters['gender'] ?? '',
    ],
    [
        'name' => 'opponents_filter_belt',
        'label' => __('club.area.table.belt'),
        'value' => $entryOpponentFilters['belt'] ?? '',
    ],
];
$tableFilterState = $entriesQueryState;
$tableFilterResetUrl = $entryOpponentsFilterResetUrl;
$tableFilterDownloadUrl = null;
$entryOpponentFiltersActive = array_filter(
    $entryOpponentFilters,
    static fn(string $value): bool => $value !== ''
) !== [];
?>

<section class="card closed-event-opponents">
    <h2><?= e(__('events.entries_opponents_title')) ?></h2>
    <p><?= e(__('events.entries_opponents_help')) ?></p>
    <p class="field-help">
        <?= e(__('events.entries_shared_roster_help')) ?>
        <a href="<?= e(base_url('/privacy')) ?>"><?= e(__('events.entries_shared_roster_privacy')) ?></a>
        <a href="<?= e(base_url('/terms')) ?>"><?= e(__('events.entries_shared_roster_terms')) ?></a>
    </p>

    <?php require __DIR__ . '/_entries_table_filters.php'; ?>

    <?php if ($entryOpponentGroups === [] && $entryOpponentFiltersActive) : ?>
        <p><?= e(__('events.entries_no_filter_results')) ?></p>
    <?php elseif ($entryOpponentGroups === []) : ?>
        <p><?= e(__('events.entries_opponents_none')) ?></p>
    <?php else : ?>
        <div class="opponent-groups">
            <?php foreach ($entryOpponentGroups as $groupIndex => $group) : ?>
                <?php
                $groupHeadingId = 'opponent-group-' . (int) $groupIndex;
                $groupTableLabel = __('events.entries_opponents_table_label', [
                    'age_class' => $group['ageClass'],
                    'weight_category' => $group['weightCategory'],
                ]);
                ?>
                <article class="opponent-group">
                    <h3 id="<?= e($groupHeadingId) ?>">
                        <?= e($group['ageClass']) ?> · <?= e($group['weightCategory']) ?>
                    </h3>
                    <div
                        class="table-scroll table-scroll--responsive"
                        role="region"
                        tabindex="0"
                        aria-labelledby="<?= e($groupHeadingId) ?>"
                    >
                        <table
                            class="responsive-table opponent-table"
                            data-client-pagination="50"
                            aria-label="<?= e($groupTableLabel) ?>"
                        >
                            <thead>
                                <tr>
                                    <th scope="col" data-sort-key="athlete">
                                        <?= e(__('club.area.table.athlete')) ?>
                                    </th>
                                    <th scope="col" data-sort-key="club">
                                        <?= e(__('club.area.table.club')) ?>
                                    </th>
                                    <th scope="col" data-sort-key="gender">
                                        <?= e(__('club.area.table.gender')) ?>
                                    </th>
                                    <th scope="col" data-sort-key="belt">
                                        <?= e(__('club.area.table.belt')) ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($group['athletes'] as $athlete) : ?>
                                    <?php $isCurrentClub = (bool) ($athlete['is_current_club'] ?? false); ?>
                                    <tr<?= $isCurrentClub ? ' class="club-row--current"' : '' ?>>
                                        <td data-label="<?= e(__('club.area.athlete')) ?>">
                                            <?= e((string) $athlete['athlete_name']) ?>
                                        </td>
                                        <td data-label="<?= e(__('club.area.club')) ?>">
                                            <?= e((string) $athlete['club_name']) ?>
                                            <?php if ($isCurrentClub) : ?>
                                                <span
                                                    class="club-badge--current club-badge--icon"
                                                    title="<?= e(__('club.list.current_club')) ?>"
                                                >
                                                    <span aria-hidden="true">👥</span>
                                                    <span class="sr-only"><?= e(__('club.list.current_club')) ?></span>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="<?= e(__('club.area.gender')) ?>">
                                            <?php
                                            $genderBadge = \App\Model\Gender::tryFromValue(
                                                (string) $athlete['gender']
                                            );
                                            $genderBadgeFallback = (string) $athlete['gender'];
                                            require dirname(__DIR__) . '/components/gender_badge.php';
                                            ?>
                                        </td>
                                        <td data-label="<?= e(__('club.area.belt')) ?>">
                                            <?= e((string) $athlete['belt_label']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
