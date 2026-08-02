<?php
/** @var \App\Model\Athlete|null $athlete */
/** @var \App\Model\Belt|null $beltBadge */
/** @var string|null $beltBadgeFallback */
if (isset($beltBadgeFallback)) {
    $_belt = $beltBadge ?? null;
    $_beltFallbackLabel = $beltBadgeFallback;
} elseif (isset($athlete)) {
    $_belt = $athlete->beltEnum();
    $_beltFallbackLabel = $athlete->beltLabel(App\Localization::getLocale());
} else {
    $_belt = null;
    $_beltFallbackLabel = '';
}
$_beltComponents = $_belt?->components(App\Localization::getLocale()) ?? [[
    'label' => $_beltFallbackLabel,
    'color' => '#9ca3af',
    'textColor' => '#ffffff',
    'circle' => '',
]];
$_beltLabel = implode(' / ', array_column($_beltComponents, 'label'));
?>
<span class="belt-badge" title="<?= e($_beltLabel) ?>">
    <span class="belt-badge__visual" aria-hidden="true">
        <span class="belt-badge__band">
            <?php foreach ($_beltComponents as $_beltComponent) : ?>
                <span
                    class="belt-badge__segment"
                    style="background-color: <?= e($_beltComponent['color']) ?>"
                ></span>
            <?php endforeach; ?>
        </span>
        <span class="belt-badge__knot">
            <?php foreach ($_beltComponents as $_beltComponent) : ?>
                <span
                    class="belt-badge__segment"
                    style="background-color: <?= e($_beltComponent['color']) ?>"
                ></span>
            <?php endforeach; ?>
        </span>
    </span>
    <span class="belt-badge__label"><?= e($_beltLabel) ?></span>
</span>
<?php unset($beltBadge, $beltBadgeFallback); ?>
