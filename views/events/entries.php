<?php

/** @var \App\Model\Event $event */
/** @var array $clubs */
/** @var array $rows */
/** @var array $grouped */
/** @var array|null $selectedClub */
/** @var int $clubFilter */
/** @var bool $isAdmin */
?>

<div class="card">
    <h2><?= e(__('events.entries_title')) ?></h2>
    <h3><?= e($event->name) ?></h3>
    <p>
        <strong><?= e(__('events.date')) ?>:</strong> <?= e($event->date) ?><br>
        <strong><?= e(__('events.location')) ?>:</strong> <?= e($event->location) ?><br>
        <strong><?= e(__('events.entries_athletes_count')) ?>:</strong> <?= e((string) count($rows)) ?><br>
    </p>
    <p>
        <a class="btn" href="/events.php"><?= e(__('events.back')) ?></a>
        <button class="btn green" onclick="window.print()" type="button"><?= e(__('events.entries_print')) ?></button>
    </p>
</div>

<div class="card">
    <h2><?= e(__('events.entries_clubs_heading')) ?></h2>
    <?php if (!$clubs) : ?>
        <p><?= e(__('events.entries_no_clubs')) ?></p>
    <?php else : ?>
        <table>
            <tr>
                <th>#</th>
                <th><?= e(__('events.entries_club')) ?></th>
                <th><?= e(__('events.entries_code')) ?></th>
                <th><?= e(__('events.entries_details')) ?></th>
            </tr>
            <?php foreach ($clubs as $i => $club) : ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= e($club['club_name'] ?? '') ?></strong></td>
                    <td><?= e($club['federal_code'] ?? '') ?></td>
                    <td>
                        <a class="btn green" href="/event_details.php?event=<?= e($event->id) ?>&club=<?= e($club['id'] ?? '') ?>">
                            <?= e(__('events.entries_view_club')) ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<?php if ($clubFilter > 0) : ?>
    <div class="card">
        <h2><?= e(__('events.entries_group_heading')) ?></h2>
        <h3><?= e($selectedClub['club_name'] ?? '') ?></h3>
        <p><strong><?= e(__('events.entries_athletes_count')) ?>:</strong> <?= e((string) count($rows)) ?></p>

        <?php if (!$grouped) : ?>
            <p><?= e(__('events.entries_club_no_athletes')) ?></p>
        <?php endif; ?>

        <?php foreach ($grouped as $groupName => $athletes) : ?>
            <div class="card">
                <h3><?= e($groupName) ?> — <?= e((string) count($athletes)) ?> <?= e(__('events.entries_group_count')) ?></h3>
                <table>
                    <tr>
                        <th>#</th>
                        <th><?= e(__('club.area.last_name')) ?></th>
                        <th><?= e(__('club.area.first_name')) ?></th>
                        <th><?= e(__('club.area.birth')) ?></th>
                        <th><?= e(__('club.area.weight')) ?></th>
                        <th><?= e(__('club.area.belt')) ?></th>
                        <th><?= e(__('club.area.membership_number')) ?></th>
                    </tr>
                    <?php foreach ($athletes as $i => $row) : ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= e($row['last_name'] ?? '') ?></td>
                            <td><?= e($row['first_name'] ?? '') ?></td>
                            <td><?= e($row['birth_date'] ?? '') ?></td>
                            <td><?= e($row['weight_kg'] ?? '') ?></td>
                            <td><?= e(App\Model\Belt::tryFromValue($row['belt'] ?? '')?->label(App\Localization::getLocale()) ?? $row['belt'] ?? '') ?></td>
                            <td><?= e($row['membership_number'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
