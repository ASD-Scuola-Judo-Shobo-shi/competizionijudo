<?php /** @var list<array{id: int, federal_code: string, name: string}> $clubs */ ?>
<?php /** @var string $selectedClubId */ ?>
<?php /** @var \App\Service\AthleteDuplicateCleanupResult|null $result */ ?>
<?php /** @var list<string> $errors */ ?>
<?php /** @var string $confirmationPhrase */ ?>
<?php
$clubLabels = [];
foreach ($clubs as $clubOption) {
    $clubLabels[$clubOption['id']] = $clubOption['name'] . ' (#' . $clubOption['id'] . ')';
}
?>
<section class="card athlete-cleanup-page">
    <header class="admin-list-heading">
        <div>
            <p class="admin-list-eyebrow"><?= e(__('admin.athlete_cleanup.eyebrow')) ?></p>
            <h2><?= e(__('admin.athlete_cleanup.title')) ?></h2>
        </div>
    </header>

    <div class="notice warning" role="note">
        <strong><?= e(__('admin.athlete_cleanup.temporary_title')) ?></strong>
        <p><?= e(__('admin.athlete_cleanup.temporary_help')) ?></p>
    </div>

    <?php if ($errors !== []) : ?>
        <div class="notice-error" role="alert">
            <ul>
                <?php foreach ($errors as $error) : ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(base_url('/admin/maintenance/athlete-duplicates')) ?>" class="athlete-cleanup-form" autocomplete="off">
        <?= csrf_field() ?>
        <div>
            <label for="athlete-cleanup-club"><?= e(__('admin.athlete_cleanup.club')) ?></label>
            <select id="athlete-cleanup-club" name="club_id">
                <option value=""><?= e(__('admin.athlete_cleanup.all_clubs')) ?></option>
                <?php foreach ($clubs as $club) : ?>
                    <option
                        value="<?= (int) $club['id'] ?>"
                        <?= $selectedClubId === (string) $club['id'] ? 'selected' : '' ?>
                    ><?= e($club['name'] . ' — ' . $club['federal_code']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="field-help"><?= e(__('admin.athlete_cleanup.club_help')) ?></p>
        </div>

        <div>
            <label for="athlete-cleanup-confirmation"><?= e(__('admin.athlete_cleanup.confirmation_label')) ?></label>
            <input
                id="athlete-cleanup-confirmation"
                name="confirmation"
                spellcheck="false"
                autocomplete="off"
                placeholder="<?= e($confirmationPhrase) ?>"
            >
            <p class="field-help">
                <?= e(__('admin.athlete_cleanup.confirmation_help', ['phrase' => $confirmationPhrase])) ?>
            </p>
        </div>

        <label class="consent-field">
            <input type="checkbox" name="backup_confirmed" value="1">
            <span><?= e(__('admin.athlete_cleanup.backup_confirmed')) ?></span>
        </label>

        <div class="athlete-cleanup-actions">
            <button class="btn" type="submit" name="operation" value="preview">
                <?= e(__('admin.athlete_cleanup.preview')) ?>
            </button>
            <button class="btn red" type="submit" name="operation" value="apply">
                <?= e(__('admin.athlete_cleanup.apply')) ?>
            </button>
        </div>
    </form>
</section>

<?php if ($result !== null) : ?>
    <section class="card athlete-cleanup-report" aria-labelledby="athlete-cleanup-report-title">
        <header class="admin-list-heading">
            <div>
                <p class="admin-list-eyebrow">
                    <?= e($result->applied
                        ? __('admin.athlete_cleanup.mode_apply')
                        : __('admin.athlete_cleanup.mode_preview')) ?>
                </p>
                <h2 id="athlete-cleanup-report-title"><?= e(__('admin.athlete_cleanup.report_title')) ?></h2>
            </div>
        </header>

        <dl class="athlete-cleanup-summary">
            <div>
                <dt><?= e(__('admin.athlete_cleanup.safe_groups')) ?></dt>
                <dd><?= count($result->groups) ?></dd>
            </div>
            <div>
                <dt><?= e(__('admin.athlete_cleanup.duplicate_athletes')) ?></dt>
                <dd><?= $result->duplicateAthletes() ?></dd>
            </div>
            <div>
                <dt><?= e(__('admin.athlete_cleanup.entry_moves')) ?></dt>
                <dd><?= $result->entryMoves() ?></dd>
            </div>
            <div>
                <dt><?= e(__('admin.athlete_cleanup.blocked_groups')) ?></dt>
                <dd><?= count($result->blockedGroups) ?></dd>
            </div>
            <div>
                <dt><?= e(__('admin.athlete_cleanup.manual_groups')) ?></dt>
                <dd><?= count($result->nameCollisions) ?></dd>
            </div>
        </dl>

        <?php if ($result->applied) : ?>
            <div class="notice<?= $result->blockedGroups === [] ? ' success' : ' warning' ?>" role="status">
                <?= e($result->blockedGroups === []
                    ? __('admin.athlete_cleanup.applied_success')
                    : __('admin.athlete_cleanup.applied_with_blocked')) ?>
            </div>
        <?php else : ?>
            <div class="notice warning" role="status">
                <?= e(__('admin.athlete_cleanup.preview_notice')) ?>
            </div>
        <?php endif; ?>

        <?php if ($result->groups !== []) : ?>
            <h3><?= e(__('admin.athlete_cleanup.safe_heading')) ?></h3>
            <div class="table-scroll table-scroll--wide table-scroll--responsive" role="region" tabindex="0" aria-label="<?= e(__('admin.athlete_cleanup.safe_heading')) ?>">
                <table class="table-full responsive-table">
                    <thead>
                        <tr>
                            <th scope="col"><?= e(__('admin.athlete_cleanup.table.club')) ?></th>
                            <th scope="col"><?= e(__('admin.athlete_cleanup.table.athlete')) ?></th>
                            <th scope="col"><?= e(__('admin.athlete_cleanup.table.birth_date')) ?></th>
                            <th scope="col"><?= e(__('admin.athlete_cleanup.table.survivor')) ?></th>
                            <th scope="col"><?= e(__('admin.athlete_cleanup.table.duplicates')) ?></th>
                            <th scope="col"><?= e(__('admin.athlete_cleanup.table.entries')) ?></th>
                            <th scope="col"><?= e(__('admin.athlete_cleanup.table.reconciliation')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result->groups as $group) : ?>
                            <tr>
                                <td data-label="<?= e(__('admin.athlete_cleanup.table.club')) ?>"><?= e($clubLabels[$group['club_id']] ?? '#' . $group['club_id']) ?></td>
                                <td data-label="<?= e(__('admin.athlete_cleanup.table.athlete')) ?>"><?= e($group['last_name'] . ' ' . $group['first_name']) ?></td>
                                <td data-label="<?= e(__('admin.athlete_cleanup.table.birth_date')) ?>"><?= e($group['birth_date']) ?></td>
                                <td data-label="<?= e(__('admin.athlete_cleanup.table.survivor')) ?>">#<?= (int) $group['survivor_id'] ?></td>
                                <td data-label="<?= e(__('admin.athlete_cleanup.table.duplicates')) ?>">#<?= e(implode(', #', $group['duplicate_ids'])) ?></td>
                                <td data-label="<?= e(__('admin.athlete_cleanup.table.entries')) ?>"><?= (int) $group['entry_moves'] ?></td>
                                <td data-label="<?= e(__('admin.athlete_cleanup.table.reconciliation')) ?>">
                                    <ul class="athlete-cleanup-resolution-list">
                                        <?php foreach ($group['resolutions'] as $athleteId => $resolutions) : ?>
                                            <li>
                                                <strong>#<?= (int) $athleteId ?>:</strong>
                                                <?php if ($resolutions === []) : ?>
                                                    <?= e(__('admin.athlete_cleanup.exact_duplicate')) ?>
                                                <?php else : ?>
                                                    <?php $details = []; ?>
                                                    <?php foreach ($resolutions as $field => $resolution) : ?>
                                                        <?php
                                                        $details[] = __('club.area.csv.headers.' . $field)
                                                            . ': '
                                                            . __('club.area.csv.reconciliation.' . $resolution);
                                                        ?>
                                                    <?php endforeach; ?>
                                                    <?= e(implode('; ', $details)) ?>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($result->blockedGroups === [] && $result->nameCollisions === []) : ?>
            <p class="notice success"><?= e(__('admin.athlete_cleanup.no_duplicates')) ?></p>
        <?php endif; ?>

        <?php if ($result->blockedGroups !== []) : ?>
            <h3><?= e(__('admin.athlete_cleanup.blocked_heading')) ?></h3>
            <p><?= e(__('admin.athlete_cleanup.blocked_help')) ?></p>
            <div class="table-scroll table-scroll--wide table-scroll--responsive" role="region" tabindex="0" aria-label="<?= e(__('admin.athlete_cleanup.blocked_heading')) ?>">
                <table class="table-full responsive-table">
                    <thead>
                        <tr>
                            <th scope="col"><?= e(__('admin.athlete_cleanup.table.club')) ?></th>
                            <th scope="col"><?= e(__('admin.athlete_cleanup.table.athlete')) ?></th>
                            <th scope="col"><?= e(__('admin.athlete_cleanup.table.birth_date')) ?></th>
                            <th scope="col"><?= e(__('admin.athlete_cleanup.table.ids')) ?></th>
                            <th scope="col"><?= e(__('admin.athlete_cleanup.table.reason')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result->blockedGroups as $group) : ?>
                            <tr>
                                <td data-label="<?= e(__('admin.athlete_cleanup.table.club')) ?>"><?= e($clubLabels[$group['club_id']] ?? '#' . $group['club_id']) ?></td>
                                <td data-label="<?= e(__('admin.athlete_cleanup.table.athlete')) ?>"><?= e($group['last_name'] . ' ' . $group['first_name']) ?></td>
                                <td data-label="<?= e(__('admin.athlete_cleanup.table.birth_date')) ?>"><?= e($group['birth_date']) ?></td>
                                <td data-label="<?= e(__('admin.athlete_cleanup.table.ids')) ?>">#<?= e(implode(', #', $group['athlete_ids'])) ?></td>
                                <td data-label="<?= e(__('admin.athlete_cleanup.table.reason')) ?>">
                                    <?php if ($group['reason'] === 'overlapping_entries') : ?>
                                        <?= e(__('admin.athlete_cleanup.overlapping_entries', [
                                            'events' => implode(', ', $group['overlapping_event_ids']),
                                        ])) ?>
                                    <?php else : ?>
                                        <?= e(__('admin.athlete_cleanup.entry_club_mismatch')) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($result->nameCollisions !== []) : ?>
            <h3><?= e(__('admin.athlete_cleanup.manual_heading')) ?></h3>
            <p><?= e(__('admin.athlete_cleanup.manual_help')) ?></p>
            <div class="table-scroll table-scroll--wide table-scroll--responsive" role="region" tabindex="0" aria-label="<?= e(__('admin.athlete_cleanup.manual_heading')) ?>">
                <table class="table-full responsive-table">
                    <thead>
                        <tr>
                            <th scope="col"><?= e(__('admin.athlete_cleanup.table.club')) ?></th>
                            <th scope="col"><?= e(__('admin.athlete_cleanup.table.athlete')) ?></th>
                            <th scope="col"><?= e(__('admin.athlete_cleanup.table.ids')) ?></th>
                            <th scope="col"><?= e(__('admin.athlete_cleanup.table.birth_dates')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result->nameCollisions as $collision) : ?>
                            <tr>
                                <td data-label="<?= e(__('admin.athlete_cleanup.table.club')) ?>"><?= e($clubLabels[$collision['club_id']] ?? '#' . $collision['club_id']) ?></td>
                                <td data-label="<?= e(__('admin.athlete_cleanup.table.athlete')) ?>"><?= e($collision['last_name'] . ' ' . $collision['first_name']) ?></td>
                                <td data-label="<?= e(__('admin.athlete_cleanup.table.ids')) ?>">#<?= e(implode(', #', $collision['athlete_ids'])) ?></td>
                                <td data-label="<?= e(__('admin.athlete_cleanup.table.birth_dates')) ?>"><?= e(implode(', ', $collision['birth_dates'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
