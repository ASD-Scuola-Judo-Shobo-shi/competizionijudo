<?php

declare(strict_types=1);

use App\Model\Database;
use App\Service\AthleteDuplicateCleanupResult;
use App\Service\AthleteDuplicateReconciler;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/helpers.php';

load_env(dirname(__DIR__) . '/.env');

try {
    [$apply, $clubId] = cleanupArguments($argv);
    $result = (new AthleteDuplicateReconciler(Database::connection()))->run($apply, $clubId);
    printCleanupResult($result, $clubId);

    exit($apply && $result->blockedGroups !== [] ? 2 : 0);
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n\n");
    printCleanupUsage(STDERR);
    exit(2);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Athlete duplicate cleanup failed: ' . $exception->getMessage() . "\n");
    exit(1);
}

/**
 * @param list<string> $arguments
 * @return array{bool, int|null}
 */
function cleanupArguments(array $arguments): array
{
    $apply = false;
    $clubId = null;
    for ($index = 1; $index < count($arguments); $index++) {
        $argument = $arguments[$index];
        if ($argument === '--help' || $argument === '-h') {
            printCleanupUsage(STDOUT);
            exit(0);
        }
        if ($argument === '--apply') {
            $apply = true;
            continue;
        }
        if ($argument === '--club-id') {
            $index++;
            $argument = $arguments[$index] ?? '';
        } elseif (str_starts_with($argument, '--club-id=')) {
            $argument = substr($argument, strlen('--club-id='));
        } else {
            throw new InvalidArgumentException('Unknown argument: ' . $argument);
        }

        if (preg_match('/\A[1-9]\d*\z/', $argument) !== 1) {
            throw new InvalidArgumentException('The club ID must be a positive integer.');
        }
        $clubId = (int) $argument;
    }

    return [$apply, $clubId];
}

/** @param resource $stream */
function printCleanupUsage($stream): void
{
    fwrite(
        $stream,
        "Usage: php scripts/reconcile-duplicate-athletes.php [--club-id=ID] [--apply]\n"
        . "\n"
        . "Without --apply, the command is a read-only dry run. Before applying, stop imports,\n"
        . "take and verify a database backup, and review every blocked/manual-review group.\n"
    );
}

function printCleanupResult(AthleteDuplicateCleanupResult $result, ?int $clubId): void
{
    echo $result->applied ? "Mode: APPLY\n" : "Mode: DRY RUN (no changes made)\n";
    echo $clubId !== null ? "Club filter: {$clubId}\n" : "Club filter: all clubs\n";
    echo sprintf(
        "%s %d duplicate athlete(s) in %d safe group(s); %d registration(s) %s.\n",
        $result->applied ? 'Merged' : 'Would merge',
        $result->duplicateAthletes(),
        count($result->groups),
        $result->entryMoves(),
        $result->applied ? 'reassigned' : 'would be reassigned'
    );

    foreach ($result->groups as $group) {
        echo sprintf(
            "  Club %d: %s %s, born %s — keep #%d; %s #%s; entries %d.\n",
            $group['club_id'],
            $group['last_name'],
            $group['first_name'],
            $group['birth_date'],
            $group['survivor_id'],
            $result->applied ? 'merged' : 'merge',
            implode(', #', $group['duplicate_ids']),
            $group['entry_moves']
        );
        foreach ($group['resolutions'] as $athleteId => $resolutions) {
            if ($resolutions === []) {
                echo "    #{$athleteId}: exact duplicate.\n";
                continue;
            }
            $details = [];
            foreach ($resolutions as $field => $resolution) {
                $details[] = $field . '=' . $resolution;
            }
            echo "    #{$athleteId}: " . implode(', ', $details) . ".\n";
        }
    }

    if ($result->blockedGroups !== []) {
        echo "Blocked groups (left unchanged):\n";
        foreach ($result->blockedGroups as $group) {
            $detail = $group['reason'] === 'overlapping_entries'
                ? 'both IDs are registered for event(s) ' . implode(', ', $group['overlapping_event_ids'])
                : 'an entry has a mismatched club ID';
            echo sprintf(
                "  Club %d: %s %s, born %s — IDs #%s; %s.\n",
                $group['club_id'],
                $group['last_name'],
                $group['first_name'],
                $group['birth_date'],
                implode(', #', $group['athlete_ids']),
                $detail
            );
        }
    }

    if ($result->nameCollisions !== []) {
        echo "Manual review (same case-insensitive name, different birth dates):\n";
        foreach ($result->nameCollisions as $collision) {
            echo sprintf(
                "  Club %d: %s %s — IDs #%s; birth dates %s.\n",
                $collision['club_id'],
                $collision['last_name'],
                $collision['first_name'],
                implode(', #', $collision['athlete_ids']),
                implode(', ', $collision['birth_dates'])
            );
        }
    }

    if (!$result->applied && $result->groups !== []) {
        echo "Review the report, take a verified backup, then repeat with --apply.\n";
    }
    if ($result->applied && $result->blockedGroups !== []) {
        echo "Safe groups were applied, but blocked groups still require manual reconciliation.\n";
    }
}
