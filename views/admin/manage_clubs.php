<?php /** @var list<\App\Model\Club> $clubs */ ?>
<?php /** @var array<int, int> $athlete_counts */ ?>
<?php /** @var array{page: int, per_page: int, total: int, last_page: int, offset: int, links: string} $pagination */ ?>
<section class="card admin-list-page">
    <header class="admin-list-heading">
        <h2>
            <?= e(__('admin.clubs.title')) ?>
            <span class="count-badge"><?= e((string) $pagination['total']) ?></span>
        </h2>
    </header>

    <?php if (empty($clubs)) : ?>
        <p class="admin-list-empty"><?= e(__('admin.clubs.empty')) ?></p>
    <?php else : ?>
        <div class="admin-card-list" role="list">
            <?php foreach ($clubs as $club) : ?>
                <?php
                $contactName = trim($club->contact_first_name . ' ' . $club->contact_last_name);
                $locality = trim(implode(' ', array_filter([
                    $club->postal_code,
                    $club->city,
                    $club->province !== '' ? '(' . $club->province . ')' : null,
                ])));
                $address = implode(', ', array_filter([$club->address_line, $locality]));
                $affiliations = implode(', ', $club->affiliations());
                $athleteCount = $athlete_counts[$club->id] ?? 0;
                ?>
                <article class="admin-entity-card" role="listitem">
                    <header class="admin-entity-card__header">
                        <h3><?= e($club->name) ?></h3>
                        <span class="admin-code-badge"><?= e($club->federal_code) ?></span>
                    </header>

                    <dl class="admin-entity-details">
                        <div>
                            <dt><?= e(__('admin.clubs.email')) ?></dt>
                            <dd><a href="<?= e('mailto:' . $club->email) ?>"><?= e($club->email) ?></a></dd>
                        </div>
                        <div>
                            <dt><?= e(__('admin.clubs.phone')) ?></dt>
                            <dd>
                                <?php if ($club->phone !== '') : ?>
                                    <a href="<?= e('tel:' . $club->phone) ?>"><?= e($club->phone) ?></a>
                                <?php else : ?>
                                    <?= e(__('admin.common.not_available')) ?>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div>
                            <dt><?= e(__('admin.clubs.contact')) ?></dt>
                            <dd><?= e($contactName !== '' ? $contactName : __('admin.common.not_available')) ?></dd>
                        </div>
                        <div>
                            <dt><?= e(__('admin.clubs.address')) ?></dt>
                            <dd><?= e($address !== '' ? $address : __('admin.common.not_available')) ?></dd>
                        </div>
                        <div>
                            <dt><?= e(__('admin.clubs.affiliation')) ?></dt>
                            <dd><?= e($affiliations !== '' ? $affiliations : __('admin.common.not_available')) ?></dd>
                        </div>
                        <div>
                            <dt><?= e(__('admin.clubs.athletes')) ?></dt>
                            <dd>
                                <strong><?= e((string) $athleteCount) ?></strong>
                                <?= e(__('admin.clubs.athlete_count')) ?>
                            </dd>
                        </div>
                    </dl>

                    <div class="admin-card-actions">
                        <a
                            class="btn btn-sm"
                            href="<?= e(base_url('/admin/clubs/athletes?club_id=' . (int) $club->id)) ?>"
                        ><?= e(__('admin.clubs.view_athletes')) ?></a>
                        <a
                            class="btn btn-sm"
                            href="<?= e(base_url('/admin/clubs/athletes/export?club_id=' . (int) $club->id)) ?>"
                        ><?= e(__('admin.clubs.export_athletes')) ?></a>
                        <a
                            class="btn btn-sm green"
                            href="<?= e(base_url('/admin/clubs/edit?id=' . (int) $club->id)) ?>"
                        ><?= e(__('admin.clubs.edit')) ?></a>
                        <form method="post" action="<?= e(base_url('/admin/clubs/delete?')) ?>" onsubmit="return confirm('<?= e(__('admin.clubs.confirm_delete')) ?>')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="club_id" value="<?= (int) $club->id ?>">
                            <button class="btn btn-sm red" type="submit"><?= e(__('admin.clubs.delete')) ?></button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?= $pagination['links'] ?>
</section>
