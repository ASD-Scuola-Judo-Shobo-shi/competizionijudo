<?php

/** @var \App\Model\Gender|null $genderBadge */
/** @var string|null $genderBadgeFallback */
$_genderBadge = $genderBadge ?? null;
$_genderBadgeFallback = $genderBadgeFallback ?? '';
$_genderBadgeIcon = $_genderBadge?->icon() ?? $_genderBadgeFallback;
$_genderBadgeLabel = $_genderBadge?->label() ?? $_genderBadgeFallback;
$_genderBadgeModifier = match ($_genderBadge) {
    \App\Model\Gender::Male => ' gender-badge--male',
    \App\Model\Gender::Female => ' gender-badge--female',
    default => '',
};
?>
<span
    class="table-density-value gender-badge<?= $_genderBadgeModifier ?>"
    role="img"
    aria-label="<?= e($_genderBadgeLabel) ?>"
    title="<?= e($_genderBadgeLabel) ?>"
><?= e($_genderBadgeIcon) ?></span>
<span class="card-density-value">
    <span class="gender-badge<?= $_genderBadgeModifier ?>" aria-hidden="true"><?= e($_genderBadgeIcon) ?></span>
    <?= e($_genderBadgeLabel) ?>
</span>
<?php unset($genderBadge, $genderBadgeFallback); ?>
