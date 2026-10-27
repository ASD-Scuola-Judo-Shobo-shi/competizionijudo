<?php

declare(strict_types=1);

/** @var string $type */
/** @var list<array{id:int, label:string, club:string, changes:array<string, array{before:string, after:string}>}> $records */
/** @var array{links:string} $pagination */
/** @var string $previewToken */
/** @var string $error */
/** @var int|null $updated */
?>
<div class="card">
    <h2><?= e(__('normalization.title')) ?></h2>
    <p><?= e(__('normalization.help')) ?></p>
    <?php if ($error !== '') : ?>
        <p class="notice" role="alert"><?= e($error) ?></p>
    <?php endif; ?>
    <?php if ($updated !== null) : ?>
        <p class="notice" role="status"><?= e(__('normalization.applied', ['count' => (string) $updated])) ?></p>
    <?php endif; ?>
    <form method="get" class="form-card">
        <label for="normalization-type"><?= e(__('normalization.data')) ?></label>
        <select id="normalization-type" name="type">
            <?php foreach (['clubs', 'athletes'] as $option) : ?>
                <option value="<?= e($option) ?>" <?= $option === $type ? 'selected' : '' ?>><?= e(__('normalization.' . $option)) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit"><?= e(__('normalization.preview')) ?></button>
    </form>
    <p><?= e(__('normalization.page_help')) ?></p>
    <?php if ($records !== []) : ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="preview_token" value="<?= e($previewToken) ?>">
            <div class="table-scroll" role="region" tabindex="0" aria-label="<?= e(__('normalization.title')) ?>">
                <table>
                    <thead><tr>
                        <th scope="col" data-sortable="false"><?= e(__('normalization.select_label')) ?></th>
                        <th scope="col"><?= e(__('normalization.record')) ?></th>
                        <th scope="col"><?= e(__('normalization.clubs')) ?></th>
                        <th scope="col"><?= e(__('normalization.changes')) ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($records as $record) : ?>
                        <tr>
                            <td><input type="checkbox" name="selected[]" value="<?= $record['id'] ?>" aria-label="<?= e(__('normalization.select_record', ['name' => $record['label']])) ?>"></td>
                            <td><?= e($record['label']) ?> (#<?= $record['id'] ?>)</td>
                            <td><?= e($record['club']) ?></td>
                            <td>
                                <dl>
                                    <?php foreach ($record['changes'] as $field => $change) : ?>
                                        <dt><?= e(__('normalization.fields.' . $field)) ?></dt>
                                        <dd><del><?= e($change['before']) ?></del> → <ins><?= e($change['after']) ?></ins></dd>
                                    <?php endforeach; ?>
                                </dl>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button class="btn green" type="submit"><?= e(__('normalization.apply')) ?></button>
        </form>
    <?php else : ?>
        <p><?= e(__('normalization.empty')) ?></p>
    <?php endif; ?>
    <?= $pagination['links'] ?>
</div>
