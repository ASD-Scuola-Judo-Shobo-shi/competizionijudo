<?php /** @var array{page: int, per_page: int, total: int, last_page: int, offset: int, links: string} $pagination */ ?>
<?php /** @var int|null $loggedInClubId */ ?>
<section class="card public-club-directory">
    <div class="public-club-directory__heading">
        <h3>
            <?= e(__('club.list.title')) ?>
            <span class="count-badge"><?= e((string) $pagination['total']) ?></span>
        </h3>
    </div>
    <?php if (empty($clubs)) : ?>
        <p><?= e(__('club.no_clubs')) ?></p>
    <?php else : ?>
        <div
            class="table-scroll table-scroll--responsive public-club-table-wrap"
            role="region"
            tabindex="0"
            aria-label="<?= e(__('club.list.title')) ?>"
        >
            <table class="table-full responsive-table public-club-table">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col"><?= e(__('club.list.table_name')) ?></th>
                        <th scope="col"><?= e(__('club.list.table_code')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clubs as $i => $club) : ?>
                        <?php $isCurrentClub = $loggedInClubId !== null && $club->id === $loggedInClubId; ?>
                        <tr<?= $isCurrentClub ? ' class="club-row--current"' : '' ?>>
                            <td data-label="#">
                                <span class="public-club-index">
                                    #<?= e((string) ($pagination['offset'] + $i + 1)) ?>
                                </span>
                            </td>
                            <td class="public-club-table__name" data-label="<?= e(__('club.name')) ?>">
                                <?= e($club->name) ?>
                                <?php if ($isCurrentClub) : ?>
                                    <span class="club-badge--current"><?= e(__('club.list.current_club')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="<?= e(__('club.federal_code')) ?>">
                                <span class="public-club-code"><?= e($club->federal_code) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?= $pagination['links'] ?>
</section>
