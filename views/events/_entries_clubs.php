<?php

/** @var \App\Presentation\EventEntriesViewModel $entryReport */
/** @var int|null $loggedInClubId */
/** @var bool $canViewEntryBreakdowns */
/** @var list<array<string, mixed>> $entryClubs */
/** @var array{page:int, per_page:int, total:int, last_page:int, offset:int, links:string} $entryClubsPagination */
?>

<section class="card">
    <h2><?= e(__('events.entries_clubs_heading')) ?></h2>
    <?php if ($entryClubs === [] && $entryReport->entries === []) : ?>
        <p><?= e(__('club.area.no_entries')) ?></p>
    <?php elseif ($entryClubs === []) : ?>
        <p><?= e(__('events.entries_no_clubs')) ?></p>
    <?php else : ?>
        <div
            class="table-scroll table-scroll--responsive"
            role="region"
            tabindex="0"
            aria-label="<?= e(__('events.entries_clubs_heading')) ?>"
        >
            <table
                class="responsive-table"
                data-sort-mode="server"
                data-sort-parameter="clubs_sort"
                data-sort-direction-parameter="clubs_direction"
                data-sort-page-parameter="clubs_page"
                data-sort-default="club"
            >
                <thead>
                    <tr>
                        <th scope="col" data-sortable="false">#</th>
                        <th scope="col" data-sort-key="club"><?= e(__('events.entries_club')) ?></th>
                        <th scope="col" data-sort-key="federal_code"><?= e(__('events.entries_code')) ?></th>
                        <th scope="col" data-sort-key="athletes"><?= e(__('events.entries_athletes')) ?></th>
                        <?php if ($canViewEntryBreakdowns) : ?>
                            <th scope="col" data-sort-key="breakdown"><?= e(__('events.entries_club_breakdown')) ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entryClubs as $index => $club) : ?>
                        <?php
                        $clubId = (int) ($club['id'] ?? 0);
                        $clubTotal = $entryReport->clubAthleteCounts[$clubId] ?? 0;
                        $isCurrentClub = $loggedInClubId !== null && $clubId === $loggedInClubId;
                        ?>
                        <tr<?= $isCurrentClub ? ' class="club-row--current"' : '' ?>>
                            <td data-label="#"><?= $entryClubsPagination['offset'] + (int) $index + 1 ?></td>
                            <td data-label="<?= e(__('events.entries_club')) ?>">
                                <strong><?= e((string) ($club['club_name'] ?? '')) ?></strong>
                                <?php if ($isCurrentClub) : ?>
                                    <span class="club-badge--current"><?= e(__('club.list.current_club')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="<?= e(__('events.entries_code')) ?>">
                                <?= e((string) ($club['federal_code'] ?? '')) ?>
                            </td>
                            <td data-label="<?= e(__('events.entries_athletes')) ?>">
                                <?= e((string) $clubTotal) ?>
                            </td>
                            <?php if ($canViewEntryBreakdowns) : ?>
                                <td data-label="<?= e(__('events.entries_club_breakdown')) ?>">
                                    <?php if ($clubTotal > 0) : ?>
                                        <div class="entries-club-breakdowns">
                                            <?php foreach ($entryReport->dimensions as $dimension) : ?>
                                                <?php require __DIR__ . '/_entries_stacked_bar.php'; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $entryClubsPagination['links'] ?>
    <?php endif; ?>
</section>
