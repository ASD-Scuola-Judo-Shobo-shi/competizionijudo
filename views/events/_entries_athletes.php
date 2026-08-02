<?php

/** @var \App\Presentation\EventEntriesViewModel $entryReport */
/** @var int|null $loggedInClubId */
?>

<section class="card">
    <h2><?= e(__('events.entries_athletes_heading')) ?></h2>
    <?php if ($entryReport->athleteGroups === []) : ?>
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
                    <?php foreach ($entryReport->athleteGroups as $group) : ?>
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
                                    <span
                                        class="table-density-value"
                                        title="<?= e((string) $athlete['gender_label']) ?>"
                                    ><?= e((string) $athlete['gender_icon']) ?></span>
                                    <span class="card-density-value">
                                        <?= e((string) $athlete['gender_label']) ?>
                                    </span>
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
    <?php endif; ?>
</section>
