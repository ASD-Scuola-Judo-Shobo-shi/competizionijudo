<?php

/** @var \App\Model\Event|null $event */
/** @var \App\Presentation\EventEntriesViewModel $entryReport */
/** @var int|null $loggedInClubId */
/** @var bool $hasRegistrationException */
/** @var list<\App\Model\Event> $upcomingEvents */
/** @var array<int, bool> $eventExceptions */
/** @var list<array<string, mixed>>|null $entryClubs */
/** @var list<array{category:string, weight:string, ageMin:int, athletes:list<array<string, mixed>>}>|null $entryAthleteGroups */
/** @var list<array<string, mixed>>|null $currentClubEntries */
$entryClubs ??= $entryReport->clubs;
$entryAthleteGroups ??= $entryReport->athleteGroups;
$currentClubEntries ??= $entryReport->currentClubEntries;
$entryClubsPagination ??= paginate(count($entryClubs), 1, 50, 'clubs_page');
$entryAthletesPagination ??= paginate(count($entryReport->entries), 1, 50, 'athletes_page');
$currentClubPagination ??= paginate(count($currentClubEntries), 1, 50, 'club_entries_page');
?>

<?php if ($event !== null) : ?>
    <?php require __DIR__ . '/_entries_summary.php'; ?>

    <?php if ($event->closed && $loggedInClubId !== null) : ?>
        <?php require __DIR__ . '/_entries_current_club.php'; ?>
    <?php endif; ?>

    <?php require __DIR__ . '/_entries_clubs.php'; ?>

    <?php if ($event->closed && ($loggedInClubId === null || !$hasRegistrationException)) : ?>
        <?php require __DIR__ . '/_entries_athletes.php'; ?>
    <?php endif; ?>
<?php endif; ?>

<?php
$upcomingEventAction = 'entries';
require dirname(__DIR__) . '/components/upcoming_events.php';
