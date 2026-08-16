<?php

/** @var string $tableFilterId */
/** @var string $tableFilterLabel */
/**
 * @var list<array{
 *     name:string,
 *     label:string,
 *     value:string,
 *     inputmode?:string,
 *     options?:array<string, string>
 * }> $tableFilterFields
 */
/** @var array<string, string> $tableFilterState */
/** @var string $tableFilterResetUrl */
/** @var string|null $tableFilterDownloadUrl */
$tableFilterDownloadUrl ??= null;
$tableFilterNames = array_column($tableFilterFields, 'name');
?>

<form
    method="get"
    action="<?= e(base_url('/events/entries')) ?>"
    class="table-filter-form"
    aria-label="<?= e($tableFilterLabel) ?>"
>
    <?php foreach ($tableFilterState as $name => $value) : ?>
        <?php if (!in_array($name, $tableFilterNames, true) && $value !== '') : ?>
            <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
        <?php endif; ?>
    <?php endforeach; ?>

    <div class="table-filter-grid">
        <?php foreach ($tableFilterFields as $field) : ?>
            <?php
            $fieldId = $tableFilterId . '-' . str_replace('_', '-', $field['name']);
            $options = $field['options'] ?? null;
            ?>
            <div class="table-filter-field">
                <label for="<?= e($fieldId) ?>"><?= e($field['label']) ?></label>
                <?php if ($options !== null) : ?>
                    <select id="<?= e($fieldId) ?>" name="<?= e($field['name']) ?>">
                        <?php foreach ($options as $optionValue => $optionLabel) : ?>
                            <option
                                value="<?= e($optionValue) ?>"
                                <?= $field['value'] === $optionValue ? 'selected' : '' ?>
                            ><?= e($optionLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else : ?>
                    <input
                        id="<?= e($fieldId) ?>"
                        name="<?= e($field['name']) ?>"
                        type="search"
                        value="<?= e($field['value']) ?>"
                        maxlength="100"
                        <?= isset($field['inputmode']) ? 'inputmode="' . e($field['inputmode']) . '"' : '' ?>
                    >
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="table-filter-actions">
        <button class="btn" type="submit"><?= e(__('events.entries_apply_filters')) ?></button>
        <a class="btn gray" href="<?= e(base_url($tableFilterResetUrl)) ?>">
            <?= e(__('events.entries_clear_filters')) ?>
        </a>
        <?php if ($tableFilterDownloadUrl !== null) : ?>
            <a class="btn green" href="<?= e(base_url($tableFilterDownloadUrl)) ?>">
                <?= e(__('events.entries_download_filtered')) ?>
            </a>
        <?php endif; ?>
    </div>
</form>

<?php
unset(
    $tableFilterId,
    $tableFilterLabel,
    $tableFilterFields,
    $tableFilterState,
    $tableFilterResetUrl,
    $tableFilterDownloadUrl,
    $tableFilterNames,
    $name,
    $value,
    $field,
    $fieldId,
    $options,
    $optionValue,
    $optionLabel
);
?>
