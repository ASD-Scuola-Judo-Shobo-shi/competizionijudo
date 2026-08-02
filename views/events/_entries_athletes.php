<?php

/** @var \App\Presentation\EventEntriesViewModel $entryReport */
/** @var int|null $loggedInClubId */
/** @var list<array{category:string, weight:string, ageMin:int, athletes:list<array<string, mixed>>}> $entryAthleteGroups */
/** @var array{page:int, per_page:int, total:int, last_page:int, offset:int, links:string} $entryAthletesPagination */
?>

<section class="card">
    <h2><?= e(__('events.entries_athletes_heading')) ?></h2>
    <?php if ($entryAthleteGroups === []) : ?>
        <p><?= e(__('club.area.no_entries')) ?></p>
    <?php else : ?>
        <div
            class="table-scroll table-scroll--wide table-scroll--responsive"
            role="region"
            tabindex="0"
            aria-label="<?= e(__('events.entries_athletes_heading')) ?>"
        >
            <table class="responsive-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('club.area.table.age_class')) ?></th>
                        <th scope="col"><?= e(__('club.area.table.weight_category')) ?></th>
                        <th scope="col"><?= e(__('club.area.table.athlete')) ?></th>
                        <th scope="col"><?= e(__('club.area.table.club')) ?></th>
                        <th scope="col"><?= e(__('club.area.table.gender')) ?></th>
                        <th scope="col"><?= e(__('club.area.table.belt')) ?></th>
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
                                    <?= e((string) $athlete['belt_label']) ?>
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
