<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\AuthContext;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Model\AgeClass;
use App\Model\Athlete;
use App\Model\Database;
use App\Model\Entry;
use App\Model\EntryRegistrationResult;
use App\Model\Event;
use App\Model\EventRegistrationException;
use App\Model\JudoCategory;
use Throwable;

final class EventController extends Controller
{
    private const REGISTRATION_FEEDBACK_PREFIX = 'event_registration_';

    public function index(Request $request): Response
    {
        $limit = max(1, (int) config('app.events_upcoming_limit'));
        $events = Event::allPublishedIncludingClosed(date('Y-m-d'), $limit);

        $loggedInClubId = AuthContext::clubId();

        return $this->view('events/index', [
            'title' => __('nav.events'),
            'events' => $events,
            'canViewEntries' => $this->canViewEntries(),
            'loggedInClubId' => $loggedInClubId !== null ? (int) $loggedInClubId : null,
            'eventExceptions' => $this->resolveEventExceptions($events),
        ]);
    }

    public function details(Request $request): Response
    {
        $limit = max(1, (int) config('app.events_upcoming_limit'));
        $id = (int) ($request->input('id') ?? $request->query('id') ?? $request->query('event') ?? 0);
        $date = date('Y-m-d');

        // Check if logged-in club has registration exception for this specific event
        $clubId = AuthContext::clubId();
        $hasRegistrationException = false;
        if ($id > 0 && $clubId !== null && !AuthContext::isAdministrator()) {
            $hasRegistrationException = EventRegistrationException::exists($id, (int) $clubId);
        }

        if ($id > 0) {
            $event = Event::findPublishedById($id);
            if ($event === null) {
                return $this->redirect('/events');
            }

            $upcomingEvents = $this->resolveUpcomingEvents($event->id, $date, $limit);

            return $this->view('events/details', [
                'title' => $event->name,
                'event' => $event,
                'upcomingEvents' => $upcomingEvents,
                'eventExceptions' => $this->resolveEventExceptions($upcomingEvents),
                'canViewEntries' => $this->canViewEntries(),
                'hasRegistrationException' => $hasRegistrationException,
            ]);
        }

        $upcomingEvents = $this->resolveUpcomingEvents(null, $date, $limit);

        return $this->view('events/details', [
            'title' => __('nav.events'),
            'event' => null,
            'upcomingEvents' => $upcomingEvents,
            'eventExceptions' => $this->resolveEventExceptions($upcomingEvents),
            'canViewEntries' => $this->canViewEntries(),
            'hasRegistrationException' => false,
        ]);
    }

