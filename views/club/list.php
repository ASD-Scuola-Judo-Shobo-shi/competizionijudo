<?php /** @var array{page: int, per_page: int, total: int, last_page: int, offset: int, links: string} $pagination */ ?>
<?php /** @var int|null $loggedInClubId */ ?>
<div class="card">
    <h3><?= e(__('club.list.title')) ?></h3>
    <?php if (empty($clubs)) : ?>
        <p><?= e(__('club.no_clubs')) ?></p>
    <?php else : ?>
        <table class="table-full">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= e(__('club.name')) ?></th>
                    <th><?= e(__('club.federal_code')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clubs as $i => $club) : ?>
                    <tr<?= $loggedInClubId !== null && $club->id === $loggedInClubId ? ' class="club-row--current"' : '' ?>>
                        <td><?= $pagination['offset'] + $i + 1 ?></td>
                        <td><?= e($club->name) ?><?php if ($loggedInClubId !== null && $club->id === $loggedInClubId) : ?> <span class="club-badge--current"><?= e(__('club.list.current_club')) ?></span><?php endif; ?></td>
                        <td><?= e($club->federal_code) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <?= $pagination['links'] ?>
</div>