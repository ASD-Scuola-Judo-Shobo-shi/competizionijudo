<?php /** @var list<\App\Model\Event> $events */ ?>
<?php /** @var array<int, array{clubs: int, athletes: int}> $entry_counts */ ?>
<?php /** @var array{page: int, per_page: int, total: int, last_page: int, offset: int, links: string} $pagination */ ?>
<section class="card admin-list-page">
    <header class="admin-list-heading">
        <h2>
            <?= e(__('admin.events.title')) ?>
            <span class="count-badge"><?= e((string) $pagination['total']) ?></span>
        </h2>
    </header>

    <?php if (empty($events)) : ?>
        <p class="admin-list-empty"><?= e(__('admin.events.empty')) ?></p>
    <?php else : ?>
        <div class="admin-card-list" role="list">
            <?php foreach ($events as $event) : ?>
                <?php
                $typeLabel = match ($event->type) {
                    'only_precompetitive' => __('admin.events.type_tooltip.precompetitive'),
                    'only_competitive' => __('admin.events.type_tooltip.competitive'),
                    'precompetitive_and_competitive' => __('admin.events.type_tooltip.both'),
                    default => __('admin.common.not_available'),
                };
                $clubCount = $entry_counts[$event->id]['clubs'] ?? 0;
                $athleteCount = $entry_counts[$event->id]['athletes'] ?? 0;
    ?>
                <article class="admin-entity-card admin-event-card" role="listitem">
                    <header class="admin-entity-card__header">
                        <h3><?= e($event->name) ?></h3>
                        <time datetime="<?= e($event->date) ?>" class="admin-date-badge">
                            <?= e($event->date) ?>
                        </time>
                    </header>

                    <dl class="admin-entity-details">
                        <div>
                            <dt><?= e(__('admin.events.location')) ?></dt>
                            <dd><?= e($event->location) ?></dd>
                        </div>
                        <div>
                            <dt><?= e(__('admin.events.type')) ?></dt>
                            <dd><?= e($typeLabel) ?></dd>
                        </div>
                        <div>
                            <dt><?= e(__('admin.events.visibility')) ?></dt>
                            <dd>
                                <span class="admin-status-chip <?= $event->published ? 'is-positive' : 'is-muted' ?>">
                                    <?= e($event->published ? __('admin.events.published_tooltip') : __('admin.events.hidden_tooltip')) ?>
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt><?= e(__('admin.events.registration_status')) ?></dt>
                            <dd>
                                <span class="admin-status-chip <?= $event->closed ? 'is-closed' : 'is-positive' ?>">
                                    <?= e($event->closed ? __('admin.events.closed_tooltip') : __('admin.events.open_tooltip')) ?>
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt><?= e(__('admin.events.clubs')) ?></dt>
                            <dd><strong><?= e((string) $clubCount) ?></strong></dd>
                        </div>
                        <div>
                            <dt><?= e(__('admin.events.athletes')) ?></dt>
                            <dd><strong><?= e((string) $athleteCount) ?></strong></dd>
                        </div>
                        <div>
                            <dt><?= e(__('admin.events.max_participants')) ?></dt>
                            <dd>
                                <?= e($event->max_participants !== null
                                    ? (string) $event->max_participants
                                    : __('admin.events.unlimited')) ?>
                            </dd>
                        </div>
                    </dl>

                    <div class="admin-card-actions">
                        <a
                            class="btn btn-sm green"
                            href="<?= e(base_url('/admin/events/add?event_id=' . (int) $event->id)) ?>"
                        ><?= e(__('admin.events.edit')) ?></a>
                        <a
                            class="btn btn-sm"
                            href="<?= e(base_url('/admin/events/export?event_id=' . (int) $event->id)) ?>"
                        ><?= e(__('admin.events.export')) ?></a>
                        <form method="post" action="<?= e(base_url('/admin/events/delete?')) ?>" onsubmit="return confirm('<?= e(__('admin.events.confirm_delete')) ?>')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="event_id" value="<?= (int) $event->id ?>">
                            <button class="btn btn-sm red" type="submit"><?= e(__('admin.events.delete')) ?></button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?= $pagination['links'] ?>
</section>
