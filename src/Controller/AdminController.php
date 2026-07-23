<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\AuthContext;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Model\Affiliation;
use App\Model\Club;
use App\Model\Database;
use App\Model\EntrySnapshotService;
use App\Model\Event;
use App\Model\SardinianLocation;
use App\Security\AuthenticationThrottle;
use App\Model\EventRegistrationException;
use App\Security\DatabaseAuthenticationThrottle;
use App\Security\PasswordPolicy;
use App\Service\DatabasePasswordResetRepository;
use App\Service\EventEntriesCsvTransfer;
use App\Service\EventUploadStorage;
use App\Service\PasswordResetRepository;
use App\Validation\ClubInputValidator;
use App\Validation\EventInputValidator;
use PDOException;

final class AdminController extends Controller
{
    private ?AuthenticationThrottle $authenticationThrottle;
    private ?PasswordResetRepository $passwordResetRepository;
    private readonly EventUploadStorage $eventUploadStorage;

    public function __construct(
        View $view,
        Request $request,
        ?AuthenticationThrottle $authenticationThrottle = null,
        ?PasswordResetRepository $passwordResetRepository = null,
        ?Logger $logger = null,
        ?EventUploadStorage $eventUploadStorage = null
    ) {
        parent::__construct($view, $request, $logger);
        $this->authenticationThrottle = $authenticationThrottle;
        $this->passwordResetRepository = $passwordResetRepository;
        $this->eventUploadStorage = $eventUploadStorage ?? new EventUploadStorage();
    }

    public function login(Request $request): Response
    {
        $errors = [];

        if ($request->method() === 'POST') {
            validate_csrf((string) $request->post('csrf_token'));
            $user = (string) $request->input('user');
            $pass = (string) $request->input('pass');

            $adminUser = env('ADMIN_USER');
            $adminHash = env('ADMIN_PASS_HASH');

            if ($user === '' || $pass === '') {
                $errors[] = __('admin.login.errors.credentials_required');
            } elseif ($adminUser === null || $adminHash === null) {
                $errors[] = __('admin.login.errors.not_configured');
            } else {
                $networkSignal = $this->networkSignal($request);
                $throttle = $this->authenticationThrottle();

                if ($throttle->isBlocked('admin-login', $user, $networkSignal)) {
                    $errors[] = __('admin.login.errors.too_many_attempts');
                } elseif ($user === $adminUser && password_verify($pass, $adminHash)) {
                    $throttle->clear('admin-login', $user, $networkSignal);
                    Session::authenticateAdministrator();

                    return $this->redirect('/admin/events');
                } else {
                    $throttle->recordAttempt('admin-login', $user, $networkSignal);
                    $errors[] = __('admin.login.errors.invalid_credentials');
                }
            }
        }

        return $this->view('admin/login', [
            'title' => __('admin.login.title'),
            'errors' => $errors,
        ]);
    }

    private function authenticationThrottle(): AuthenticationThrottle
    {
        return $this->authenticationThrottle ??= new DatabaseAuthenticationThrottle(Database::connection());
    }

    private function networkSignal(Request $request): string
    {
        return trim((string) $request->server('REMOTE_ADDR', 'unknown')) ?: 'unknown';
    }

    public function dashboard(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }

