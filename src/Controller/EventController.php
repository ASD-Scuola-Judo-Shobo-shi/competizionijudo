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
use App\Model\Event;
use App\Model\JudoCategory;

final class EventController extends Controller
{
    public function index(Request $request): Response
    {
        $limit = max(1, (int) config('app.events_upcoming_limit'));
        $events = Event::allPublished($limit);

        return $this->view('events/index', [
            'events' => $events,
        ]);
    }

    public function show(Request $request): Response
    {
        $limit = max(1, (int) config('app.events_upcoming_limit'));
        $id = (int) ($request->input('id') ?? $request->query('id') ?? $request->query('event') ?? 0);

        if ($id > 0) {
            $event = Event::findById($id);
            if ($event === null) {
                return $this->redirect('/events.php');
            }

            $nextEvents = Event::nextPublished($id, $limit);

            return $this->view('events/show', [
                'event' => $event,
                'nextEvents' => $nextEvents,
                'upcomingEvents' => [],
            ]);
        }

        $upcomingEvents = Event::allPublished($limit);

        return $this->view('events/show', [
            'event' => null,
            'nextEvents' => [],
            'upcomingEvents' => $upcomingEvents,
        ]);
    }

    public function register(Request $request): Response
    {
        Session::start();
        $clubId = Session::get('club_id');

        if ($clubId === null) {
            return $this->redirect('/club_login.php');
        }

        $id = (int) ($request->input('id') ?? $request->query('id'));
        $limit = max(1, (int) config('app.events_upcoming_limit'));

        if ($id <= 0) {
            $upcomingEvents = Event::allPublished($limit);

            return $this->view('events/register', [
                'event' => null,
                'athletes' => [],
                'registered' => [],
                'nextEvents' => [],
                'upcomingEvents' => $upcomingEvents,
                'warning' => null,
            ]);
        }

        $event = Event::findById($id);
        if ($event === null || !$event->published || $event->closed) {
            $upcomingEvents = Event::allPublished($limit);

            return $this->view('events/register', [
                'event' => null,
                'athletes' => [],
                'registered' => [],
                'nextEvents' => [],
                'upcomingEvents' => $upcomingEvents,
                'warning' => null,
            ]);
        }

        $warning = null;

        if ($request->method() === 'POST') {
            validate_csrf((string) $request->post('csrf_token'));
            $athleteIds = $request->input('athletes', []);
            if (!is_array($athleteIds)) {
                $athleteIds = [$athleteIds];
            }

            foreach ($athleteIds as $athleteId) {
                $athleteId = (int) $athleteId;
                if ($athleteId > 0) {
                    try {
                        Entry::register($id, $clubId, $athleteId);
                    } catch (\RuntimeException $e) {
                        if ($e->getMessage() === 'ALREADY_REGISTERED') {
                            $warning = __('events.already_registered');
                        } else {
                            throw $e;
                        }
                    }
                }
            }

            return $this->redirect('/event_register.php?id=' . $id);
        }

        $athletes = Athlete::findByClub($clubId);
        $registered = Entry::findByClubEvent($id, $clubId);
        $nextEvents = Event::nextPublished($id, $limit);

        return $this->view('events/register', [
            'event' => $event,
            'athletes' => $athletes,
            'registered' => $registered,
            'nextEvents' => $nextEvents,
            'upcomingEvents' => [],
            'warning' => $warning,
        ]);
    }

    public function entries(Request $request): Response
    {
        Session::start();
        $isAdmin = !empty(Session::get('is_admin'));
        $clubId = Session::get('club_id');

        if (!$isAdmin && $clubId === null) {
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

        $clubFilter = (int) ($request->query('club') ?? 0);
        if (!$isAdmin && $clubFilter !== 0) {
            $clubFilter = $clubId ?? 0;
        }
        $clubs = Entry::findClubsByEvent($eventId);
        $rows = Entry::findByEvent($eventId, $clubFilter);

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
}
