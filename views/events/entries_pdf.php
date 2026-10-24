<?php

declare(strict_types=1);

/** @var \App\Model\Event $event */
/** @var list<array{club_name: string, federal_code: string, athletes: list<array<string, mixed>>}> $clubRosters */
/** @var string $generatedAt */
?>
<?php if ($clubRosters === []) : ?>
    <h1><?= e($event->name) ?></h1>
    <p><?= e(__('admin.event_details.no_enrolled_athletes')) ?></p>
<?php else : ?>
    <?php foreach ($clubRosters as $roster) : ?>
        <div class="club-roster">
            <?php
            $athletes = $roster['athletes'];
            require __DIR__ . '/_entries_club_pdf.php';
            ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
