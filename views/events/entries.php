<?php

/** @var \App\Model\Event|null $event */
/** @var list<array{id: int, club_name: string, federal_code: string}> $clubs */
/** @var int|null $loggedInClubId */
/** @var array<int, int> $clubAthleteCounts */
/** @var array<int, array<string, int>> $clubCategoryCounts */
/** @var array<int, array<string, int>> $clubBeltCounts */
/** @var array<int, array<string, int>> $clubGenderCounts */
/** @var array<int, array<string, int>> $clubWeightCounts */
/** @var array<string, int> $categoryCounts */
/** @var array<string, int> $beltCounts */
/** @var array<string, int> $genderCounts */
/** @var array<string, int> $weightCounts */
/** @var array<string, array<int, array{last_name: string, first_name: string, gender: string, weight_kg: float, belt: string, club_id: int, club_name: string}>> $grouped */
/** @var list<array<string, string>> $rows */
/** @var bool $isAdmin */
/** @var bool $hasRegistrationException */
/** @var list<\App\Model\Event> $upcomingEvents */

// Helper closures for rendering UI components cleanly
$renderPieChart = function (array $segments, float $chartWidth) {
    if (empty($segments)) {
        return;
    }
    $startAngle = 0;
    echo '<svg viewBox="0 0 100 100" style="width: ' . e((string)$chartWidth) . '%; max-width: 240px; height: auto; aspect-ratio: 1 / 1;">';
    foreach ($segments as $segment) {
        $endAngle = $startAngle + ($segment['percentage'] / 100) * 360;
        $largeArc = ($endAngle - $startAngle) > 180 ? 1 : 0;
        $startX = 50 + 40 * cos(deg2rad($startAngle - 90));
        $startY = 50 + 40 * sin(deg2rad($startAngle - 90));
        $endX = 50 + 40 * cos(deg2rad($endAngle - 90));
        $endY = 50 + 40 * sin(deg2rad($endAngle - 90));
        printf(
            '<path d="M50,50 L%.2f,%.2f A40,40 0 %d,1 %.2f,%.2f Z" fill="%s"/>',
            $startX,
            $startY,
            $largeArc,
            $endX,
            $endY,
            e($segment['color'])
        );
        $startAngle = $endAngle;
    }
    echo '</svg>';
};

$renderStackedBar = function (array $segments, array $counts, int $total) {
    if ($total <= 0 || empty($segments)) {
        return;
    }
    echo '<div style="display: flex; height: 6px;">';
    foreach ($segments as $segment) {
        $count = $counts[$segment['label']] ?? 0;
        if ($count <= 0) {
            continue;
        }
        $width = ($count / $total) * 100;
        $label = $segment['displayLabel'] ?? $segment['label'];
        $borderStyle = !empty($segment['border']) ? 'border: 1px solid #ccc;' : '';
        printf(
            '<div title="%s: %d" style="background: %s; width: %.2f%%; height: 100%%; %s"></div>',
            e($label),
            $count,
            e($segment['color']),
            $width,
            $borderStyle
        );
    }
    echo '</div>';
};
?>

