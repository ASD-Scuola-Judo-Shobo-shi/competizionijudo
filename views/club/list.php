<?php /** @var array{page: int, per_page: int, total: int, last_page: int, offset: int, links: string} $pagination */ ?>
<div class="card">
    <h3><?= e(__('club.list')) ?></h3>
    <?php if (empty($clubs)) : ?>
        <p><?= e(__('club.no_clubs')) ?></p>
    <?php else : ?>
        <table class="table-full">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= e(__('club.name')) ?></th>
                    <th><?= e(__('club.federal_code')) ?></th>
                    <th><?= e(__('club.register.contact_name')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clubs as $i => $club) : ?>
                    <tr>
                        <td><?= $pagination['offset'] + $i + 1 ?></td>
                        <td><?= e($club->name) ?></td>
                        <td><?= e($club->federal_code) ?></td>
                        <td><?= e($club->contact_first_name . ' ' . $club->contact_last_name) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <?= $pagination['links'] ?>
</div>
