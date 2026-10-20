<?php

/** @var array<string, mixed> $entry */
/** @var array<string, string|null> $current */
/** @var array<string, string|null> $editData */
/** @var string $fingerprint */
/** @var list<array<string, mixed>> $revisions */
/** @var list<array<string, mixed>> $history */
/** @var bool $administrator */
/** @var bool $canEdit */
/** @var string|null $error */
/** @var string|null $feedback */
$reviewPath = $administrator ? '/admin/events/entry-data' : '/events/entry-data';
$backPath = $administrator ? '/admin/events/details?event_id=' : '/events/entries?event=';
?>
<section class="card entry-data-review">
    <h2><?= e(__('entry_review.title')) ?> — <?= e((string) $entry['event_name']) ?></h2>
    <p><?= e(trim((string) ($current['last_name'] ?? '') . ' ' . (string) ($current['first_name'] ?? ''))) ?></p>
    <p><?= e(__('entry_review.help')) ?></p>
    <p><?= e(__('entry_review.cutoff', ['date' => (string) $entry['event_date']])) ?></p>
    <a class="btn" href="<?= e(base_url($backPath . (int) $entry['event_id'])) ?>"><?= e(__('entry_review.back')) ?></a>
    <?php if ($error !== null) :
        ?><p class="notice" role="alert"><?= e($error) ?></p><?php
    endif; ?>
    <?php if ($feedback !== null) :
        ?><p class="notice" role="status"><?= e($feedback) ?></p><?php
    endif; ?>

    <?php if ($administrator && $canEdit) : ?>
        <details>
            <summary><?= e(__('entry_review.edit')) ?></summary>
            <p><?= e(__('entry_review.edit_help')) ?></p>
            <form method="post" action="<?= e(base_url($reviewPath)) ?>" class="form-card">
                <?= csrf_field() ?>
                <input type="hidden" name="event_id" value="<?= e((string) $entry['event_id']) ?>">
                <input type="hidden" name="entry_id" value="<?= e((string) $entry['id']) ?>">
                <input type="hidden" name="fingerprint" value="<?= e($fingerprint) ?>">
                <input type="hidden" name="operation" value="edit">
                <?php foreach (array_slice(\App\Service\TournamentData::FIELDS, 0, 7) as $field) : ?>
                    <label for="entry-edit-<?= e($field) ?>"><?= e(__('admin.event_details.enrollment_fields.' . $field)) ?></label>
                    <?php if ($field === 'gender' || $field === 'belt') : ?>
                        <select id="entry-edit-<?= e($field) ?>" name="<?= e($field) ?>" required>
                            <option value="">—</option>
                            <?php $options = $field === 'gender' ? \App\Model\Gender::cases() : \App\Model\Belt::cases(); ?>
                            <?php foreach ($options as $option) : ?>
                                <option value="<?= e($option->value) ?>" <?= $editData[$field] === $option->value ? 'selected' : '' ?>><?= e($option->label()) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else : ?>
                        <input
                            id="entry-edit-<?= e($field) ?>" name="<?= e($field) ?>"
                            type="<?= $field === 'birth_date' ? 'date' : 'text' ?>"
                            value="<?= e((string) ($editData[$field] ?? '')) ?>"
                            <?= in_array($field, ['last_name', 'first_name', 'birth_date'], true) ? 'required' : '' ?>
                            <?= $field === 'weight_kg' ? 'inputmode="decimal"' : '' ?>
                        >
                    <?php endif; ?>
                <?php endforeach; ?>
                <label for="entry-edit-reason"><?= e(__('entry_review.reason')) ?></label>
                <textarea id="entry-edit-reason" name="reason" maxlength="1000" required></textarea>
                <button class="btn" type="submit"><?= e(__('entry_review.approve_edit')) ?></button>
            </form>
        </details>
    <?php elseif ($administrator) : ?>
        <p><?= e(__('entry_review.edit_ended')) ?></p>
    <?php endif; ?>

    <h3><?= e(__('entry_review.revisions')) ?></h3>
    <?php if ($revisions === []) :
        ?><p><?= e(__('entry_review.no_revisions')) ?></p><?php
    endif; ?>
    <?php foreach ($revisions as $revision) : ?>
        <details>
            <summary><?= e(__('entry_review.revision_at', ['date' => (string) $revision['recorded_at']])) ?></summary>
            <?php $before = $current;
            $proposed = $revision['data'];
            require __DIR__ . '/_comparison.php'; ?>
            <form method="post" action="<?= e(base_url($reviewPath)) ?>" class="form-card">
                <?= csrf_field() ?>
                <input type="hidden" name="event_id" value="<?= e((string) $entry['event_id']) ?>">
                <input type="hidden" name="entry_id" value="<?= e((string) $entry['id']) ?>">
                <input type="hidden" name="fingerprint" value="<?= e($fingerprint) ?>">
                <input type="hidden" name="revision_id" value="<?= e((string) $revision['id']) ?>">
                <input type="hidden" name="operation" value="<?= $administrator ? 'apply_revision' : 'request' ?>">
                <label for="revision-reason-<?= e((string) $revision['id']) ?>"><?= e(__('entry_review.reason')) ?></label>
                <textarea id="revision-reason-<?= e((string) $revision['id']) ?>" name="reason" maxlength="1000" required></textarea>
                <button class="btn" type="submit"><?= e(__($administrator ? 'entry_review.approve_revision' : 'entry_review.request')) ?></button>
            </form>
        </details>
    <?php endforeach; ?>

    <h3><?= e(__('entry_review.history')) ?></h3>
    <?php if ($history === []) :
        ?><p><?= e(__('entry_review.no_history')) ?></p><?php
    endif; ?>
    <?php foreach ($history as $review) : ?>
        <details <?= $review['status'] === 'pending' ? 'open' : '' ?>>
            <summary><?= e(__('entry_review.status.' . $review['status'])) ?> — <?= e((string) $review['requested_at']) ?> UTC</summary>
            <p><?= e((string) $review['reason']) ?></p>
            <p><?= e(__('entry_review.revision_at', ['date' => (string) $review['source_recorded_at']])) ?></p>
            <?php $before = $review['before'];
            $proposed = $review['proposed'];
            require __DIR__ . '/_comparison.php'; ?>
            <?php if ($review['decided_at'] !== null) : ?>
                <p><?= e(__('entry_review.decision', ['date' => (string) $review['decided_at']])) ?> — <?= e((string) $review['decision_reason']) ?></p>
            <?php endif; ?>
            <?php if ($administrator && $review['status'] === 'pending') : ?>
                <form method="post" action="<?= e(base_url($reviewPath)) ?>" class="form-card">
                    <?= csrf_field() ?>
                    <input type="hidden" name="event_id" value="<?= e((string) $entry['event_id']) ?>">
                    <input type="hidden" name="entry_id" value="<?= e((string) $entry['id']) ?>">
                    <input type="hidden" name="review_id" value="<?= e((string) $review['id']) ?>">
                    <label for="decision-reason-<?= e((string) $review['id']) ?>"><?= e(__('entry_review.decision_reason')) ?></label>
                    <textarea id="decision-reason-<?= e((string) $review['id']) ?>" name="reason" maxlength="1000" required></textarea>
                    <div class="form-actions">
                        <button class="btn" type="submit" name="operation" value="approve"><?= e(__('entry_review.approve')) ?></button>
                        <button class="btn" type="submit" name="operation" value="reject"><?= e(__('entry_review.reject')) ?></button>
                    </div>
                </form>
            <?php endif; ?>
        </details>
    <?php endforeach; ?>
</section>