<?php if ($event !== null) : ?>
    <?php
    $ageClasses = \App\Model\AgeClass::all();

    // 1. Prepare Category Segments
    $ageClassColors = [
        'children_a' => '#e1bee7',
        'children_b' => '#ce93d8',
        'kids' => '#ba68c8',
        'youth' => '#ab47bc',
        'pre_cadets_a' => '#9c27b0',
        'pre_cadets_b' => '#8e24aa',
        'cadets' => '#7b1fa2',
        'juniors' => '#6a1b9a',
        'seniors' => '#4a148c',
        'masters' => '#311b92',
    ];
    $categoryTotal = array_sum($categoryCounts);
    $sortedCategories = [];
    foreach ($categoryCounts as $category => $count) {
        $ageMin = PHP_INT_MAX;
        $ageClassKey = null;
        foreach ($ageClasses as $ac) {
            if (str_contains($ac->label(), $category) || str_starts_with($ac->label(), $category)) {
                $ageClassKey = $ac->key;
                $ageMin = $ac->ageMin;
                break;
            }
        }
        $sortedCategories[] = ['category' => $category, 'count' => $count, 'ageMin' => $ageMin, 'ageClassKey' => $ageClassKey];
    }
    usort($sortedCategories, fn($a, $b) => $a['ageMin'] <=> $b['ageMin']);

    $categorySegments = [];
    foreach ($sortedCategories as $item) {
        $categorySegments[] = [
            'label'      => $item['category'],
            'count'      => $item['count'],
            'percentage' => $categoryTotal > 0 ? ($item['count'] / $categoryTotal) * 100 : 0,
            'color'      => $ageClassColors[$item['ageClassKey'] ?? ''] ?? '#b39ddb',
        ];
    }

    // 2. Prepare Weight Segments
    $weightTotal = array_sum($weightCounts);
    $weightColors = [
        'under-12kg' => '#ffe0b2',
        '12-16kg' => '#ffcdd2',
        '16-20kg' => '#ffb6c1',
        '20-24kg' => '#ffaab9',
        '24-28kg' => '#ff8eb5',
        '28-32kg' => '#ff7eb8',
        '32-36kg' => '#ff6eaa',
        '36-40kg' => '#ff5e99',
        '40-44kg' => '#ff4e88',
        '44-48kg' => '#ff3e78',
        '48-52kg' => '#ff2e68',
        '52-56kg' => '#ff1e58',
        '56-60kg' => '#f0164f',
        '60-64kg' => '#e01046',
        '64-68kg' => '#d00a3d',
        '68-72kg' => '#c00034',
        '72-76kg' => '#b3002d',
        '76-80kg' => '#a30026',
        '80-84kg' => '#93001f',
        '84-88kg' => '#830018',
        '88-92kg' => '#730011',
        '92-96kg' => '#63000a',
        '96-100kg' => '#520004',
        '100+kg' => '#400000',
        'unspecified' => '#b39ddb',
    ];
    $weightSegments = [];
    foreach ($weightColors as $weightGroup => $color) {
        $count = $weightCounts[$weightGroup] ?? 0;
        if ($count > 0) {
            $weightSegments[] = [
                'label'      => $weightGroup,
                'count'      => $count,
                'percentage' => $weightTotal > 0 ? ($count / $weightTotal) * 100 : 0,
                'color'      => $color,
            ];
        }
    }

    // 3. Prepare Belt Segments
    $beltTotal = array_sum($beltCounts);
    $beltSegments = [];
    $beltRankOrder = array_flip(array_map(fn($case) => $case->value, \App\Model\Belt::cases()));
    foreach ($beltCounts as $belt => $count) {
        $beltEnum = \App\Model\Belt::tryFromValue($belt);
        $beltSegments[] = [
            'label'        => $belt,
            'displayLabel' => $beltEnum?->label(\App\Localization::getLocale()) ?? $belt,
            'count'        => $count,
            'percentage'   => $beltTotal > 0 ? ($count / $beltTotal) * 100 : 0,
            'color'        => $beltEnum !== null ? $beltEnum->components()[0]['color'] : '#6c757d',
            'border'       => true,
        ];
    }
    usort($beltSegments, fn($a, $b) => ($beltRankOrder[$a['label']] ?? PHP_INT_MAX) <=> ($beltRankOrder[$b['label']] ?? PHP_INT_MAX));

    // 4. Prepare Gender Segments
    $genderTotal = array_sum($genderCounts);
    $genderSegments = [];
    $genderColors = ['F' => '#f48fb1', 'M' => '#90caf9'];
    foreach (['F', 'M'] as $gender) {
        $count = $genderCounts[$gender] ?? 0;
        if ($count > 0) {
            $genderSegments[] = [
                'label'        => $gender,
                'displayLabel' => __('gender.' . $gender),
                'count'        => $count,
                'percentage'   => $genderTotal > 0 ? ($count / $genderTotal) * 100 : 0,
                'color'        => $genderColors[$gender],
            ];
        }
    }

    // Chart Collection
    $charts = array_filter([
        ['title' => __('events.entries_category_breakdown'), 'segments' => $categorySegments],
        ['title' => __('events.entries_weight_breakdown'),   'segments' => $weightSegments],
        ['title' => __('events.entries_belt_breakdown'),     'segments' => $beltSegments],
        ['title' => __('gender.gender'),                    'segments' => $genderSegments],
    ], fn($c) => !empty($c['segments']));
    ?>

    <!-- Main Card with relative positioning -->
    <div class="card" style="position: relative;">
        <?php if ($event->closed) : ?>
            <span class="badge badge-closed" style="position: absolute; top: 1rem; right: 1rem; background-color: #6c757d; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; z-index: 2;">
                <?= e(__('events.closed')) ?>
            </span>
        <?php endif; ?>

        <h2><?= e(__('events.entries_title')) ?></h2>
        <h3><?= e($event->name) ?></h3>
        <p>
            <strong><?= e(__('events.date')) ?>:</strong> <?= e($event->date) ?><br>
            <strong><?= e(__('events.location')) ?>:</strong> <?= e($event->location) ?><br>
        </p>

        <?php if (!empty($charts)) : ?>
            <div class="recap-summary" style="margin-top: 1rem; padding: 1rem; background: #f7f9fc; border-radius: 8px;">
                <h4 style="margin-top: 0;"><?= e(__('events.entries_recap')) ?></h4>
                <p>
                    <strong><?= e(__('events.entries_subscribed')) ?>:</strong> <?= e((string) count($rows)) ?><br>
                    <?php if ($event->max_participants !== null) : ?>
                        <strong><?= e(__('admin.events.max_participants')) ?>:</strong> <?= e((string) $event->max_participants) ?><br>
                        <strong><?= e(__('events.entries_free_spots')) ?>:</strong> <?= e((string) max(0, $event->max_participants - count($rows))) ?><br>
                    <?php endif; ?>
                </p>

                <div style="display: flex; flex-direction: column; margin-bottom: 1rem; width: 100%;">
                    <?php $chartWidth = 100 / (count($charts) + 1); ?>
                    <!-- Pie Charts -->
                    <div style="display: flex; justify-content: space-around; flex-wrap: wrap; gap: 15px; margin-bottom: 1rem; width: 100%;">
                        <?php foreach ($charts as $chart) : ?>
                            <?php $renderPieChart($chart['segments'], $chartWidth); ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- Legends -->
                    <div style="display: flex; flex-direction: column; gap: 4px; width: 100%;">
                        <?php foreach ($charts as $chart) : ?>
                            <div style="display: flex; flex-wrap: wrap; gap: 12px; font-size: 0.85rem;">
                                <strong style="margin-right: 8px;"><?= e($chart['title']) ?>:</strong>
                                <?php foreach ($chart['segments'] as $segment) : ?>
                                    <span style="display: flex; align-items: center; gap: 4px;">
                                        <span style="display: inline-block; width: 10px; height: 10px; background: <?= e($segment['color']) ?>; <?= !empty($segment['border']) ? 'border: 1px solid #ccc;' : '' ?> border-radius: 2px;"></span>
                                        <?= e($segment['displayLabel'] ?? $segment['label']) ?>: <?= e((string) $segment['count']) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <p>
            <a class="btn" href="<?= e(base_url('/events/details?event=' . (string) $event->id)) ?>"><?= e(__('events.details')) ?></a>
            <?php if (!$event->closed || $hasRegistrationException) : ?>
                <a class="btn green" href="<?= e(base_url('/events/register?event=' . (string) $event->id)) ?>"><?= e(__('events.registration')) ?></a>
            <?php endif; ?>
        </p>
    </div>

    <!-- Clubs Overview Table -->
    <div class="card">
        <h2><?= e(__('events.entries_clubs_heading')) ?></h2>
        <?php if (!$clubs && !$rows) : ?>
            <p><?= e(__('club.area.no_entries')) ?></p>
        <?php elseif (!$clubs) : ?>
            <p><?= e(__('events.entries_no_clubs')) ?></p>
        <?php else : ?>
            <table>
                <tr>
                    <th>#</th>
                    <th><?= e(__('events.entries_club')) ?></th>
                    <th><?= e(__('events.entries_code')) ?></th>
                    <th><?= e(__('events.entries_athletes')) ?></th>
                    <th><?= e(__('events.entries_club_breakdown')) ?></th>
                </tr>
                <?php foreach ($clubs as $i => $club) : ?>
                    <?php
                    $clubTotal = $clubAthleteCounts[$club['id']] ?? 0;
                    $isCurrentClub = $loggedInClubId !== null && (int) $club['id'] === $loggedInClubId;
                    ?>
                    <tr<?= $isCurrentClub ? ' class="club-row--current"' : '' ?>>
                        <td><?= (int) $i + 1 ?></td>
                        <td>
                            <strong><?= e($club['club_name']) ?></strong>
                            <?php if ($isCurrentClub) : ?>
                                <span class="club-badge--current"><?= e(__('club.list.current_club')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($club['federal_code']) ?></td>
                        <td><?= e((string) $clubTotal) ?></td>
                        <td>
                            <?php if ($clubTotal > 0) : ?>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <?php
                                    $renderStackedBar($categorySegments, $clubCategoryCounts[$club['id']] ?? [], $clubTotal);
                                    $renderStackedBar($weightSegments, $clubWeightCounts[$club['id']] ?? [], $clubTotal);
                                    $renderStackedBar($beltSegments, $clubBeltCounts[$club['id']] ?? [], $clubTotal);
                                    $renderStackedBar($genderSegments, $clubGenderCounts[$club['id']] ?? [], $clubTotal);
                                    ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <!-- Athletes Breakdown Table -->
    <?php if ($event->closed && ($loggedInClubId === null || !$hasRegistrationException)) : ?>
        <div class="card">
            <h2><?= e(__('events.entries_athletes_heading')) ?></h2>
            <?php if (empty($grouped)) : ?>
                <p><?= e(__('club.area.no_entries')) ?></p>
            <?php else : ?>
                <?php
                $sortedKeys = [];
                foreach (array_keys($grouped) as $key) {
                    [$category, $weight] = array_pad(explode(' | ', $key, 2), 2, '');
                    $ageMin = PHP_INT_MAX;
                    foreach ($ageClasses as $ac) {
                        if (str_contains($ac->label(), $category) || str_starts_with($ac->label(), $category)) {
                            $ageMin = $ac->ageMin;
                            break;
                        }
                    }
                    $sortedKeys[] = ['key' => $key, 'category' => $category, 'weight' => $weight, 'ageMin' => $ageMin];
                }
                usort($sortedKeys, fn($a, $b) => $a['ageMin'] <=> $b['ageMin'] ?: strcmp($a['weight'], $b['weight']));
                ?>
                <table>
                    <tr>
                        <th><?= e(__('club.area.age_class')) ?></th>
                        <th><?= e(__('club.area.weight_category')) ?></th>
                        <th><?= e(__('club.area.athlete')) ?></th>
                        <th><?= e(__('club.area.club')) ?></th>
                        <th><?= e(__('club.area.gender')) ?></th>
                        <th><?= e(__('club.area.belt')) ?></th>
                    </tr>
                    <?php foreach ($sortedKeys as $groupInfo) : ?>
                        <?php foreach ($grouped[$groupInfo['key']] as $athlete) : ?>
                            <?php $isCurrentClub = $loggedInClubId !== null && (int) $athlete['club_id'] === $loggedInClubId; ?>
                            <tr<?= $isCurrentClub ? ' class="club-row--current"' : '' ?>>
                                <td><?= e($groupInfo['category']) ?></td>
                                <td><?= e($groupInfo['weight']) ?></td>
                                <td><?= e($athlete['last_name'] . ' ' . $athlete['first_name']) ?></td>
                                <td>
                                    <?= e($athlete['club_name']) ?>
                                    <?php if ($isCurrentClub) : ?>
                                        <span class="club-badge--current"><?= e(__('club.list.current_club')) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e(__('gender.' . $athlete['gender'])) ?></td>
                                <td><?= e(\App\Model\Belt::tryFromValue($athlete['belt'])?->label(\App\Localization::getLocale()) ?? $athlete['belt']) ?></td>
                                </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Bottom Upcoming / Next Events Section -->
<?php if ($event === null && empty($upcomingEvents)) : ?>
    <div class="card">
        <p><?= e(__('events.none')) ?></p>
    </div>
<?php elseif (!empty($upcomingEvents)) : ?>
    <div class="card">
        <?php if ($event === null) : ?>
            <p><?= e(__('events.select_event')) ?></p>
        <?php endif; ?>

        <h3><?= e($event !== null ? __('events.upcoming_events') : __('events.upcoming_heading')) ?></h3>

        <?php foreach ($upcomingEvents as $next) : ?>
            <div class="event-line" style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <a class="btn btn-sm event-details-btn" href="<?= e(base_url('/events/entries?event=' . (string) $next->id)) ?>"><?= e(__('events.entries')) ?></a>
                <!-- Display Closed Badge if closed -->
                <?php if ($next->closed) : ?>
                    <span class="badge badge-closed" style="background-color: #6c757d; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">
                        <?= e(__('events.closed')) ?>
                    </span>
                <?php endif; ?>
                <span>
                    <?= e($next->date) ?> - <?= e($next->name) ?> - <?= e($next->location) ?> - (<?= e(__('events.registration_deadline')) ?>: <?= e($next->registration_deadline) ?>)
                </span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>