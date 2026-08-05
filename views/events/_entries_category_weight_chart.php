<?php

/** @var \App\Presentation\EventEntriesViewModel $entryReport */
$chartTitle = __('events.entries_class_weight_breakdown');
?>

<section class="entries-category-weight-chart" aria-labelledby="entries-category-weight-chart-title">
    <h5 id="entries-category-weight-chart-title" class="entries-category-weight-chart__title">
        <?= e($chartTitle) ?>
    </h5>
    <ul class="entries-category-weight-chart__rows">
        <?php foreach ($entryReport->categoryWeightBars as $bar) : ?>
            <li class="entries-category-weight-chart__row">
                <p class="entries-category-weight-chart__heading">
                    <strong><?= e((string) $bar['category']) ?></strong>
                    <span><?= e(__('events.entries_category_total', ['count' => (string) $bar['total']])) ?></span>
                </p>
                <div class="entries-category-weight-chart__content">
                    <div class="entries-category-weight-chart__track" aria-label="<?= e((string) $bar['category']) ?>">
                        <svg class="entries-category-weight-chart__bars" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                            <?php
                            $segmentOffset = 0.0;
                            foreach ($bar['segments'] as $segment) : ?>
                                <rect
                                    x="<?= sprintf('%.2f', $segmentOffset) ?>"
                                    y="0"
                                    width="<?= sprintf('%.2f', (float) $segment['percentage']) ?>"
                                    height="100"
                                    fill="<?= e((string) $segment['color']) ?>"
                                >
                                    <title><?= e((string) $segment['label']) ?>: <?= e((string) $segment['count']) ?></title>
                                </rect>
                                <?php $segmentOffset += (float) $segment['percentage']; ?>
                            <?php endforeach; ?>
                        </svg>
                    </div>
                    <ul class="entries-category-weight-chart__legend">
                        <?php foreach ($bar['segments'] as $segment) : ?>
                            <li>
                                <span class="entries-category-weight-chart__swatch" aria-hidden="true">
                                    <svg viewBox="0 0 11 11">
                                        <rect width="11" height="11" fill="<?= e((string) $segment['color']) ?>" />
                                    </svg>
                                </span>
                                <span>
                                    <?= e((string) $segment['label']) ?>:
                                    <strong><?= e((string) $segment['count']) ?></strong>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
