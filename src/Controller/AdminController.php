<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Model\Club;
use App\Model\Database;
use App\Model\Event;
use App\Security\AuthenticationThrottle;
use App\Security\DatabaseAuthenticationThrottle;
use App\Security\PasswordPolicy;
use App\Service\DatabasePasswordResetRepository;
use App\Service\PasswordResetRepository;
use App\Validation\ClubInputValidator;
use App\Validation\EventInputValidator;
use PDOException;
use RuntimeException;

final class AdminController extends Controller
{
    private ?AuthenticationThrottle $authenticationThrottle;
    private ?PasswordResetRepository $passwordResetRepository;

    public function __construct(
        View $view,
        Request $request,
        ?AuthenticationThrottle $authenticationThrottle = null,
        ?PasswordResetRepository $passwordResetRepository = null,
        ?Logger $logger = null
    ) {
        parent::__construct($view, $request, $logger);
        $this->authenticationThrottle = $authenticationThrottle;
        $this->passwordResetRepository = $passwordResetRepository;
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
                    Session::regenerate();
                    Session::set('is_admin', true);
                    Session::set('csrf_token', bin2hex(random_bytes(32)));

                    return $this->redirect('/admin_manage_events.php');
                } else {
                    $throttle->recordAttempt('admin-login', $user, $networkSignal);
                    $errors[] = __('admin.login.errors.invalid_credentials');
                }
            }
        }

        return $this->view('admin/login', [
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
        if (empty(Session::get('is_admin'))) {
            return $this->redirect('/admin_login.php');
        }

        return $this->redirect('/admin_manage_events.php');
    }

    public function manageClubs(Request $request): Response
    {
        Session::start();
        if (empty(Session::get('is_admin'))) {
            return $this->redirect('/admin_login.php');
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
            'clubs' => $clubs,
            'pagination' => $pagination,
        ]);
    }

    public function deleteClub(Request $request): Response
    {
        Session::start();
        if (empty(Session::get('is_admin'))) {
            return $this->redirect('/admin_login.php');
        }

        validate_csrf((string) $request->post('csrf_token'));
        $clubId = (int) $request->post('club_id');
        if ($clubId > 0) {
            Club::remove($clubId);
        }

        return $this->redirect('/admin_manage_clubs.php');
    }

    public function manageEvents(Request $request): Response
    {
        Session::start();
        if (empty(Session::get('is_admin'))) {
            return $this->redirect('/admin_login.php');
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

        $countsStmt = $db->prepare(
            'SELECT en.event_id, COUNT(DISTINCT en.club_id) AS clubs, COUNT(en.athlete_id) AS athletes
             FROM entries en
             GROUP BY en.event_id'
        );
        $countsStmt->execute();
        $counts = [];
        foreach ($countsStmt->fetchAll() as $row) {
            $counts[(int) $row['event_id']] = [
                'clubs' => (int) $row['clubs'],
                'athletes' => (int) $row['athletes'],
            ];
        }

        return $this->view('admin/manage_events', [
            'events' => $events,
            'entry_counts' => $counts,
            'pagination' => $pagination,
        ]);
    }

    public function deleteEvent(Request $request): Response
    {
        Session::start();
        if (empty(Session::get('is_admin'))) {
            return $this->redirect('/admin_login.php');
        }

        validate_csrf((string) $request->post('csrf_token'));
        $eventId = (int) $request->post('event_id');
        if ($eventId > 0) {
            Event::remove($eventId);
        }

        return $this->redirect('/admin_manage_events.php');
    }

    public function addEvent(Request $request): Response
    {
        Session::start();
        if (empty(Session::get('is_admin'))) {
            return $this->redirect('/admin_login.php');
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

        $error = '';

        if ($request->method() === 'POST') {
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
                $uploads
            );
            if ($validationErrors !== []) {
                $error = __($validationErrors[0]);
            }

            if ($error === '') {
                try {
                    $locandina = $event?->poster_file ?? null;
                    $informativa = $event?->info_file ?? null;

                    if (isset($uploads['poster_file'])) {
                        $locandina = $this->storeEventUpload($uploads['poster_file'], 'poster_');
                    }
                    if (isset($uploads['info_file'])) {
                        $informativa = $this->storeEventUpload($uploads['info_file'], 'info_');
                    }

                    if ($event) {
                        $sql = "UPDATE events SET name=?, date=?, location=?, organizer=?, registration_deadline=?, type=?, description=?, notes=?, poster_file=?, info_file=?, published=?, closed=? WHERE id=?";
                        $params = [
                            $data['name'],
                            $data['date'],
                            $data['location'],
                            $data['organizer'],
                            $data['registration_deadline'] ?: null,
                            $data['type'],
                            $data['description'],
                            $data['notes'],
                            $locandina,
                            $informativa,
                            $data['published'],
                            $data['closed'],
                            $eventId,
                        ];
                        $db->prepare($sql)->execute($params);
                    } else {
                        $db->prepare(
                            "INSERT INTO events (name, date, location, organizer, registration_deadline, type, description, notes, poster_file, info_file, published, closed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                        )->execute([
                            $data['name'],
                            $data['date'],
                            $data['location'],
                            $data['organizer'],
                            $data['registration_deadline'] ?: null,
                            $data['type'],
                            $data['description'],
                            $data['notes'],
                            $locandina,
                            $informativa,
                            $data['published'],
                            $data['closed'],
                        ]);
                    }

                    return $this->redirect('/admin_manage_events.php');
                } catch (\Throwable $exception) {
                    $this->reportFailure('admin.event_save_failed', $exception, $request);
                    $error = __('errors.save_failed');
                }
            }

            return $this->view('admin/add_event', [
                'event' => $event,
                'error' => $error,
                'locations' => $locations,
            ]);
        }

        return $this->view('admin/add_event', [
            'event' => $event,
            'error' => $error,
            'locations' => $locations,
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

    /** @param array<string, mixed> $upload */
    private function storeEventUpload(array $upload, string $prefix): string
    {
        $extension = EventInputValidator::extension($upload);
        if ($extension === null) {
            throw new RuntimeException('Validated event upload has no supported extension.');
        }

        $uploadDir = base_path('public/uploads/events/');
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Unable to create event upload directory.');
        }

        $filename = $prefix . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        if (!move_uploaded_file((string) $upload['tmp_name'], $uploadDir . $filename)) {
            throw new RuntimeException('Unable to store event upload.');
        }

        return 'uploads/events/' . $filename;
    }

    public function editClub(Request $request): Response
    {
        Session::start();
        if (empty(Session::get('is_admin'))) {
            return $this->redirect('/admin_login.php');
        }

        $id = (int) ($request->input('id') ?? $request->query('id'));
        if ($id <= 0) {
            return $this->redirect('/admin_manage_clubs.php');
        }

        $club = Club::findById($id);
        if (!$club) {
            return $this->redirect('/admin_manage_clubs.php');
        }

        $error = '';

        if ($request->method() === 'POST') {
            validate_csrf((string) $request->post('csrf_token'));
            try {
                $data = [
                    'name' => trim((string) $request->post('name')),
                    'email' => Club::normalizeEmail((string) $request->post('email')),
                    'phone' => trim((string) $request->post('phone')),
                    'contact_first_name' => trim((string) $request->post('contact_first_name')),
                    'contact_last_name' => trim((string) $request->post('contact_last_name')),
                    'contact_phone' => trim((string) $request->post('contact_phone')),
                    'contact_email' => trim((string) $request->post('contact_email')),
                    'organization' => trim((string) $request->post('organization')),
                    'recovery_email' => trim((string) $request->post('recovery_email')),
                    'federal_code' => trim((string) $request->post('federal_code')),
                ];

                $password = (string) $request->post('password_hash');
                $validationErrors = ClubInputValidator::errors(
                    $data['name'],
                    $data['federal_code'],
                    $data['email'],
                    $data['contact_email'],
                    $data['recovery_email']
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

                    return $this->redirect('/admin_manage_clubs.php');
                }
            } catch (\Throwable $exception) {
                $this->reportFailure('admin.club_save_failed', $exception, $request);
                $error = $exception instanceof PDOException && (string) $exception->getCode() === '23000'
                    ? __('errors.account_conflict')
                    : __('errors.save_failed');
            }
        }

        return $this->view('admin/edit_club', [
            'club' => $club,
            'error' => $error,
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

        return $this->redirect('/admin_login.php');
    }

    public function editEvent(Request $request): Response
    {
        Session::start();
        if (empty(Session::get('is_admin'))) {
            return $this->redirect('/admin_login.php');
        }

        $id = (int) ($request->input('id') ?? $request->query('id'));
        if ($id <= 0) {
            return $this->redirect('/admin_manage_events.php');
        }

        return $this->redirect('/admin_add_event.php?event_id=' . $id);
    }
}