    public function register(Request $request): Response
    {
        Session::start();
        $clubId = AuthContext::clubId();

        if (!is_numeric($clubId) || (int) $clubId <= 0) {
            return $this->redirect('/clubs/login');
        }
        $clubId = (int) $clubId;

        $eventId = (int) ($request->input('event') ?? $request->query('event'));
        $limit = max(1, (int) config('app.events_upcoming_limit'));
        $registrationDate = date('Y-m-d');

        if ($eventId <= 0) {
            $upcomingEvents = $this->resolveUpcomingEvents(null, $registrationDate, $limit);

            return $this->view('events/register', [
                'title' => __('events.registration'),
                'event' => null,
                'athletes' => [],
                'registered' => [],
                'upcomingEvents' => $upcomingEvents,
                'eventExceptions' => $this->resolveEventExceptions($upcomingEvents),
                'registrationFeedback' => null,
                'athleteCategories' => [],
            ]);
        }

        // Use club-specific eligibility check to allow exceptions for closed events
        $event = Event::findRegistrationEligibleByIdForClub($eventId, $registrationDate, $clubId);
        if ($event === null) {
            $upcomingEvents = $this->resolveUpcomingEvents(null, $registrationDate, $limit);

            return $this->view('events/register', [
                'title' => __('events.registration'),
                'event' => null,
                'athletes' => [],
                'registered' => [],
                'upcomingEvents' => $upcomingEvents,
                'eventExceptions' => $this->resolveEventExceptions($upcomingEvents),
                'registrationFeedback' => null,
                'athleteCategories' => [],
            ]);
        }

        if ($request->method() === 'POST') {
            validate_csrf((string) $request->post('csrf_token'));

            // Handle registration/unregistration action based on checkbox states
            $athleteIds = $request->input('athletes', []);
            if (!is_array($athleteIds)) {
                $athleteIds = [$athleteIds];
            }

            // Get currently registered athletes for comparison
            $currentlyRegistered = Entry::findByClubEvent($eventId, $clubId);

            // Validate and filter athlete IDs - count invalid ones as rejected
            $validAthleteIds = [];
            $rejectedCount = 0;
            foreach ($athleteIds as $athleteId) {
                if (is_numeric($athleteId) && (int) $athleteId > 0) {
                    $validAthleteIds[] = (int) $athleteId;
                } else {
                    $rejectedCount++;
                }
            }

            // Determine which athletes to register and which to unregister
            $toRegister = array_values(array_diff($validAthleteIds, $currentlyRegistered));
            $toUnregister = array_values(array_diff($currentlyRegistered, $validAthleteIds));

            $feedback = [
                'added' => 0,
                'removed' => 0,
                'rejected' => $rejectedCount,
                'already_registered' => 0,
                'capacity_exceeded' => 0,
                'failed' => 0,
            ];

            // Handle unregistrations first (removals take priority)
            foreach ($toUnregister as $athleteId) {
                try {
                    $result = Entry::unregister($eventId, $clubId, $athleteId, $registrationDate);
                    if ($result === EntryRegistrationResult::Unsubscribed) {
                        $feedback['removed']++;
                    } else {
                        $feedback['failed']++;
                    }
                } catch (Throwable $exception) {
                    $feedback['failed']++;
                    $this->reportFailure('event.unregistration_failed', $exception, $request);
                }
            }

            // Handle registrations
            foreach ($toRegister as $athleteId) {
                try {
                    $result = Entry::register($eventId, $clubId, $athleteId, $registrationDate);
                    match ($result) {
                        EntryRegistrationResult::Registered => $feedback['added']++,
                        EntryRegistrationResult::AlreadyRegistered => $feedback['already_registered']++,
                        EntryRegistrationResult::AthleteRejected => $feedback['rejected']++,
                        EntryRegistrationResult::CapacityExceeded => $feedback['capacity_exceeded']++,
                    };
                } catch (Throwable $exception) {
                    $feedback['failed']++;
                    $this->reportFailure('event.registration_failed', $exception, $request);
                }
            }

            // Count athletes that were already registered and still checked (no change)
            $stillChecked = array_intersect($currentlyRegistered, $validAthleteIds);
            $feedback['already_registered'] += count($stillChecked);

            // Build feedback message
            $flashFeedback = array_filter([
                'added' => $feedback['added'],
                'already_registered' => $feedback['already_registered'],
                'removed' => $feedback['removed'],
                'rejected' => $feedback['rejected'],
                'capacity_exceeded' => $feedback['capacity_exceeded'],
                'failed' => $feedback['failed'],
            ]);

            Session::flash(self::REGISTRATION_FEEDBACK_PREFIX . $eventId, $flashFeedback);

            return $this->redirect('/events/register?event=' . $eventId);
        }

        $athletes = Athlete::findByClub($clubId);
        $registered = Entry::findByClubEvent($eventId, $clubId);
        $registrationFeedback = $this->registrationFeedback($eventId);
        $upcomingEvents = $this->resolveUpcomingEvents($eventId, $registrationDate, $limit);

        return $this->view('events/register', [
            'title' => __('events.registration') . ' - ' . $event->name,
            'event' => $event,
            'athletes' => $athletes,
            'registered' => $registered,
            'upcomingEvents' => $upcomingEvents,
            'eventExceptions' => $this->resolveEventExceptions($upcomingEvents),
            'registrationFeedback' => $registrationFeedback,
            'athleteCategories' => $this->athleteCategories($athletes, $event->date),
        ]);
    }

