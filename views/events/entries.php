<?php

/** @var \App\Model\Event|null $event */
/** @var \App\Presentation\EventEntriesViewModel $entryReport */
/** @var int|null $loggedInClubId */
/** @var bool|null $canViewEntryBreakdowns */
/** @var bool|null $canViewAllAthleteEntries */
/** @var bool|null $requiresRosterAgreements */
/** @var bool $hasRegistrationException */
/** @var list<\App\Model\Event> $upcomingEvents */
/** @var array<int, bool> $eventExceptions */
/** @var list<array<string, mixed>>|null $entryClubs */
/** @var list<array{category:string, weight:string, ageMin:int, athletes:list<array<string, mixed>>}>|null $entryAthleteGroups */
/** @var list<array<string, mixed>>|null $currentClubEntries */
/** @var list<array{ageClass:string, weightCategory:string, ageMin:int, athletes:list<array<string, mixed>>}>|null $entryOpponentGroups */
/** @var array<string, string>|null $entryClubFilters */
/** @var array<string, string>|null $entryAthleteFilters */
/** @var array<string, string>|null $currentClubFilters */
/** @var array<string, string>|null $entryOpponentFilters */
/** @var array<string, string>|null $entriesQueryState */
$entryClubs ??= $entryReport->clubs;
$entryAthleteGroups ??= $entryReport->athleteGroups;
$currentClubEntries ??= $entryReport->currentClubEntries;
$entryOpponentGroups ??= $entryReport->opponentGroups();
$canViewEntryBreakdowns ??= false;
$canViewAllAthleteEntries ??= false;
$requiresRosterAgreements ??= false;
$entryClubsPagination ??= paginate(count($entryClubs), 1, 50, 'clubs_page');
$entryAthletesPagination ??= paginate(count($entryReport->entries), 1, 50, 'athletes_page');
$currentClubPagination ??= paginate(count($currentClubEntries), 1, 50, 'club_entries_page');
$entryClubsSort ??= ['column' => 'club', 'direction' => 'asc'];
$entryAthletesSort ??= ['column' => 'age_class', 'direction' => 'asc'];
$currentClubSort ??= ['column' => 'weight', 'direction' => 'asc'];
$entryClubFilters ??= [
    'club' => '',
    'federal_code' => '',
    'athletes' => '',
    'breakdown' => '',
];
$entryAthleteFilters ??= [
    'age_class' => '',
    'weight_category' => '',
    'athlete' => '',
    'club' => '',
    'gender' => '',
    'belt' => '',
];
$currentClubFilters ??= [
    'athlete' => '',
    'gender' => '',
    'weight' => '',
    'weight_category' => $entryReport->selectedWeightCategory,
    'belt' => '',
];
$entryOpponentFilters ??= [
    'age_class' => '',
    'weight_category' => '',
    'athlete' => '',
    'club' => '',
    'gender' => '',
    'belt' => '',
];
if ($event !== null) {
    $entriesQueryState ??= [
        'event' => (string) $event->id,
        'clubs_sort' => $entryClubsSort['column'],
        'clubs_direction' => $entryClubsSort['direction'],
        'athletes_sort' => $entryAthletesSort['column'],
        'athletes_direction' => $entryAthletesSort['direction'],
        'club_entries_sort' => $currentClubSort['column'],
        'club_entries_direction' => $currentClubSort['direction'],
    ];
    $defaultEntriesUrl = '/events/entries?event=' . rawurlencode((string) $event->id);
    $entryClubsFilterResetUrl ??= $defaultEntriesUrl;
    $entryAthletesFilterResetUrl ??= $defaultEntriesUrl;
    $currentClubFilterResetUrl ??= $defaultEntriesUrl;
    $entryOpponentsFilterResetUrl ??= $defaultEntriesUrl;
    $defaultExportUrl = '/events/entries/export?event=' . rawurlencode((string) $event->id);
    if ($currentClubFilters['weight_category'] !== '') {
        $defaultExportUrl .= '&weight_category=' . rawurlencode($currentClubFilters['weight_category']);
    }
    $clubEntriesExportUrl ??= $defaultExportUrl;
}
?>

<?php if ($event !== null) : ?>
    <?php require __DIR__ . '/_entries_summary.php'; ?>

    <?php if ($loggedInClubId !== null) : ?>
        <?php require __DIR__ . '/_entries_current_club.php'; ?>
    <?php endif; ?>

    <?php if ($event->closed && $loggedInClubId !== null && $canViewAllAthleteEntries) : ?>
        <?php require __DIR__ . '/_entries_opponents.php'; ?>
    <?php endif; ?>

    <?php require __DIR__ . '/_entries_clubs.php'; ?>

    <?php if ($event->closed && $requiresRosterAgreements) : ?>
        <div class="notice" role="status">
            <?= e(__('events.entries_roster_agreements_required')) ?>
            <a href="<?= e(base_url('/clubs/agreements')) ?>">
                <?= e(__('events.entries_review_agreements')) ?>
            </a>
        </div>
    <?php endif; ?>

    <?php if ($event->closed && $canViewAllAthleteEntries) : ?>
        <?php require __DIR__ . '/_entries_athletes.php'; ?>
    <?php endif; ?>
<?php endif; ?>

<?php
$upcomingEventAction = 'entries';
require dirname(__DIR__) . '/components/upcoming_events.php';
