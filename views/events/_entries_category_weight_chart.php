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
                        <?php foreach ($bar['segments'] as $segment) : ?>
                            <?php
                            $segmentStyle = sprintf(
                                '--entry-segment-width: %.2f%%; --entry-segment-color: %s;',
                                (float) $segment['percentage'],
                                (string) $segment['color']
                            );
                            ?>
                            <span
                                class="entries-category-weight-chart__segment"
                                style="<?= e($segmentStyle) ?>"
                                title="<?= e((string) $segment['label']) ?>: <?= e((string) $segment['count']) ?>"
                            ></span>
                        <?php endforeach; ?>
                    </div>
                    <ul class="entries-category-weight-chart__legend">
                        <?php foreach ($bar['segments'] as $segment) : ?>
                            <li>
                                <span
                                    class="entries-category-weight-chart__swatch"
                                    style="--entry-segment-color: <?= e((string) $segment['color']) ?>;"
                                    aria-hidden="true"
                                ></span>
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
