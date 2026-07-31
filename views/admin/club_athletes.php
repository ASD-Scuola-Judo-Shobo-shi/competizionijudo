<?php /** @var \App\Model\Club $club */ ?>
<?php /** @var list<\App\Model\Athlete> $athletes */ ?>
<?php /** @var array{page: int, per_page: int, total: int, last_page: int, offset: int, links: string} $pagination */ ?>
<section class="card admin-list-page">
    <header class="admin-list-heading admin-list-heading--with-actions">
        <div>
            <p class="admin-list-eyebrow"><?= e($club->federal_code) ?></p>
            <h2>
                <?= e(__('admin.clubs.athletes_title', ['club' => $club->name])) ?>
                <span class="count-badge"><?= e((string) $pagination['total']) ?></span>
            </h2>
        </div>
        <div class="admin-heading-actions">
            <a class="btn gray" href="<?= e(base_url('/admin/clubs')) ?>">
                <?= e(__('admin.clubs.back_to_clubs')) ?>
            </a>
            <a
                class="btn"
                href="<?= e(base_url('/admin/clubs/athletes/export?club_id=' . $club->id)) ?>"
            ><?= e(__('admin.clubs.export_athletes')) ?></a>
        </div>
    </header>

    <?php if (empty($athletes)) : ?>
        <p class="admin-list-empty"><?= e(__('admin.clubs.no_athletes')) ?></p>
    <?php else : ?>
        <div class="admin-card-list admin-athlete-card-list" role="list">
            <?php foreach ($athletes as $athlete) : ?>
                <?php
                $category = $athlete->categoryForEventDate();
                $ageClass = $athlete->ageClassLabel(App\Localization::getLocale());
                $weight = $athlete->weight_kg !== null
                    ? rtrim(rtrim(number_format($athlete->weight_kg, 2, '.', ''), '0'), '.')
                    : null;
                ?>
                <article class="admin-entity-card admin-athlete-card" role="listitem">
                    <header class="admin-entity-card__header">
                        <h3><?= e($athlete->last_name . ' ' . $athlete->first_name) ?></h3>
                        <?php if ($athlete->membership_number !== null) : ?>
                            <span class="admin-code-badge"><?= e($athlete->membership_number) ?></span>
                        <?php endif; ?>
                    </header>

                    <dl class="admin-entity-details">
                        <div>
                            <dt><?= e(__('club.area.gender')) ?></dt>
                            <dd><?= e($athlete->genderIconLabel()) ?></dd>
                        </div>
                        <div>
                            <dt><?= e(__('club.area.birth')) ?></dt>
                            <dd><time datetime="<?= e($athlete->birth_date) ?>"><?= e($athlete->birth_date) ?></time></dd>
                        </div>
                        <div>
                            <dt><?= e(__('club.area.age_class')) ?></dt>
                            <dd><?= e($ageClass !== '' ? $ageClass : __('admin.common.not_available')) ?></dd>
                        </div>
                        <div>
                            <dt><?= e(__('club.area.weight')) ?></dt>
                            <dd>
                                <?= e($weight !== null
                                    ? __('admin.clubs.weight_value', ['weight' => $weight])
                                    : __('events.no_weight')) ?>
                            </dd>
                        </div>
                        <div>
                            <dt><?= e(__('club.area.belt')) ?></dt>
                            <dd class="admin-belt-value">
                                <?php require dirname(__DIR__) . '/components/belt_badge.php'; ?>
                            </dd>
                        </div>
                        <div>
                            <dt><?= e(__('club.area.weight_category')) ?></dt>
                            <dd>
                                <?= e($category['weight_category'] !== ''
                                    ? $category['weight_category']
                                    : __('admin.common.not_available')) ?>
                            </dd>
                        </div>
                        <div>
                            <dt><?= e(__('club.area.membership_number')) ?></dt>
                            <dd>
                                <?= e($athlete->membership_number ?? __('admin.common.not_available')) ?>
                            </dd>
                        </div>
                        <div class="admin-detail-wide">
                            <dt><?= e(__('club.area.notes')) ?></dt>
                            <dd><?= nl2br(e($athlete->notes ?? __('admin.common.not_available'))) ?></dd>
                        </div>
                    </dl>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?= $pagination['links'] ?>
</section>
