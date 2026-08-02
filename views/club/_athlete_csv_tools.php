<?php
$csvFeedback = is_array($athleteCsvFeedback ?? null) ? $athleteCsvFeedback : null;
$returnView = ($csvReturnView ?? 'list') === 'add' ? 'add' : 'list';
$feedbackType = is_string($csvFeedback['type'] ?? null) ? $csvFeedback['type'] : '';
$importReport = is_array($csvFeedback['report'] ?? null) ? $csvFeedback['report'] : [];
?>
<div class="card csv-tools">
    <h3><?= e(__('club.area.csv.title')) ?></h3>
    <?php if ($csvFeedback !== null) : ?>
        <div
            class="notice<?= $feedbackType === 'success' ? ' success' : ($feedbackType === 'warning' ? ' warning' : '') ?>"
            role="status"
        >
            <?= e((string) ($csvFeedback['message'] ?? '')) ?>
        </div>
        <?php if ($importReport !== []) : ?>
            <div class="import-report">
                <h4><?= e(__('club.area.csv.report_title')) ?></h4>
                <div class="table-scroll" role="region" tabindex="0" aria-label="<?= e(__('club.area.csv.report_title')) ?>">
                    <table class="table-full">
                        <thead>
                            <tr>
                                <th scope="col"><?= e(__('club.area.csv.report_row')) ?></th>
                                <th scope="col"><?= e(__('club.area.csv.report_athlete')) ?></th>
                                <th scope="col"><?= e(__('club.area.csv.report_problem')) ?></th>
                                <th scope="col"><?= e(__('club.area.actions')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($importReport as $problem) : ?>
                                <?php if (is_array($problem)) : ?>
                                    <tr>
                                        <td><?= e((string) ($problem['row'] ?? '')) ?></td>
                                        <td><?= e((string) ($problem['identity'] ?? '')) ?></td>
                                        <td><?= e((string) ($problem['message'] ?? '')) ?></td>
                                        <td>
                                            <?php if ((int) ($problem['existing_athlete_id'] ?? 0) > 0) : ?>
                                                <a
                                                    class="btn btn-sm"
                                                    href="<?= e(base_url('/clubs/area?view=add&edit=' . (string) $problem['existing_athlete_id'])) ?>"
                                                ><?= e(__('club.area.edit')) ?></a>
                                            <?php else : ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ((int) ($csvFeedback['omitted'] ?? 0) > 0) : ?>
                    <p>
                        <?= e(__('club.area.csv.report_omitted', [
                            'count' => (string) (int) $csvFeedback['omitted'],
                        ])) ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <p><?= e(__('club.area.csv.description')) ?></p>
    <div class="notice info csv-federal-import-notice" role="note">
        <span class="csv-federal-import-notice__icon" aria-hidden="true">ℹ️</span>
        <div>
            <strong><?= e(__('club.area.csv.federal_import_title')) ?></strong>
            <p><?= e(__('club.area.csv.federal_import_help')) ?></p>
        </div>
    </div>
    <div class="csv-actions">
        <a class="btn" href="<?= e(base_url('/clubs/athletes-export')) ?>"><?= e(__('club.area.csv.export')) ?></a>
        <form
            method="post"
            action="<?= e(base_url('/clubs/athletes-import?')) ?>"
            enctype="multipart/form-data"
            class="csv-import-form"
        >
            <?= csrf_field() ?>
            <input type="hidden" name="return_view" value="<?= e($returnView) ?>">
            <label for="athletes_file_<?= e($returnView) ?>"><?= e(__('club.area.csv.file_label')) ?> <span class="required-marker" aria-hidden="true">*</span></label>
            <input
                id="athletes_file_<?= e($returnView) ?>"
                type="file"
                name="athletes_file"
                accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
                required
            >
            <button class="btn green" type="submit"><?= e(__('club.area.csv.import')) ?></button>
            <label class="checkbox-label import-merge-option">
                <input type="checkbox" name="merge_incomplete" value="1">
                <span><?= e(__('club.area.csv.merge_incomplete')) ?></span>
            </label>
        </form>
    </div>
    <p class="csv-help"><?= e(__('club.area.csv.columns_help')) ?></p>
    <p class="csv-help"><?= e(__('club.area.csv.update_help')) ?></p>
    <p class="csv-privacy"><?= e(__('club.area.csv.privacy_warning')) ?></p>
</div>
