<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Model\AgeClass;
use App\Model\Athlete;
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
        $events = Event::upcomingPublished(date('Y-m-d'), $limit);

        return $this->view('events/index', [
            'events' => $events,
            'canViewEntries' => $this->canViewEntries(),
        ]);
    }

    public function show(Request $request): Response
    {
        $limit = max(1, (int) config('app.events_upcoming_limit'));
        $id = (int) ($request->input('id') ?? $request->query('id') ?? $request->query('event') ?? 0);

        if ($id > 0) {
            $event = Event::findPublishedById($id);
            if ($event === null) {
                return $this->redirect('/events.php');
            }

            $nextEvents = Event::nextUpcomingPublished($id, date('Y-m-d'), $limit);

            return $this->view('events/show', [
                'event' => $event,
                'nextEvents' => $nextEvents,
                'upcomingEvents' => [],
                'canViewEntries' => $this->canViewEntries(),
            ]);
        }

        $upcomingEvents = Event::upcomingPublished(date('Y-m-d'), $limit);

        return $this->view('events/show', [
            'event' => null,
            'nextEvents' => [],
            'upcomingEvents' => $upcomingEvents,
            'canViewEntries' => $this->canViewEntries(),
        ]);
    }

    public function register(Request $request): Response
    {
        Session::start();
        $clubId = Session::get('club_id');

        if (!is_numeric($clubId) || (int) $clubId <= 0) {
            return $this->redirect('/club_login.php');
        }
        $clubId = (int) $clubId;

        $id = (int) ($request->input('id') ?? $request->query('id'));
        $limit = max(1, (int) config('app.events_upcoming_limit'));
        $registrationDate = date('Y-m-d');

        if ($id <= 0) {
            $upcomingEvents = Event::upcomingPublished($registrationDate, $limit);

            return $this->view('events/register', [
                'event' => null,
                'athletes' => [],
                'registered' => [],
                'nextEvents' => [],
                'upcomingEvents' => $upcomingEvents,
                'registrationFeedback' => null,
                'athleteCategories' => [],
            ]);
        }

        $event = Event::findRegistrationEligibleById($id, $registrationDate);
        if ($event === null) {
            $upcomingEvents = Event::upcomingPublished($registrationDate, $limit);

            return $this->view('events/register', [
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
            $athleteIds = $request->input('athletes', []);
            if (!is_array($athleteIds)) {
                $athleteIds = [$athleteIds];
            }
            $feedback = [
                'added' => 0,
                'already_registered' => 0,
                'rejected' => 0,
                'failed' => 0,
            ];

            foreach ($athleteIds as $athleteId) {
                if (!is_numeric($athleteId) || (int) $athleteId <= 0) {
                    $feedback['rejected']++;
                    continue;
                }

                $athleteId = (int) $athleteId;
                try {
                    $result = Entry::register($id, $clubId, $athleteId, $registrationDate);
                    match ($result) {
                        EntryRegistrationResult::Registered => $feedback['added']++,
                        EntryRegistrationResult::AlreadyRegistered => $feedback['already_registered']++,
                        EntryRegistrationResult::AthleteRejected => $feedback['rejected']++,
                    };
                } catch (Throwable $exception) {
                    $feedback['failed']++;
                    $this->reportFailure('event.registration_failed', $exception, $request);
                }
            }

            Session::flash(self::REGISTRATION_FEEDBACK_PREFIX . $id, $feedback);

            return $this->redirect('/event_register.php?id=' . $id);
        }

        $athletes = Athlete::findByClub($clubId);
        $registered = Entry::findByClubEvent($id, $clubId);
        $nextEvents = Event::nextUpcomingPublished($id, $registrationDate, $limit);
        $registrationFeedback = $this->registrationFeedback($id);

        return $this->view('events/register', [
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
        $isAdmin = !empty(Session::get('is_admin'));
        $clubId = Session::get('club_id');

        if (!$isAdmin && (!is_numeric($clubId) || (int) $clubId <= 0)) {
            return $this->redirect('/club_login.php');
        }

        $eventId = (int) ($request->input('event') ?? $request->query('event') ?? $request->query('id'));
        if ($eventId <= 0) {
            return $this->redirect('/events.php');
        }

        $event = Event::findById($eventId);
        if ($event === null) {
            return $this->redirect('/events.php');
        }

        $requestedClubId = (int) ($request->query('club') ?? 0);
        $clubFilter = $isAdmin ? $requestedClubId : (int) $clubId;
        $entryClubId = $isAdmin
            ? ($clubFilter > 0 ? $clubFilter : null)
            : (int) $clubId;
        $clubs = Entry::findClubsByEvent($eventId, $isAdmin ? null : $entryClubId);
        $rows = Entry::findByEvent($eventId, $entryClubId);

        $selectedClub = null;
        foreach ($clubs as $club) {
            if ((int) ($club['id'] ?? 0) === $clubFilter) {
                $selectedClub = $club;
                break;
            }
        }

        $grouped = [];
        foreach ($rows as $row) {
            $birthDate = $row['birth_date'] ?? '';
            $eventDate = $row['data_gara'] ?? '';
            $birthYear = JudoCategory::extractBirthYear($birthDate);
            $eventYear = $eventDate !== '' ? (int) substr($eventDate, 0, 4) : (int) date('Y');
            if ($birthYear !== null) {
                $acResult = AgeClass::calculate($birthYear, $eventYear);
                $category = $acResult['label'];
            } else {
                $category = '';
            }
            $weight = $row['weight_category'] ?? '';

            if ($category === '') {
                $category = __('events.no_category');
            }
            if ($weight === '') {
                $weight = __('events.no_weight');
            }

            $grouped[$category . ' | ' . $weight][] = $row;
        }

        return $this->view('events/entries', [
            'event' => $event,
            'clubs' => $clubs,
            'rows' => $rows,
            'grouped' => $grouped,
            'selectedClub' => $selectedClub,
            'clubFilter' => $clubFilter,
            'isAdmin' => $isAdmin,
        ]);
    }

    private function canViewEntries(): bool
    {
        return !empty(Session::get('is_admin')) || Session::has('club_id');
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

    /** @return array{added: int, already_registered: int, rejected: int, failed: int}|null */
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
            'failed' => max(0, (int) ($feedback['failed'] ?? 0)),
        ];
    }
}
