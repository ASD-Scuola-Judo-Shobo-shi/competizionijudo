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
        <ol class="public-club-list">
            <?php foreach ($clubs as $i => $club) : ?>
                <?php $isCurrentClub = $loggedInClubId !== null && $club->id === $loggedInClubId; ?>
                <li class="public-club-card<?= $isCurrentClub ? ' public-club-card--current' : '' ?>">
                    <div class="public-club-card__meta">
                        <span class="public-club-index">#<?= e((string) ($pagination['offset'] + $i + 1)) ?></span>
                        <?php if ($isCurrentClub) : ?>
                            <span class="club-badge--current"><?= e(__('club.list.current_club')) ?></span>
                        <?php endif; ?>
                    </div>
                    <h4><?= e($club->name) ?></h4>
                    <dl class="public-club-details">
                        <div>
                            <dt><?= e(__('club.federal_code')) ?></dt>
                            <dd><span class="public-club-code"><?= e($club->federal_code) ?></span></dd>
                        </div>
                    </dl>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
    <?= $pagination['links'] ?>
</section>
