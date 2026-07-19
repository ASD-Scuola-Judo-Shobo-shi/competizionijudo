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
use App\Model\JudoCategory;
use Throwable;

final class EventController extends Controller
{
    private const REGISTRATION_FEEDBACK_PREFIX = 'event_registration_';

    public function index(Request $request): Response
    {
        $limit = max(1, (int) config('app.events_upcoming_limit'));
        $events = Event::allPublishedIncludingClosed(date('Y-m-d'), $limit);

        // Check which events have registration exceptions for the logged-in club
        $loggedInClubId = AuthContext::clubId();
        $eventExceptions = [];
        if ($loggedInClubId !== null && !AuthContext::isAdministrator()) {
            foreach ($events as $ev) {
                if ($ev->closed) {
                    $eventExceptions[$ev->id] = \App\Model\EventRegistrationException::exists($ev->id, (int) $loggedInClubId);
                }
            }
        }

        return $this->view('events/index', [
            'title' => __('nav.events'),
            'events' => $events,
            'canViewEntries' => $this->canViewEntries(),
            'loggedInClubId' => $loggedInClubId !== null ? (int) $loggedInClubId : null,
            'eventExceptions' => $eventExceptions,
        ]);
    }

    public function show(Request $request): Response
    {
        $limit = max(1, (int) config('app.events_upcoming_limit'));
        $id = (int) ($request->input('id') ?? $request->query('id') ?? $request->query('event') ?? 0);

        // Check if logged-in club has registration exception for this closed event
        $clubId = AuthContext::clubId();
        $hasRegistrationException = false;
        if ($id > 0 && $clubId !== null && !AuthContext::isAdministrator()) {
            $hasRegistrationException = \App\Model\EventRegistrationException::exists($id, (int) $clubId);
        }

        if ($id > 0) {
            $event = Event::findPublishedById($id);
            if ($event === null) {
                return $this->redirect('/events');
            }

            $nextEvents = Event::nextUpcomingPublished($id, date('Y-m-d'), $limit);

            return $this->view('events/show', [
                'title' => $event->name,
                'event' => $event,
                'nextEvents' => $nextEvents,
                'upcomingEvents' => [],
                'canViewEntries' => $this->canViewEntries(),
                'hasRegistrationException' => $hasRegistrationException,
            ]);
        }

        $upcomingEvents = Event::upcomingPublished(date('Y-m-d'), $limit);

        return $this->view('events/show', [
            'title' => __('nav.events'),
            'event' => null,
            'nextEvents' => [],
            'upcomingEvents' => $upcomingEvents,
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

        $id = (int) ($request->input('id') ?? $request->query('id'));
        $limit = max(1, (int) config('app.events_upcoming_limit'));
        $registrationDate = date('Y-m-d');

        if ($id <= 0) {
            $upcomingEvents = Event::upcomingPublished($registrationDate, $limit);

            return $this->view('events/register', [
                'title' => __('events.registration'),
                'event' => null,
                'athletes' => [],
                'registered' => [],
                'nextEvents' => [],
                'upcomingEvents' => $upcomingEvents,
                'registrationFeedback' => null,
                'athleteCategories' => [],
            ]);
        }

        // Use club-specific eligibility check to allow exceptions for closed events
        $event = Event::findRegistrationEligibleByIdForClub($id, $registrationDate, $clubId);
        if ($event === null) {
            $upcomingEvents = Event::upcomingPublished($registrationDate, $limit);

            return $this->view('events/register', [
                'title' => __('events.registration'),
                'event' => null,
                'athletes' => [],
                'registered' => [],
                'nextEvents' => [],
                'upcomingEvents' => $upcomingEvents,
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
            $currentlyRegistered = Entry::findByClubEvent($id, $clubId);
            
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
                    $result = Entry::unregister($id, $clubId, $athleteId, $registrationDate);
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
                    $result = Entry::register($id, $clubId, $athleteId, $registrationDate);
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
            $flashFeedback = [];
            if ($feedback['added'] > 0) {
                $flashFeedback['added'] = $feedback['added'];
            }
            if ($feedback['already_registered'] > 0) {
                $flashFeedback['already_registered'] = $feedback['already_registered'];
            }
            if ($feedback['removed'] > 0) {
                $flashFeedback['removed'] = $feedback['removed'];
            }
            if ($feedback['rejected'] > 0) {
                $flashFeedback['rejected'] = $feedback['rejected'];
            }
            if ($feedback['capacity_exceeded'] > 0) {
                $flashFeedback['capacity_exceeded'] = $feedback['capacity_exceeded'];
            }
            if ($feedback['failed'] > 0) {
                $flashFeedback['failed'] = $feedback['failed'];
            }

            Session::flash(self::REGISTRATION_FEEDBACK_PREFIX . $id, $flashFeedback);

            return $this->redirect('/event_register.php?id=' . $id);
        }

        $athletes = Athlete::findByClub($clubId);
        $registered = Entry::findByClubEvent($id, $clubId);
        $nextEvents = Event::nextUpcomingPublished($id, $registrationDate, $limit);
        $registrationFeedback = $this->registrationFeedback($id);

        return $this->view('events/register', [
            'title' => __('events.registration') . ' - ' . $event->name,
            'event' => $event,
            'athletes' => $athletes,
            'registered' => $registered,
            'nextEvents' => $nextEvents,
            'upcomingEvents' => [],
            'registrationFeedback' => $registrationFeedback,
            'athleteCategories' => $this->athleteCategories($athletes, $event->date),
        ]);
    }

    public function entries(Request $request): Response
    {
        Session::start();
        $isAdmin = AuthContext::isAdministrator();
        $limit = max(1, (int) config('app.events_upcoming_limit'));

        $eventId = (int) ($request->input('event') ?? $request->query('event') ?? $request->query('id'));
        if ($eventId <= 0) {
            // Show all published events (including closed ones) for selection
            $events = Event::allPublishedIncludingClosed(date('Y-m-d'), $limit);

            return $this->view('events/entries', [
                'title' => __('events.entries'),
                'event' => null,
                'clubs' => [],
                'rows' => [],
                'isAdmin' => $isAdmin,
                'events' => $events,
                'nextEvents' => [],
                'upcomingEvents' => [],
            ]);
        }

        // For admins, can view any published event (including closed)
        // For clubs, can view open published events OR closed events they have entries in OR closed events with registration exceptions
        $clubId = AuthContext::clubId();
        $hasRegistrationException = false;
        if ($isAdmin) {
            $event = Event::findById($eventId);
        } elseif ($clubId !== null) {
            $event = Event::findPublishedByIdOrClosedWithEntries($eventId, (int) $clubId);
            // Check if club has registration exception for this closed event (needed for showing registration link)
            if ($event !== null && $event->closed) {
                $hasRegistrationException = \App\Model\EventRegistrationException::exists($eventId, (int) $clubId);
            }
        } else {
            $event = Event::findPublishedByIdIncludingClosed($eventId);
        }
        if ($event === null) {
            return $this->redirect('/events');
        }

        // The club parameter is now ignored - always show all entries globally
        $clubs = Entry::findClubsByEvent($eventId, null);
        $rows = Entry::findByEvent($eventId, null);

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
            $birthDate = $row['date_of_birth'] ?? '';
            $eventDate = $row['event_date'] ?? '';
            $birthYear = JudoCategory::extractBirthYear($birthDate);
            $eventYear = $eventDate !== '' ? (int) substr($eventDate, 0, 4) : (int) date('Y');
            if ($birthYear !== null) {
                $acResult = AgeClass::calculate($birthYear, $eventYear);
                $category = $acResult['label'];
            } else {
                $category = '';
            }
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
            // Count by category (age class) only - for pie chart
            $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
            // Count by weight with 4kg grouping - for pie chart
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

        // Get athlete counts per club
        $clubAthleteCounts = [];
        foreach ($clubs as $club) {
            $stmt = Database::connection()->prepare(
                'SELECT COUNT(*) FROM entries WHERE event_id = ? AND club_id = ?'
            );
            $stmt->execute([$eventId, $club['id']]);
            $clubAthleteCounts[$club['id']] = (int) $stmt->fetchColumn();
        }

        $nextEvents = Event::nextUpcomingPublished($eventId, date('Y-m-d'), $limit);

        $loggedInClubId = $clubId !== null ? (int) $clubId : null;
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
            'loggedInClubId' => $loggedInClubId,
            'hasRegistrationException' => $hasRegistrationException,
            'nextEvents' => $nextEvents,
            'upcomingEvents' => [],
        ]);
    }

    private function canViewEntries(): bool
    {
        return true;
    }

    /**
     * @param list<Athlete> $athletes
     * @return array<int, array{age_below: int|null, program: string, weight_category: string}>
     */
    private function athleteCategories(array $athletes, string $eventDate): array
    {
        $categories = [];
        foreach ($athletes as $athlete) {
            $categories[$athlete->id] = $athlete->categoryForEventDate($eventDate);
        }

        return $categories;
    }

    /**
     * Groups weight category into 4kg increments starting from 12kg.
     * Returns format like "12-16kg", "16-20kg", ..., "100+kg".
     * Handles weight categories like "-16 kg" or "+100 kg".
     */
    private static function groupWeight(string $weight): string
    {
        $weight = trim($weight, ' kg');
        
        // Strip leading -/+ signs used in weight categories (e.g., "-16 kg", "+100 kg")
        $weight = ltrim($weight, '-+');
        
        if (!is_numeric($weight)) {
            return 'unspecified';
        }
        $w = (int) $weight;
        
        // Group from 12kg in 4kg increments
        // Weights 12-15 go to 12-16kg, 16-19 go to 16-20kg, etc.
        $lowerBound = 12;
        while ($lowerBound + 4 <= $w) {
            $lowerBound += 4;
        }
        $upperBound = $lowerBound + 4;
        
        // Handle weights below 12kg
        if ($w < 12) {
            return 'under-12kg';
        }
        
        // Handle above 100kg
        if ($lowerBound >= 100) {
            return '100+kg';
        }
        
        return $lowerBound . '-' . $upperBound . 'kg';
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