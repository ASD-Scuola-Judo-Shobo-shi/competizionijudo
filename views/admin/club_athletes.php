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
        <div
            class="table-scroll table-scroll--wide table-scroll--responsive admin-list-table-wrap"
            role="region"
            tabindex="0"
            aria-label="<?= e(__('admin.clubs.athletes_title', ['club' => $club->name])) ?>"
        >
            <table class="table-full responsive-table admin-list-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('club.area.table.athlete')) ?></th>
                        <th scope="col"><?= e(__('club.area.table.gender')) ?></th>
                        <th scope="col"><?= e(__('club.area.table.birth')) ?></th>
                        <th scope="col"><?= e(__('club.area.table.age_class')) ?></th>
                        <th scope="col"><?= e(__('club.area.table.weight')) ?></th>
                        <th scope="col"><?= e(__('club.area.table.belt')) ?></th>
                        <th scope="col"><?= e(__('club.area.table.weight_category')) ?></th>
                        <th scope="col"><?= e(__('club.area.table.membership_number')) ?></th>
                        <th scope="col"><?= e(__('club.area.table.notes')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($athletes as $athlete) : ?>
                        <?php
                        $category = $athlete->categoryForEventDate();
                        $ageClass = $athlete->ageClassLabel(App\Localization::getLocale());
                        $gender = $athlete->genderEnum();
                        $weight = $athlete->weight_kg !== null
                            ? rtrim(rtrim(number_format($athlete->weight_kg, 2, '.', ''), '0'), '.')
                            : null;
                        ?>
                        <tr>
                            <td class="admin-list-table__primary" data-label="<?= e(__('club.area.athlete')) ?>">
                                <?= e($athlete->last_name . ' ' . $athlete->first_name) ?>
                            </td>
                            <td data-label="<?= e(__('club.area.gender')) ?>">
                                <?php
                                $genderBadge = $gender;
                                $genderBadgeFallback = $athlete->gender;
                                require dirname(__DIR__) . '/components/gender_badge.php';
                                ?>
                            </td>
                            <td data-label="<?= e(__('club.area.birth')) ?>">
                                <time datetime="<?= e($athlete->birth_date) ?>"><?= e($athlete->birth_date) ?></time>
                            </td>
                            <td data-label="<?= e(__('club.area.age_class')) ?>">
                                <?= e($ageClass !== '' ? $ageClass : __('admin.common.not_available')) ?>
                            </td>
                            <td data-label="<?= e(__('club.area.weight')) ?>">
                                <?= e($weight !== null
                                    ? __('admin.clubs.weight_value', ['weight' => $weight])
                                    : __('events.no_weight')) ?>
                            </td>
                            <td data-label="<?= e(__('club.area.belt')) ?>">
                                <span class="admin-belt-value">
                                    <?php require dirname(__DIR__) . '/components/belt_badge.php'; ?>
                                </span>
                            </td>
                            <td data-label="<?= e(__('club.area.weight_category')) ?>">
                                <?= e($category['weight_category'] !== ''
                                    ? $category['weight_category']
                                    : __('admin.common.not_available')) ?>
                            </td>
                            <td data-label="<?= e(__('club.area.membership_number')) ?>">
                                <?php if ($athlete->membership_number !== null) : ?>
                                    <span class="admin-code-badge"><?= e($athlete->membership_number) ?></span>
                                <?php else : ?>
                                    <?= e(__('admin.common.not_available')) ?>
                                <?php endif; ?>
                            </td>
                            <td data-label="<?= e(__('club.area.notes')) ?>">
                                <?= nl2br(e($athlete->notes ?? __('admin.common.not_available'))) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?= $pagination['links'] ?>
</section>
