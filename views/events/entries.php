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
/** @var array<int, \App\Model\Event> $events */
/** @var array<int, \App\Model\Event> $nextEvents */
/** @var array<int, \App\Model\Event> $upcomingEvents */
?>
<?php if ($event === null) : ?>
    <div class="card">
        <h2><?= e(__('events.entries_title')) ?></h2>
        <p><?= e(__('events.select_event')) ?></p>
        <?php if (!$events) : ?>
            <p><?= e(__('events.none')) ?></p>
        <?php else : ?>
            <ul class="next-events-list">
                <?php foreach ($events as $ev) : ?>
                    <li class="next-event-item">
                        <a href="<?= e(base_url('/events/entries?' . http_build_query(['event' => $ev->id]))) ?>">
                            <strong><?= e($ev->name) ?></strong><br>
                            <span class="location"><?= e($ev->location) ?> — <?= e($ev->date) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php else : ?>
    <div class="card">
        <h2><?= e(__('events.entries_title')) ?></h2>
        <h3><?= e($event->name) ?></h3>
        <p>
            <strong><?= e(__('events.date')) ?>:</strong> <?= e($event->date) ?><br>
            <strong><?= e(__('events.location')) ?>:</strong> <?= e($event->location) ?><br>
        </p>
        
        <?php if (!empty($categoryCounts) || !empty($beltCounts) || !empty($genderCounts) || !empty($weightCounts)) : ?>
            <div class="recap-summary" style="margin-top: 1rem; padding: 1rem; background: #f7f9fc; border-radius: 8px;">
                <h4 style="margin-top: 0;"><?= e(__('events.entries_recap')) ?></h4>
                <p>
                    <strong><?= e(__('events.entries_subscribed')) ?>:</strong> <?= e((string) count($rows)) ?><br>
                    <?php if ($event->max_participants !== null) : ?>
                    <strong><?= e(__('admin.events.max_participants')) ?>:</strong> <?= e((string) $event->max_participants) ?><br>
                    <strong><?= e(__('events.entries_free_spots')) ?>:</strong> <?= e((string) max(0, $event->max_participants - count($rows))) ?><br>
                    <?php endif; ?>
                </p>
                
                <?php
                // Colors mapped to actual age class keys from AgeClass model (shades of violet)
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
                
                // Calculate category pie chart data (by age class only)
                $categoryTotal = array_sum($categoryCounts);
                $categorySegments = [];
                
                // Sort categorySegments by age class
                $sortedCategoryCounts = [];
                foreach ($categoryCounts as $category => $count) {
                    $ageClassKey = null;
                    $ageMin = PHP_INT_MAX;
                    foreach (\App\Model\AgeClass::all() as $ac) {
                        if (str_contains($ac->label(), $category) || str_starts_with($ac->label(), $category)) {
                            $ageClassKey = $ac->key;
                            $ageMin = $ac->ageMin;
                            break;
                        }
                    }
                    $sortedCategoryCounts[] = ['category' => $category, 'count' => $count, 'ageMin' => $ageMin, 'ageClassKey' => $ageClassKey];
                }
                usort($sortedCategoryCounts, function($a, $b) {
                    return $a['ageMin'] <=> $b['ageMin'];
                });
                
                foreach ($sortedCategoryCounts as $item) {
                    $percentage = ($categoryTotal > 0) ? ($item['count'] / $categoryTotal) * 100 : 0;
                    $color = $ageClassColors[$item['ageClassKey'] ?? ''] ?? '#b39ddb';
                    $categorySegments[] = ['label' => $item['category'], 'count' => $item['count'], 'percentage' => $percentage, 'color' => $color];
                }
                ?>
                
                <?php
                // Calculate weight pie chart data (grouped every 4kg from 12kg onward, above 100kg)
                $weightTotal = array_sum($weightCounts);
                $weightSegments = [];
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
                ];
                
                // Sort weight segments by lower bound
                $weightSortOrder = [
                    'under-12kg' => 0,
                    '12-16kg' => 1, '16-20kg' => 2, '20-24kg' => 3, '24-28kg' => 4, '28-32kg' => 5,
                    '32-36kg' => 6, '36-40kg' => 7, '40-44kg' => 8, '44-48kg' => 9, '48-52kg' => 10,
                    '52-56kg' => 11, '56-60kg' => 12, '60-64kg' => 13, '64-68kg' => 14, '68-72kg' => 15,
                    '72-76kg' => 16, '76-80kg' => 17, '80-84kg' => 18, '84-88kg' => 19, '88-92kg' => 20,
                    '92-96kg' => 21, '96-100kg' => 22, '100+kg' => 23,
                ];
                
                $weightGroupKeys = array_keys($weightSortOrder);
                foreach ($weightGroupKeys as $weightGroup) {
                    $count = $weightCounts[$weightGroup] ?? 0;
                    if ($count > 0) {
                        $percentage = ($weightTotal > 0) ? ($count / $weightTotal) * 100 : 0;
                        $weightSegments[] = ['label' => $weightGroup, 'count' => $count, 'percentage' => $percentage, 'color' => $weightColors[$weightGroup] ?? '#b39ddb'];
                    }
                }
                // Handle unspecified/empty weights that may not be in the sort order
                if (isset($weightCounts['unspecified'])) {
                    $count = $weightCounts['unspecified'];
                    if ($count > 0) {
                        $percentage = ($weightTotal > 0) ? ($count / $weightTotal) * 100 : 0;
                        $weightSegments[] = ['label' => 'unspecified', 'count' => $count, 'percentage' => $percentage, 'color' => '#b39ddb'];
                    }
                }
                ?>
                
                <?php
                // Calculate belt pie chart data
                $beltTotal = array_sum($beltCounts);
                $beltSegments = [];
                
                foreach ($beltCounts as $belt => $count) {
                    $percentage = ($beltTotal > 0) ? ($count / $beltTotal) * 100 : 0;
                    $beltEnum = \App\Model\Belt::tryFromValue($belt ?? '');
                    $color = '#6c757d';
                    if ($beltEnum !== null) {
                        $components = $beltEnum->components();
                        $color = $components[0]['color'];
                    }
                    $beltSegments[] = ['label' => $belt, 'count' => $count, 'percentage' => $percentage, 'color' => $color];
                }
                // Sort beltSegments by belt rank (enum order)
                $beltRankOrder = array_flip(array_map(fn($case) => $case->value, \App\Model\Belt::cases()));
                usort($beltSegments, function($a, $b) use ($beltRankOrder) {
                    $aRank = $beltRankOrder[$a['label']] ?? PHP_INT_MAX;
                    $bRank = $beltRankOrder[$b['label']] ?? PHP_INT_MAX;
                    return $aRank <=> $bRank;
                });
                ?>
                <?php
                // Calculate gender pie chart data with pastel colors
                $genderTotal = array_sum($genderCounts);
                $genderSegments = [];
                $genderColors = ['M' => '#90caf9', 'F' => '#f48fb1']; // Pastel blue for Male, Pastel pink for Female
                foreach ($genderCounts as $gender => $count) {
                    $percentage = ($genderTotal > 0) ? ($count / $genderTotal) * 100 : 0;
                    $genderSegments[] = ['label' => $gender, 'count' => $count, 'percentage' => $percentage, 'color' => $genderColors[$gender] ?? '#6c757d'];
                }
                // Sort genderSegments: Female first, then Male
                $genderOrder = ['F' => 0, 'M' => 1];
                usort($genderSegments, function($a, $b) use ($genderOrder) {
                    $aOrder = $genderOrder[$a['label']] ?? PHP_INT_MAX;
                    $bOrder = $genderOrder[$b['label']] ?? PHP_INT_MAX;
                    return $aOrder <=> $bOrder;
                });
                ?>
                
                <!-- Charts on single line occupying full width -->
                <div style="display: flex; flex-direction: column; margin-bottom: 1rem; width: 100%;">
                    <?php
                    $chartCount = 0;
                    if (!empty($categorySegments)) $chartCount++;
                    if (!empty($weightSegments)) $chartCount++;
                    if (!empty($beltSegments)) $chartCount++;
                    if (!empty($genderSegments)) $chartCount++;
                    $chartWidth = $chartCount > 0 ? (100 / ($chartCount + 1)) : 100;
                    ?>
                    <div style="display: flex; justify-content: space-around; flex-wrap: wrap; gap: 15px; margin-bottom: 1rem; width: 100%;">
                        <?php if (!empty($categorySegments)) : ?>
                        <svg viewBox="0 0 100 100" style="width: <?= e((string) $chartWidth) ?>%; max-width: 240px; height: auto; aspect-ratio: 1 / 1;">
                            <?php $startAngle = 0; ?>
                            <?php foreach ($categorySegments as $segment) : ?>
                                <?php 
                                $endAngle = $startAngle + ($segment['percentage'] / 100) * 360;
                                $largeArc = ($endAngle - $startAngle) > 180 ? 1 : 0;
                                $startX = 50 + 40 * cos(deg2rad($startAngle - 90));
                                $startY = 50 + 40 * sin(deg2rad($startAngle - 90));
                                $endX = 50 + 40 * cos(deg2rad($endAngle - 90));
                                $endY = 50 + 40 * sin(deg2rad($endAngle - 90));
                                ?>
                                <path d="M50,50 L<?= e((string) $startX) ?>,<?= e((string) $startY) ?> A40,40 0 <?= e((string) $largeArc) ?>,1 <?= e((string) $endX) ?>,<?= e((string) $endY) ?> Z" fill="<?= e($segment['color']) ?>"/>
                                <?php $startAngle = $endAngle; ?>
                            <?php endforeach; ?>
                        </svg>
                        <?php endif; ?>
                        
                        <?php if (!empty($weightSegments)) : ?>
                        <svg viewBox="0 0 100 100" style="width: <?= e((string) $chartWidth) ?>%; max-width: 240px; height: auto; aspect-ratio: 1 / 1;">
                            <?php $startAngle = 0; ?>
                            <?php foreach ($weightSegments as $segment) : ?>
                                <?php 
                                $endAngle = $startAngle + ($segment['percentage'] / 100) * 360;
                                $largeArc = ($endAngle - $startAngle) > 180 ? 1 : 0;
                                $startX = 50 + 40 * cos(deg2rad($startAngle - 90));
                                $startY = 50 + 40 * sin(deg2rad($startAngle - 90));
                                $endX = 50 + 40 * cos(deg2rad($endAngle - 90));
                                $endY = 50 + 40 * sin(deg2rad($endAngle - 90));
                                ?>
                                <path d="M50,50 L<?= e((string) $startX) ?>,<?= e((string) $startY) ?> A40,40 0 <?= e((string) $largeArc) ?>,1 <?= e((string) $endX) ?>,<?= e((string) $endY) ?> Z" fill="<?= e($segment['color']) ?>"/>
                                <?php $startAngle = $endAngle; ?>
                            <?php endforeach; ?>
                        </svg>
                        <?php endif; ?>
                        
                        <?php if (!empty($beltSegments)) : ?>
                        <svg viewBox="0 0 100 100" style="width: <?= e((string) $chartWidth) ?>%; max-width: 240px; height: auto; aspect-ratio: 1 / 1;">
                            <?php $startAngle = 0; ?>
                            <?php foreach ($beltSegments as $segment) : ?>
                                <?php 
                                $endAngle = $startAngle + ($segment['percentage'] / 100) * 360;
                                $largeArc = ($endAngle - $startAngle) > 180 ? 1 : 0;
                                $startX = 50 + 40 * cos(deg2rad($startAngle - 90));
                                $startY = 50 + 40 * sin(deg2rad($startAngle - 90));
                                $endX = 50 + 40 * cos(deg2rad($endAngle - 90));
                                $endY = 50 + 40 * sin(deg2rad($endAngle - 90));
                                ?>
                                <path d="M50,50 L<?= e((string) $startX) ?>,<?= e((string) $startY) ?> A40,40 0 <?= e((string) $largeArc) ?>,1 <?= e((string) $endX) ?>,<?= e((string) $endY) ?> Z" fill="<?= e($segment['color']) ?>"/>
                                <?php $startAngle = $endAngle; ?>
                            <?php endforeach; ?>
                        </svg>
                        <?php endif; ?>
                        
                        <?php if (!empty($genderSegments)) : ?>
                        <svg viewBox="0 0 100 100" style="width: <?= e((string) $chartWidth) ?>%; max-width: 240px; height: auto; aspect-ratio: 1 / 1;">
                            <?php $startAngle = 0; ?>
                            <?php foreach ($genderSegments as $segment) : ?>
                                <?php 
                                $endAngle = $startAngle + ($segment['percentage'] / 100) * 360;
                                $largeArc = ($endAngle - $startAngle) > 180 ? 1 : 0;
                                $startX = 50 + 40 * cos(deg2rad($startAngle - 90));
                                $startY = 50 + 40 * sin(deg2rad($startAngle - 90));
                                $endX = 50 + 40 * cos(deg2rad($endAngle - 90));
                                $endY = 50 + 40 * sin(deg2rad($endAngle - 90));
                                ?>
                                <path d="M50,50 L<?= e((string) $startX) ?>,<?= e((string) $startY) ?> A40,40 0 <?= e((string) $largeArc) ?>,1 <?= e((string) $endX) ?>,<?= e((string) $endY) ?> Z" fill="<?= e($segment['color']) ?>"/>
                                <?php $startAngle = $endAngle; ?>
                            <?php endforeach; ?>
                        </svg>
                        <?php endif; ?>
                        
                    </div>
                    
                    <!-- Legend below charts -->
                    <div style="display: flex; flex-direction: column; gap: 4px; width: 100%;">
                        <?php if (!empty($categorySegments)) : ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 12px; font-size: 0.85rem;">
                            <strong style="margin-right: 8px;"><?= e(__('events.entries_category_breakdown')) ?>:</strong>
                            <?php foreach ($categorySegments as $segment) : ?>
                                <span style="display: flex; align-items: center; gap: 4px;">
                                    <span style="display: inline-block; width: 10px; height: 10px; background: <?= e($segment['color']) ?>; border-radius: 2px;"></span>
                                    <?= e($segment['label']) ?>: <?= e((string) $segment['count']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($weightSegments)) : ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 12px; font-size: 0.85rem;">
                            <strong style="margin-right: 8px;"><?= e(__('events.entries_weight_breakdown')) ?>:</strong>
                            <?php foreach ($weightSegments as $segment) : ?>
                                <span style="display: flex; align-items: center; gap: 4px;">
                                    <span style="display: inline-block; width: 10px; height: 10px; background: <?= e($segment['color']) ?>; border-radius: 2px;"></span>
                                    <?= e($segment['label']) ?>: <?= e((string) $segment['count']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($beltSegments)) : ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 12px; font-size: 0.85rem;">
                            <strong style="margin-right: 8px;"><?= e(__('events.entries_belt_breakdown')) ?>:</strong>
                            <?php foreach ($beltSegments as $segment) : ?>
                                <span style="display: flex; align-items: center; gap: 4px;">
                                    <span style="display: inline-block; width: 10px; height: 10px; border: 1px solid #ccc; background: <?= e($segment['color']) ?>; border-radius: 2px;"></span>
                                    <?= e(\App\Model\Belt::tryFromValue($segment['label'] ?? '')?->label(\App\Localization::getLocale()) ?? ($segment['label'] ?? '')) ?>: <?= e((string) $segment['count']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($genderSegments)) : ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 12px; font-size: 0.85rem;">
                            <strong style="margin-right: 8px;"><?= e(__('gender.gender')) ?>:</strong>
                            <?php foreach ($genderSegments as $segment) : ?>
                                <span style="display: flex; align-items: center; gap: 4px;">
                                    <span style="display: inline-block; width: 10px; height: 10px; background: <?= e($segment['color']) ?>; border-radius: 2px;"></span>
                                    <?= e(__('gender.' . $segment['label'])) ?>: <?= e((string) $segment['count']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <p>
            <a class="btn" href="<?= e(base_url('/events/details?event=' . (string) $event->id)) ?>"><?= e(__('events.details')) ?></a>
            <?php if (!$event->closed || $hasRegistrationException) : ?>
            <a class="btn green" href="<?= e(base_url('/events/register?id=' . (string) $event->id)) ?>"><?= e(__('events.registration')) ?></a>
            <?php endif; ?>
        </p>
    </div>

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
                    $clubCats = $clubCategoryCounts[$club['id']] ?? [];
                    $clubWeights = $clubWeightCounts[$club['id']] ?? [];
                    $clubBelts = $clubBeltCounts[$club['id']] ?? [];
                    $clubGenders = $clubGenderCounts[$club['id']] ?? [];
                    ?>
                    <tr<?= $loggedInClubId !== null && (int) $club['id'] === $loggedInClubId ? ' class="club-row--current"' : '' ?>>
                        <td><?= (int) $i + 1 ?></td>
                        <td><strong><?= e($club['club_name']) ?></strong><?php if ($loggedInClubId !== null && (int) $club['id'] === $loggedInClubId) : ?> <span class="club-badge--current"><?= e(__('club.list.current_club')) ?></span><?php endif; ?></td>
                        <td><?= e($club['federal_code']) ?></td>
                        <td><?= e((string) $clubTotal) ?></td>
                        <td>
                            <?php if ($clubTotal > 0) : ?>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <div style="display: flex; height: 6px;">
                                        <?php foreach ($categorySegments ?? [] as $segment) : ?>
                                            <?php $count = $clubCats[$segment['label']] ?? 0; ?>
                                            <?php $width = ($clubTotal > 0) ? ($count / $clubTotal) * 100 : 0; ?>
                                            <div title="<?= e($segment['label'] . ': ' . $count) ?>" style="background: <?= e($segment['color']) ?>; width: <?= e((string) $width) ?>%; height: 100%;"></div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div style="display: flex; height: 6px;">
                                        <?php foreach ($weightSegments ?? [] as $segment) : ?>
                                            <?php $count = $clubWeights[$segment['label']] ?? 0; ?>
                                            <?php $width = ($clubTotal > 0) ? ($count / $clubTotal) * 100 : 0; ?>
                                            <div title="<?= e($segment['label'] . ': ' . $count) ?>" style="background: <?= e($segment['color']) ?>; width: <?= e((string) $width) ?>%; height: 100%;"></div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div style="display: flex; height: 6px;">
                                        <?php foreach ($beltSegments ?? [] as $segment) : ?>
                                            <?php $count = $clubBelts[$segment['label']] ?? 0; ?>
                                            <?php $width = ($clubTotal > 0) ? ($count / $clubTotal) * 100 : 0; ?>
                                            <div title="<?= e(\App\Model\Belt::tryFromValue($segment['label'] ?? '')?->label(\App\Localization::getLocale()) ?? ($segment['label'] ?? '') . ': ' . $count) ?>" style="background: <?= e($segment['color']) ?>; border: 1px solid #ccc; width: <?= e((string) $width) ?>%; height: 100%;"></div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div style="display: flex; height: 6px;">
                                        <?php foreach ($genderSegments ?? [] as $segment) : ?>
                                            <?php $count = $clubGenders[$segment['label']] ?? 0; ?>
                                            <?php $width = ($clubTotal > 0) ? ($count / $clubTotal) * 100 : 0; ?>
                                            <div title="<?= e(__('gender.' . $segment['label']) . ': ' . $count) ?>" style="background: <?= e($segment['color']) ?>; width: <?= e((string) $width) ?>%; height: 100%;"></div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <?php if ($event->closed) : ?>
    <div class="card">
        <h2><?= e(__('events.entries_athletes_heading')) ?></h2>
        <?php if (empty($grouped)) : ?>
            <p><?= e(__('club.area.no_entries')) ?></p>
        <?php else : ?>
            <?php
            // Sort grouped entries by category (age class) then weight
            $sortedKeys = [];
            foreach (array_keys($grouped) as $key) {
                $parts = explode(' | ', $key, 2);
                $category = $parts[0] ?? '';
                $weight = $parts[1] ?? '';
                
                // Get ageMin for category sorting
                $ageMin = PHP_INT_MAX;
                foreach (\App\Model\AgeClass::all() as $ac) {
                    if (str_contains($ac->label(), $category) || str_starts_with($ac->label(), $category)) {
                        $ageMin = $ac->ageMin;
                        break;
                    }
                }
                
                $sortedKeys[] = ['key' => $key, 'category' => $category, 'weight' => $weight, 'ageMin' => $ageMin];
            }
            usort($sortedKeys, function($a, $b) {
                if ($a['ageMin'] !== $b['ageMin']) {
                    return $a['ageMin'] <=> $b['ageMin'];
                }
                // Compare weights within the same category
                return strcmp($a['weight'], $b['weight']);
            });
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
                    <?php 
                    $groupKey = $groupInfo['key'];
                    $athletes = $grouped[$groupKey];
                    $parts = explode(' | ', $groupKey, 2);
                    $categoryLabel = $parts[0] ?? '';
                    $weightLabel = $parts[1] ?? '';
                    ?>
                    <?php foreach ($athletes as $athlete) : ?>
                        <?php $athleteClubId = (int) ($athlete['club_id'] ?? 0); ?>
                        <tr<?= $loggedInClubId !== null && $athleteClubId === $loggedInClubId ? ' class="club-row--current"' : '' ?>>
                            <td><?= e($categoryLabel) ?></td>
                            <td><?= e($weightLabel) ?></td>
                            <td><?= e($athlete['last_name'] . ' ' . $athlete['first_name']) ?></td>
                            <td><?= e($athlete['club_name'] ?? '') ?><?php if ($loggedInClubId !== null && $athleteClubId === $loggedInClubId) : ?> <span class="club-badge--current"><?= e(__('club.list.current_club')) ?></span><?php endif; ?></td>
                            <td><?= e(__('gender.' . ($athlete['gender'] ?? ''))) ?></td>
                            <td><?= e(\App\Model\Belt::tryFromValue($athlete['belt'] ?? '')?->label(\App\Localization::getLocale()) ?? ($athlete['belt'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($event === null) : ?>
    <p><?= e(__('events.select_event')) ?></p>
<?php endif; ?>
<div class="card">
    <h3><?= e($event !== null ? __('events.upcoming_events') : __('events.upcoming_heading')) ?></h3>
    <?php
    $eventsList = $event !== null ? $nextEvents : $upcomingEvents;
    ?>
    <?php if (!empty($eventsList)) : ?>
        <?php foreach ($eventsList as $next) : ?>
            <div class="event-line">
                <a class="btn green btn-sm event-details-btn" href="<?= e(base_url('/events/register?id=' . (string) $next->id)) ?>"><?= e(__('events.registration')) ?></a>
                <?= e($next->date) ?>
                - <?= e($next->name) ?>
                - <?= e($next->location) ?>
                - (<?= e(__('events.registration_deadline')) ?>: <?= e($next->registration_deadline) ?>)
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <p><?= e(__('events.none')) ?></p>
    <?php endif; ?>
</div>