        return $this->redirect('/admin/events');
    }

    public function manageClubs(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }

        $db = \App\Model\Database::connection();

        $total = (int) $db->query('SELECT COUNT(*) FROM clubs')->fetchColumn();
        $page = max(1, (int) ($request->query('page', '1')));
        $pagination = paginate($total, $page, 100);

        $stmt = $db->prepare('SELECT * FROM clubs ORDER BY name LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $pagination['per_page'], \PDO::PARAM_INT);
        $stmt->bindValue(2, $pagination['offset'], \PDO::PARAM_INT);
        $stmt->execute();
        $clubs = array_map(fn(array $row) => Club::fromArray($row), $stmt->fetchAll() ?: []);

        return $this->view('admin/manage_clubs', [
            'title' => __('admin.clubs.title'),
            'clubs' => $clubs,
            'pagination' => $pagination,
        ]);
    }

    public function deleteClub(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }

        validate_csrf((string) $request->post('csrf_token'));
        $clubId = (int) $request->post('club_id');
        if ($clubId > 0) {
            Club::remove($clubId);
        }

        return $this->redirect('/admin/clubs');
    }

    public function manageEvents(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }

        $db = \App\Model\Database::connection();

        $total = (int) $db->query('SELECT COUNT(*) FROM events')->fetchColumn();
        $page = max(1, (int) ($request->query('page', '1')));
        $pagination = paginate($total, $page, 100);

        $stmt = $db->prepare('SELECT * FROM events ORDER BY date DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, $pagination['per_page'], \PDO::PARAM_INT);
        $stmt->bindValue(2, $pagination['offset'], \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $events = [];
        foreach ($rows as $r) {
            $events[] = Event::fromArray($r);
        }

        $eventIds = array_map(static fn(Event $event): int => $event->id, $events);
        $counts = \App\Model\Entry::countsByEventIds($eventIds);

        return $this->view('admin/manage_events', [
            'title' => __('admin.events.title'),
            'events' => $events,
            'entry_counts' => $counts,
            'pagination' => $pagination,
        ]);
    }

    public function deleteEvent(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }

        validate_csrf((string) $request->post('csrf_token'));
        $eventId = (int) $request->post('event_id');
        if ($eventId > 0) {
            $event = Event::findById($eventId);
            Event::remove($eventId);
            if ($event !== null) {
                try {
                    $this->eventUploadStorage->purgeMany([$event->poster_file, $event->info_file]);
                } catch (\Throwable $exception) {
                    $this->reportFailure('admin.event_upload_purge_failed', $exception, $request);
                }
            }
        }

        return $this->redirect('/admin/events');
    }

    public function exportEventEntries(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }

        $eventId = (int) ($request->query('event_id') ?? $request->input('event_id') ?? 0);
        $event = $eventId > 0 ? Event::findById($eventId) : null;
        if ($event === null) {
            return $this->redirect('/admin/events');
        }

        $csv = (new EventEntriesCsvTransfer())->export($event->id);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="event-' . $event->id . '-entries.csv"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function addEvent(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }

        $db = \App\Model\Database::connection();
        $eventId = (int) ($request->input('event_id') ?? $request->input('id') ?? 0);
        $event = null;

        if ($eventId > 0) {
            $stmt = $db->prepare('SELECT * FROM events WHERE id = ?');
            $stmt->execute([$eventId]);
            $row = $stmt->fetch();
            if ($row) {
                $event = \App\Model\Event::fromArray($row);
            }
        }

        $locations = array_unique(array_map(function ($r) {
            return (string) $r['location'];
        }, $db->query('SELECT DISTINCT location FROM events WHERE location != "" ORDER BY location ASC')->fetchAll()));

        // Fetch all clubs for the exceptions dropdown (needed for both GET and POST with errors)
        $stmt = $db->query('SELECT id, name FROM clubs ORDER BY name');
        $clubs = array_map(fn(array $row) => ['id' => (int) $row['id'], 'name' => (string) $row['name']], $stmt->fetchAll() ?: []);

        // Get current exceptions for the event being edited
        $exceptionClubIds = $event !== null ? EventRegistrationException::clubIdsForEvent((int) $event->id) : [];

        $error = '';

        // Validate uploads early for quick failure before any other database work
        $uploads = $this->eventUploads();
        if ($error === '' && $request->method() === 'POST') {
            foreach (['poster_file', 'info_file'] as $field) {
                $uploadError = EventInputValidator::extension($uploads[$field] ?? null);
                if ($uploadError === null && isset($uploads[$field])) {
                    // Check file size
                    $size = filter_var($uploads[$field]['size'] ?? null, FILTER_VALIDATE_INT);
                    if ($size === false || $size < 0 || $size > EventInputValidator::MAX_UPLOAD_BYTES) {
                        $error = __('validation.event_upload_too_large');
                    }
                }
            }
        }

        if ($request->method() === 'POST' && $error === '') {
            validate_csrf((string) $request->post('csrf_token'));
/**
              * @var array{
              *     name: string,
              *     date: string,
              *     location: string,
              *     organizer: string,
              *     registration_deadline: string,
              *     type: string,
              *     description: string,
              *     notes: string,
              *     max_participants: string,
              *     published: int,
              *     closed: int
              * } $data
              */
            $data = [
                'name' => trim((string) $request->post('name')),
                'date' => trim((string) $request->post('date')),
                'location' => trim((string) $request->post('location')),
                'organizer' => trim((string) $request->post('organizer')),
                'registration_deadline' => trim((string) $request->post('registration_deadline')),
                'type' => trim((string) $request->post('type')),
                'description' => trim((string) $request->post('description')),
                'notes' => trim((string) $request->post('notes')),
                'max_participants' => trim((string) $request->post('max_participants')),
                'published' => $request->post('published') === '1' ? 1 : 0,
                'closed' => $request->post('closed') === '1' ? 1 : 0,
            ];
            $uploads = $this->eventUploads();
            $validationErrors = EventInputValidator::errors(
                $data['name'],
                $data['date'],
                $data['location'],
                $data['registration_deadline'],
                $data['type'],
                $uploads,
                $data['max_participants']
            );
            if ($validationErrors !== []) {
                $error = __($validationErrors[0]);
            }

            if ($error === '') {
                $storedUploads = [];
                $persisted = false;
                try {
                    $locandina = $event?->poster_file ?? null;
                    $informativa = $event?->info_file ?? null;

                    if (isset($uploads['poster_file'])) {
                        $locandina = $this->eventUploadStorage->store($uploads['poster_file'], 'poster_');
                        $storedUploads[] = $locandina;
                    }
                    if (isset($uploads['info_file'])) {
                        $informativa = $this->eventUploadStorage->store($uploads['info_file'], 'info_');
                        $storedUploads[] = $informativa;
                    }

                    // Handle registration exceptions (clubs that can register for closed events)
                    $exceptionClubIds = [];
                    $exceptionInput = $request->post('registration_exceptions', []);
                    if (is_array($exceptionInput)) {
                        foreach ($exceptionInput as $clubIdStr) {
                            $clubIdInt = (int) $clubIdStr;
                            if ($clubIdInt > 0) {
                                $exceptionClubIds[] = $clubIdInt;
                            }
                        }
                    }

                    if ($event) {
                        $db->beginTransaction();
                        $sql = "UPDATE events SET name=?, date=?, location=?, organizer=?, registration_deadline=?, type=?, description=?, notes=?, max_participants=?, poster_file=?, info_file=?, published=?, closed=? WHERE id=?";
                        $params = [
                            $data['name'],
                            $data['date'],
                            $data['location'],
                            $data['organizer'],
                            $data['registration_deadline'] ?: null,
                            $data['type'],
                            $data['description'],
                            $data['notes'],
                            $data['max_participants'] !== '' ? (int) $data['max_participants'] : null,
                            $locandina,
                            $informativa,
                            $data['published'],
                            $data['closed'],
                            $eventId,
                        ];
                        $db->prepare($sql)->execute($params);
                        if (!$event->closed && $data['closed'] === 1) {
                            (new EntrySnapshotService($db))->consolidate($eventId, $data['date']);
                        }
                        // Save registration exceptions
                        EventRegistrationException::setForEvent($eventId, $exceptionClubIds);
                        $db->commit();
                        $persisted = true;
                    } else {
                        $db->prepare(
                            "INSERT INTO events (name, date, location, organizer, registration_deadline, type, description, notes, max_participants, poster_file, info_file, published, closed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                        )->execute([
                            $data['name'],
                            $data['date'],
                            $data['location'],
                            $data['organizer'],
                            $data['registration_deadline'] ?: null,
                            $data['type'],
                            $data['description'],
                            $data['notes'],
                            $data['max_participants'] !== '' ? (int) $data['max_participants'] : null,
                            $locandina,
                            $informativa,
                            $data['published'],
                            $data['closed'],
                        ]);
                        $newEventId = (int) $db->lastInsertId();
                        // Save registration exceptions for new event
                        EventRegistrationException::setForEvent($newEventId, $exceptionClubIds);
                        $persisted = true;
                    }

                    if ($event !== null) {
                        $replacedUploads = [];
                        if (isset($uploads['poster_file'])) {
                            $replacedUploads[] = $event->poster_file;
                        }
                        if (isset($uploads['info_file'])) {
                            $replacedUploads[] = $event->info_file;
                        }
                        $this->eventUploadStorage->purgeMany($replacedUploads);
                    }

                    return $this->redirect('/admin/events');
                } catch (\Throwable $exception) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    if (!$persisted) {
                        try {
                            $this->eventUploadStorage->purgeMany($storedUploads);
                        } catch (\Throwable $cleanupException) {
                            $this->reportFailure(
                                'admin.new_event_upload_cleanup_failed',
                                $cleanupException,
                                $request
                            );
                        }
                    }
                    $this->reportFailure('admin.event_save_failed', $exception, $request);
                    $error = __('errors.save_failed');
                }
            }

            return $this->view('admin/add_event', [
                'title' => $event !== null ? __('admin.edit.title') . ' - ' . $event->name : __('admin.add.title'),
                'event' => $event,
                'error' => $error,
                'locations' => $locations,
                'clubs' => $clubs,
                'exceptionClubIds' => $exceptionClubIds,
            ]);
        }

        return $this->view('admin/add_event', [
            'title' => $event !== null ? __('admin.edit.title') . ' - ' . $event->name : __('admin.add.title'),
            'event' => $event,
            'error' => $error,
            'locations' => $locations,
            'clubs' => $clubs,
            'exceptionClubIds' => $exceptionClubIds,
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    private function eventUploads(): array
    {
        $uploads = [];
        foreach (['poster_file', 'info_file'] as $field) {
            if (
                isset($_FILES[$field])
                && is_array($_FILES[$field])
                && (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            ) {
                $uploads[$field] = $_FILES[$field];
            }
        }

        return $uploads;
    }

    public function editClub(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }

        $id = (int) ($request->input('id') ?? $request->query('id'));
        if ($id <= 0) {
            return $this->redirect('/admin/clubs');
        }

        $club = Club::findById($id);
        if (!$club) {
            return $this->redirect('/admin/clubs');
        }

        $error = '';

        if ($request->method() === 'POST') {
            validate_csrf((string) $request->post('csrf_token'));
            try {
                $data = [
                    'name' => trim((string) $request->post('name')),
                    'email' => Club::normalizeEmail((string) $request->post('email')),
                    'phone' => trim((string) $request->post('phone')),
                    'address_line' => trim((string) $request->post('address_line')),
                    'postal_code' => trim((string) $request->post('postal_code')),
                    'city' => trim((string) $request->post('city')),
                    'province' => trim((string) $request->post('province')),
                    'contact_first_name' => trim((string) $request->post('contact_first_name')),
                    'contact_last_name' => trim((string) $request->post('contact_last_name')),
                    'affiliation' => Affiliation::encode(Affiliation::selected($request->post('affiliation'))),
                    'federal_code' => trim((string) $request->post('federal_code')),
                ];

                $password = (string) $request->post('password_hash');
                $validationErrors = ClubInputValidator::errors(
                    $data['name'],
                    $data['federal_code'],
                    $data['email'],
                    $data['phone'],
                    $data['address_line'],
                    $data['postal_code'],
                    $data['province'],
                    $data['city']
                );
                if ($validationErrors !== []) {
                    $error = __($validationErrors[0]);
                } elseif ($password !== '' && !PasswordPolicy::accepts($password)) {
                    $error = __('errors.password_too_short', [
                        'minimum' => (string) PasswordPolicy::MINIMUM_LENGTH,
                    ]);
                } else {
                    Club::update($id, $data);
                    if ($password !== '') {
                        $this->passwordResetRepository()->replacePassword(
                            $id,
                            password_hash($password, PASSWORD_DEFAULT)
                        );
                    }

                    return $this->redirect('/admin/clubs');
                }
            } catch (\Throwable $exception) {
                $this->reportFailure('admin.club_save_failed', $exception, $request);
                $error = $exception instanceof PDOException && (string) $exception->getCode() === '23000'
                    ? __('errors.account_conflict')
                    : __('errors.save_failed');
            }
        }

        return $this->view('admin/edit_club', [
            'title' => __('admin.clubs.edit_title') . ' - ' . $club->name,
            'club' => $club,
            'error' => $error,
            'sardinianLocations' => SardinianLocation::all(),
            'sardinianPostalCodes' => SardinianLocation::postalCodes(),
            'affiliationOptions' => Affiliation::options(),
        ]);
    }

    private function passwordResetRepository(): PasswordResetRepository
    {
        return $this->passwordResetRepository ??= new DatabasePasswordResetRepository(Database::connection());
    }

    public function logout(Request $request): Response
    {
        validate_csrf((string) $request->post('csrf_token'));
        Session::destroy();

        return $this->redirect('/admin/login');
    }

    public function editEvent(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }

        $id = (int) ($request->input('id') ?? $request->query('id'));
        if ($id <= 0) {
            return $this->redirect('/admin/events');
        }

        return $this->redirect('/admin/events/add?event_id=' . $id);
    }
}
