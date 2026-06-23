<?php /** @var list<\App\Model\Club> $clubs */ ?>
<?php /** @var array{page: int, per_page: int, total: int, last_page: int, offset: int, links: string} $pagination */ ?>
<div class="card">
    <h2><?= e(__('admin.clubs.title')) ?> <span class="count-badge"><?= e((string) ($pagination['total'] ?? 0)) ?></span></h2>
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
                    <td>
                        <a class="btn btn-sm green" href="/admin_edit_club.php?id=<?= (int) $club->id ?>"><?= e(__('admin.clubs.edit')) ?></a>
                        <a class="btn btn-sm red" href="/admin_manage_clubs.php?delete=<?= (int) $club->id ?>" onclick="return confirm('<?= e(__('admin.clubs.confirm_delete')) ?>')"><?= e(__('admin.clubs.delete')) ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($clubs)) : ?>
                <tr><td colspan="6"><?= e(__('admin.clubs.empty')) ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?= $pagination['links'] ?? '' ?>
</div>
