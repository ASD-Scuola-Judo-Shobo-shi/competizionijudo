<?php

declare(strict_types=1);

/** @var \App\Model\Event $event */
/** @var \App\Presentation\EntryDataComparison $report */
/** @var list<array<string, mixed>> $differences */
/** @var array{page:int, per_page:int, total:int, last_page:int, offset:int, links:string} $pagination */
/** @var string $fingerprint */
/** @var bool $canAlign */
/** @var string|null $feedback */
?>
<section class="card entry-data-review">
    <h2><?= e(__('entry_review.differences.title')) ?> — <?= e($event->name) ?></h2>
    <p><?= e(__('entry_review.differences.help')) ?></p>
    <p><?= e(__('entry_review.differences.frozen_help')) ?></p>
    <p class="field-help"><?= e(__('entry_review.differences.format_help')) ?></p>
    <p><?= e(__('entry_review.differences.count', [
        'total' => (string) $report->total,
        'export' => (string) $report->exportDifferenceCount,
        'profile' => (string) $report->profileDifferenceCount,
    ])) ?></p>
    <div class="form-actions">
        <a class="btn" href="<?= e(base_url('/admin/events/details?event_id=' . $event->id)) ?>"><?= e(__('entry_review.back')) ?></a>
        <a class="btn" href="<?= e(base_url('/admin/events/export?event_id=' . $event->id)) ?>"><?= e(__('admin.events.export')) ?></a>
    </div>
    <?php if ($feedback !== null) : ?>
        <p class="notice" role="status"><?= e($feedback) ?></p>
    <?php endif; ?>
    <?php if ($canAlign && $report->alignableCount > 0) : ?>
        <form method="post" action="<?= e(base_url('/admin/events/align-data')) ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="event_id" value="<?= e((string) $event->id) ?>">
            <input type="hidden" name="fingerprint" value="<?= e($fingerprint) ?>">
            <p><?= e(__('entry_review.differences.align_help', ['count' => (string) $report->alignableCount])) ?></p>
            <label for="alignment-reason"><?= e(__('entry_review.reason')) ?></label>
            <textarea id="alignment-reason" name="reason" maxlength="1000" required></textarea>
            <button class="btn" type="submit"><?= e(__('entry_review.differences.align')) ?></button>
        </form>
    <?php elseif (!$canAlign) : ?>
        <p><?= e(__('entry_review.edit_ended')) ?></p>
    <?php endif; ?>
    <?php if ($differences === []) : ?>
        <p><?= e(__('entry_review.differences.none')) ?></p>
    <?php endif; ?>
    <?php foreach ($differences as $entry) : ?>
        <details open>
            <summary><?= e((string) $entry['athlete']) ?> — <?= e((string) $entry['club']) ?></summary>
            <p><?= e(__('entry_review.differences.source.' . $entry['source'])) ?></p>
            <?php foreach (['website', 'export', 'profile'] as $source) : ?>
                <?php if ($entry[$source . 'Missing']) : ?>
                    <p class="notice"><?= e(__('entry_review.differences.missing.' . $source)) ?></p>
                <?php endif; ?>
            <?php endforeach; ?>
            <div class="table-scroll table-scroll--responsive" role="region" tabindex="0" aria-label="<?= e(__('entry_review.differences.title')) ?>">
                <table class="responsive-table event-info-table">
                    <thead>
                        <tr>
                            <th scope="col"><?= e(__('entry_review.field')) ?></th>
                            <?php foreach (['website', 'export', 'profile'] as $source) : ?>
                                <th scope="col"><?= e(__('entry_review.differences.' . $source)) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entry['fields'] as $field) : ?>
                            <tr>
                                <th scope="row"><?= e(__('admin.event_details.enrollment_fields.' . $field['field'])) ?></th>
                                <?php foreach (['website', 'export', 'profile'] as $source) : ?>
                                    <td data-label="<?= e(__('entry_review.differences.' . $source)) ?>"><?= e($field[$source] !== '' ? $field[$source] : '—') ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!$entry['profileMissing']) : ?>
                <a class="btn" href="<?= e(base_url('/admin/events/entry-data?event_id=' . $event->id . '&entry_id=' . $entry['entry_id'])) ?>"><?= e(__('entry_review.edit')) ?></a>
            <?php endif; ?>
        </details>
    <?php endforeach; ?>
    <?= $pagination['links'] ?>
</section>
