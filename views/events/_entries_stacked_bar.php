<?php

/** @var array<string, mixed> $dimension */
/** @var int $clubId */
/** @var int $clubTotal */
$counts = is_array($dimension['clubCounts'][$clubId] ?? null)
    ? $dimension['clubCounts'][$clubId]
    : [];
$offset = 0.0;
$bars = [];
foreach ($dimension['segments'] as $index => $segment) {
    $count = (int) ($counts[$segment['label']] ?? 0);
    if ($count <= 0) {
        continue;
    }
    $width = ($count / $clubTotal) * 100;
    $colors = $segment['colors'];
    $bars[] = [
        'index' => $index,
        'start' => $offset,
        'width' => $width,
        'colors' => $colors,
        'border' => $segment['border'],
        'displayLabel' => $segment['displayLabel'],
        'count' => $count,
    ];
    $offset += $width;
}
?>

<div class="entries-breakdown" aria-label="<?= e((string) $dimension['title']) ?>">
    <svg class="entries-breakdown__bars" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
        <?php foreach ($bars as $bar) : ?>
            <?php if (count($bar['colors']) > 1) : ?>
                <defs>
                    <pattern
                        id="breakdown-stripe-<?= (int) $clubId ?>-<?= (int) $bar['index'] ?>"
                        width="10"
                        height="10"
                        patternUnits="userSpaceOnUse"
                        patternTransform="rotate(45)">
                        <rect width="10" height="10" fill="<?= e((string) $bar['colors'][0]) ?>" />
                        <rect width="5" height="10" fill="<?= e((string) $bar['colors'][1]) ?>" />
                    </pattern>
                </defs>
            <?php endif; ?>
            <rect
                x="<?= sprintf('%.2f', $bar['start']) ?>"
                y="0"
                width="<?= sprintf('%.2f', $bar['width']) ?>"
                height="100"
                fill="<?= count($bar['colors']) > 1
                    ? 'url(#breakdown-stripe-' . (int) $clubId . '-' . (int) $bar['index'] . ')'
                    : e((string) $bar['colors'][0]) ?>"
                <?= $bar['border'] ? 'stroke="#aeb8c5" stroke-width="1" vector-effect="non-scaling-stroke"' : '' ?>
            >
                <title><?= e((string) $bar['displayLabel']) ?>: <?= e((string) $bar['count']) ?></title>
            </rect>
        <?php endforeach; ?>
    </svg>
</div>
