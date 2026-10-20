<?php

/** @var array<string, mixed> $before */
/** @var array<string, mixed> $proposed */
?>
<div class="table-scroll table-scroll--responsive" role="region" tabindex="0" aria-label="<?= e(__('entry_review.comparison')) ?>">
    <table class="responsive-table event-info-table">
        <thead>
            <tr>
                <th scope="col"><?= e(__('entry_review.field')) ?></th>
                <th scope="col"><?= e(__('entry_review.before')) ?></th>
                <th scope="col"><?= e(__('entry_review.proposed')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (\App\Service\TournamentData::FIELDS as $field) : ?>
                <tr>
                    <th scope="row"><?= e(__('admin.event_details.enrollment_fields.' . $field)) ?></th>
                    <td data-label="<?= e(__('entry_review.before')) ?>"><?= e((string) ($before[$field] ?? '—')) ?></td>
                    <td data-label="<?= e(__('entry_review.proposed')) ?>">
                        <?php if (($before[$field] ?? null) !== ($proposed[$field] ?? null)) : ?>
                            <strong><?= e((string) ($proposed[$field] ?? '—')) ?></strong>
                            <span class="field-help"><?= e(__('entry_review.changed')) ?></span>
                        <?php else : ?>
                            <?= e((string) ($proposed[$field] ?? '—')) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