    public function entries(Request $request): Response
    {
        Session::start();
        $isAdmin = AuthContext::isAdministrator();
        $limit = max(1, (int) config('app.events_upcoming_limit'));
        $date = date('Y-m-d');

        $eventId = (int) ($request->input('event') ?? $request->query('event') ?? $request->query('id'));
        if ($eventId <= 0) {
            $upcomingEvents = $this->resolveUpcomingEvents(null, $date, $limit);

            return $this->view('events/entries', [
                'title' => __('events.entries'),
                'event' => null,
                'clubs' => [],
                'rows' => [],
                'isAdmin' => $isAdmin,
                'loggedInClubId' => null,
                'hasRegistrationException' => false,
                'grouped' => [],
                'categoryCounts' => [],
                'weightCounts' => [],
                'beltCounts' => [],
                'genderCounts' => [],
                'clubAthleteCounts' => [],
                'clubCategoryCounts' => [],
                'clubWeightCounts' => [],
                'clubBeltCounts' => [],
                'clubGenderCounts' => [],
                'upcomingEvents' => $upcomingEvents,
                'eventExceptions' => $this->resolveEventExceptions($upcomingEvents),
            ]);
        }

        $clubId = AuthContext::clubId();
        $hasRegistrationException = false;
        if ($isAdmin) {
            $event = Event::findById($eventId);
        } elseif ($clubId !== null) {
            $event = Event::findPublishedByIdOrClosedWithEntries($eventId, (int) $clubId);
            if ($event !== null && $event->closed) {
                $hasRegistrationException = EventRegistrationException::exists($eventId, (int) $clubId);
            }
        } else {
            $event = Event::findPublishedByIdIncludingClosed($eventId);
        }

        if ($event === null) {
            return $this->redirect('/events');
        }

        $clubs = Entry::findClubsByEvent($eventId, null);
        $rows = Entry::findByEvent($eventId, null, $event->closed);

        $grouped = [];
        $categoryCounts = [];
        $weightCounts = [];
        $beltCounts = [];
        $genderCounts = [];
        $clubCategoryCounts = [];
        $clubWeightCounts = [];
        $clubBeltCounts = [];
        $clubGenderCounts = [];

        foreach ($rows as $row) {
            $birthDate = $row['birth_date'] ?? '';
            $eventDate = $row['event_date'] ?? '';
            $birthYear = JudoCategory::extractBirthYear($birthDate);
            $eventYear = $eventDate !== '' ? (int) substr($eventDate, 0, 4) : (int) date('Y');
            $category = $birthYear !== null ? AgeClass::calculate($birthYear, $eventYear)['label'] : '';
            $weight = $row['weight_category'] ?? '';
            $belt = $row['belt'] ?? '';
            $gender = $row['gender'] ?? '';
            $rowClubId = (int) ($row['club_id'] ?? 0);

            if ($category === '') {
                $category = __('events.no_category');
            }
            if ($weight === '') {
                $weight = __('events.no_weight');
            }

            $groupedKey = $category . ' | ' . $weight;
            $grouped[$groupedKey][] = $row;

            $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
            $weightGroup = self::groupWeight($weight);
            $weightCounts[$weightGroup] = ($weightCounts[$weightGroup] ?? 0) + 1;
            $beltCounts[$belt] = ($beltCounts[$belt] ?? 0) + 1;
            $genderCounts[$gender] = ($genderCounts[$gender] ?? 0) + 1;

            if ($rowClubId > 0) {
                $clubCategoryCounts[$rowClubId][$category] = ($clubCategoryCounts[$rowClubId][$category] ?? 0) + 1;
                $clubWeightCounts[$rowClubId][$weightGroup] = ($clubWeightCounts[$rowClubId][$weightGroup] ?? 0) + 1;
                $clubBeltCounts[$rowClubId][$belt] = ($clubBeltCounts[$rowClubId][$belt] ?? 0) + 1;
                $clubGenderCounts[$rowClubId][$gender] = ($clubGenderCounts[$rowClubId][$gender] ?? 0) + 1;
            }
        }

        $clubAthleteCounts = [];
        foreach ($clubs as $club) {
            $stmt = Database::connection()->prepare(
                'SELECT COUNT(*) FROM entries WHERE event_id = ? AND club_id = ?'
            );
            $stmt->execute([$eventId, $club['id']]);
            $clubAthleteCounts[$club['id']] = (int) $stmt->fetchColumn();
        }

        $upcomingEvents = $this->resolveUpcomingEvents($eventId, $date, $limit);

        return $this->view('events/entries', [
            'title' => __('events.entries') . ' - ' . $event->name,
            'event' => $event,
            'clubs' => $clubs,
            'clubAthleteCounts' => $clubAthleteCounts,
            'clubCategoryCounts' => $clubCategoryCounts,
            'clubWeightCounts' => $clubWeightCounts,
            'clubBeltCounts' => $clubBeltCounts,
            'clubGenderCounts' => $clubGenderCounts,
            'rows' => $rows,
            'grouped' => $grouped,
            'categoryCounts' => $categoryCounts,
            'weightCounts' => $weightCounts,
            'beltCounts' => $beltCounts,
            'genderCounts' => $genderCounts,
            'isAdmin' => $isAdmin,
            'loggedInClubId' => $clubId !== null ? (int) $clubId : null,
            'hasRegistrationException' => $hasRegistrationException,
            'upcomingEvents' => $upcomingEvents,
            'eventExceptions' => $this->resolveEventExceptions($upcomingEvents),
        ]);
    }

