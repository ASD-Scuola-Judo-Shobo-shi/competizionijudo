<?php

/** @var array<string, mixed> $dimension */
/** @var int $clubId */
/** @var int $clubTotal */
$counts = is_array($dimension['clubCounts'][$clubId] ?? null)
    ? $dimension['clubCounts'][$clubId]
    : [];
$offset = 0.0;
?>

<div class="entries-breakdown" aria-label="<?= e((string) $dimension['title']) ?>">
    <?php foreach ($dimension['segments'] as $segment) : ?>
        <?php
        $count = (int) ($counts[$segment['label']] ?? 0);
        if ($count <= 0) {
            continue;
        }
        $width = ($count / $clubTotal) * 100;
        $colors = $segment['colors'];
        $style = sprintf(
            '--entry-segment-start: %.2f%%; --entry-segment-width: %.2f%%; --entry-segment-color: %s;',
            $offset,
            $width,
            $colors[0]
        );
        if (count($colors) > 1) {
            $style .= ' --entry-segment-color-alt: ' . $colors[1] . ';';
        }
        $offset += $width;
        ?>
        <span
            class="entries-breakdown__segment<?= count($colors) > 1 ? ' entries-breakdown__segment--split' : '' ?><?= $segment['border'] ? ' entries-breakdown__segment--bordered' : '' ?>"
            style="<?= e($style) ?>"
            title="<?= e((string) $segment['displayLabel']) ?>: <?= e((string) $count) ?>"
        ></span>
    <?php endforeach; ?>
</div>
