<?php /** @var list<\App\Model\Club> $clubs */ ?>
<?php /** @var array{page: int, per_page: int, total: int, last_page: int, offset: int, links: string} $pagination */ ?>
<div class="card">
    <h2><?= e(__('admin.clubs.title')) ?> <span class="count-badge"><?= e((string) $pagination['total']) ?></span></h2>
        <table class="table-full">
        <thead>
            <tr>
                <th><?= e(__('admin.clubs.name')) ?></th>
                <th><?= e(__('admin.clubs.federal_code')) ?></th>
                <th><?= e(__('admin.clubs.email')) ?></th>
                <th><?= e(__('admin.clubs.phone')) ?></th>
                <th><?= e(__('club.register.contact_name')) ?></th>
                <th><?= e(__('admin.clubs.actions')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clubs as $club) : ?>
                <tr>
                    <td><?= e($club->name) ?></td>
                    <td><?= e($club->federal_code) ?></td>
                    <td><?= e($club->email) ?></td>
                    <td><?= e($club->phone) ?></td>
                    <td><?= e($club->contact_first_name . ' ' . $club->contact_last_name) ?></td>
                    <td class="table-actions-cell">
                        <div class="table-actions">
                            <a class="btn btn-sm green table-action-icon" href="<?= e(base_url('/admin/clubs/edit?id=' . (int) $club->id)) ?>" aria-label="<?= e(__('admin.clubs.edit')) ?>" title="<?= e(__('admin.clubs.edit')) ?>">✏️</a>
                            <form method="post" action="<?= e(base_url('/admin/clubs/delete?')) ?>" onsubmit="return confirm('<?= e(__('admin.clubs.confirm_delete')) ?>')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="club_id" value="<?= (int) $club->id ?>">
                                <button class="btn btn-sm red table-action-icon" type="submit" aria-label="<?= e(__('admin.clubs.delete')) ?>" title="<?= e(__('admin.clubs.delete')) ?>">❌</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($clubs)) : ?>
                <tr><td colspan="6"><?= e(__('admin.clubs.empty')) ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?= $pagination['links'] ?>
</div>