    /**
     * Resolves upcoming/next published events (including closed ones) into a single array contract.
     *
     * @return list<Event>
     */
    private function resolveUpcomingEvents(?int $eventId, string $date, int $limit): array
    {
        if ($eventId !== null && $eventId > 0) {
            return Event::nextUpcomingPublishedIncludingClosed($eventId, $date, $limit);
        }

        return Event::upcomingPublishedIncludingClosed($date, $limit);
    }

    /**
     * Resolves registration exception states for a list of events for the logged-in club.
     *
     * @param list<Event> $events
     * @return array<int, bool>
     */
    private function resolveEventExceptions(array $events): array
    {
        $clubId = AuthContext::clubId();
        if ($clubId === null || AuthContext::isAdministrator()) {
            return [];
        }

        $exceptions = [];
        foreach ($events as $ev) {
            if ($ev->closed) {
                $exceptions[$ev->id] = EventRegistrationException::exists($ev->id, (int) $clubId);
            }
        }

        return $exceptions;
    }

    private function canViewEntries(): bool
    {
        return true;
    }

    /**
     * @param list<Athlete> $athletes
     * @return array<int, array{age_below: int|null, type: string, weight_category: string}>
     */
    private function athleteCategories(array $athletes, string $eventDate): array
    {
        $categories = [];
        foreach ($athletes as $athlete) {
            $categories[$athlete->id] = $athlete->categoryForEventDate($eventDate);
        }

        return $categories;
    }

    private static function groupWeight(string $weight): string
    {
        $weight = ltrim(trim($weight, ' kg'), '-+');

        if (!is_numeric($weight)) {
            return 'unspecified';
        }
        $w = (int) $weight;

        if ($w < 12) {
            return 'under-12kg';
        }

        $lowerBound = 12;
        while ($lowerBound + 4 <= $w) {
            $lowerBound += 4;
        }

        if ($lowerBound >= 100) {
            return '100+kg';
        }

        return $lowerBound . '-' . ($lowerBound + 4) . 'kg';
    }

    /** @return array{added?: int, already_registered?: int, rejected?: int, capacity_exceeded?: int, failed?: int, removed?: int, unsubscribed_failed?: int}|null */
    private function registrationFeedback(int $eventId): ?array
    {
        $feedback = Session::pullFlash(self::REGISTRATION_FEEDBACK_PREFIX . $eventId);
        if (!is_array($feedback)) {
            return null;
        }

        return [
            'added' => max(0, (int) ($feedback['added'] ?? 0)),
            'already_registered' => max(0, (int) ($feedback['already_registered'] ?? 0)),
            'rejected' => max(0, (int) ($feedback['rejected'] ?? 0)),
            'capacity_exceeded' => max(0, (int) ($feedback['capacity_exceeded'] ?? 0)),
            'failed' => max(0, (int) ($feedback['failed'] ?? 0)),
            'removed' => max(0, (int) ($feedback['removed'] ?? 0)),
            'unsubscribed_failed' => max(0, (int) ($feedback['unsubscribed_failed'] ?? 0)),
        ];
    }
}
