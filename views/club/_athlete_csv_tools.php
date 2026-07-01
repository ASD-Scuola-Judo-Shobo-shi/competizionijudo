<?php
$csvFeedback = is_array($athleteCsvFeedback ?? null) ? $athleteCsvFeedback : null;
$returnView = ($csvReturnView ?? 'list') === 'add' ? 'add' : 'list';
?>
<div class="card csv-tools">
    <h3><?= e(__('club.area.csv.title')) ?></h3>
    <?php if ($csvFeedback !== null) : ?>
        <div
            class="notice<?= ($csvFeedback['type'] ?? '') === 'success' ? ' success' : '' ?>"
            role="status"
        >
            <?= e((string) ($csvFeedback['message'] ?? '')) ?>
        </div>
    <?php endif; ?>
    <p><?= e(__('club.area.csv.description')) ?></p>
    <div class="csv-actions">
        <a class="btn" href="/club_athletes_export.csv"><?= e(__('club.area.csv.export')) ?></a>
        <form
            method="post"
            action="/club_athletes_import.php"
            enctype="multipart/form-data"
            class="csv-import-form"
        >
            <?= csrf_field() ?>
            <input type="hidden" name="return_view" value="<?= e($returnView) ?>">
            <label for="athletes_csv_<?= e($returnView) ?>"><?= e(__('club.area.csv.file_label')) ?></label>
            <input
                id="athletes_csv_<?= e($returnView) ?>"
                type="file"
                name="athletes_csv"
                accept=".csv,text/csv"
                required
            >
            <button class="btn green" type="submit"><?= e(__('club.area.csv.import')) ?></button>
        </form>
    </div>
    <p class="csv-help"><?= e(__('club.area.csv.columns_help')) ?></p>
    <p class="csv-help"><?= e(__('club.area.csv.update_help')) ?></p>
    <p class="csv-privacy"><?= e(__('club.area.csv.privacy_warning')) ?></p>
</div>
