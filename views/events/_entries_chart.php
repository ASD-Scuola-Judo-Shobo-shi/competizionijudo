<?php

/** @var array<string, mixed> $dimension */
$chartKey = (string) $dimension['key'];
$chartTitle = (string) $dimension['title'];
$segments = is_array($dimension['segments']) ? $dimension['segments'] : [];
$patternIds = [];
foreach ($segments as $index => $segment) {
    $colors = is_array($segment['colors'] ?? null) ? $segment['colors'] : [];
    if (count($colors) > 1) {
        $patternIds[$index] = 'pie-pattern-' . $chartKey . '-' . (string) $index;
    }
}
$startAngle = 0.0;
?>

<section class="entries-chart-card">
    <h5 class="entries-chart-card__title"><?= e($chartTitle) ?></h5>
    <div class="entries-chart-card__visual">
        <svg class="entries-chart__pie" viewBox="0 0 100 100" role="img" aria-label="<?= e($chartTitle) ?>">
            <title><?= e($chartTitle) ?></title>
            <?php if ($patternIds !== []) : ?>
                <defs>
                    <?php foreach ($patternIds as $index => $patternId) : ?>
                        <?php $colors = $segments[$index]['colors']; ?>
                        <pattern id="<?= e($patternId) ?>" width="8" height="8" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
                            <rect width="8" height="8" fill="<?= e((string) $colors[0]) ?>" />
                            <rect width="4" height="8" fill="<?= e((string) $colors[1]) ?>" />
                        </pattern>
                    <?php endforeach; ?>
                </defs>
            <?php endif; ?>

            <?php foreach ($segments as $index => $segment) : ?>
                <?php
                $percentage = (float) $segment['percentage'];
                $colors = $segment['colors'];
                $fill = isset($patternIds[$index])
                    ? 'url(#' . $patternIds[$index] . ')'
                    : (string) $colors[0];
                ?>
                <?php if ($percentage >= 99.999) : ?>
                    <circle cx="50" cy="50" r="40" fill="<?= e($fill) ?>" />
                <?php else : ?>
                    <?php
                    $endAngle = $startAngle + ($percentage / 100) * 360;
                    $largeArc = ($endAngle - $startAngle) > 180 ? 1 : 0;
                    $startX = 50 + 40 * cos(deg2rad($startAngle - 90));
                    $startY = 50 + 40 * sin(deg2rad($startAngle - 90));
                    $endX = 50 + 40 * cos(deg2rad($endAngle - 90));
                    $endY = 50 + 40 * sin(deg2rad($endAngle - 90));
                    $path = sprintf(
                        'M50,50 L%.2f,%.2f A40,40 0 %d,1 %.2f,%.2f Z',
                        $startX,
                        $startY,
                        $largeArc,
                        $endX,
                        $endY
                    );
                    $startAngle = $endAngle;
                    ?>
                    <path d="<?= e($path) ?>" fill="<?= e($fill) ?>" />
                <?php endif; ?>
            <?php endforeach; ?>
        </svg>
    </div>

    <ul class="entries-chart__legend" aria-label="<?= e($chartTitle) ?>">
        <?php foreach ($segments as $index => $segment) : ?>
            <?php
            $colors = $segment['colors'];
            $swatchId = 'pie-swatch-' . $chartKey . '-' . (string) $index;
            ?>
            <li>
                <span
                    class="entries-chart__swatch<?= count($colors) > 1 ? ' entries-chart__swatch--split' : '' ?><?= $segment['border'] ? ' entries-chart__swatch--bordered' : '' ?>"
                    aria-hidden="true"
                >
                    <svg viewBox="0 0 13 13">
                        <?php if (count($colors) > 1) : ?>
                            <defs>
                                <linearGradient id="<?= e($swatchId) ?>" x1="0" y1="0" x2="1" y2="1">
                                    <stop offset="0.5" stop-color="<?= e((string) $colors[0]) ?>" />
                                    <stop offset="0.5" stop-color="<?= e((string) $colors[1]) ?>" />
                                </linearGradient>
                            </defs>
                            <rect width="13" height="13" fill="url(#<?= e($swatchId) ?>)" />
                        <?php else : ?>
                            <rect width="13" height="13" fill="<?= e((string) $colors[0]) ?>" />
                        <?php endif; ?>
                    </svg>
                </span>
                <span>
                    <?= e((string) $segment['displayLabel']) ?>:
                    <strong><?= e((string) $segment['count']) ?></strong>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
