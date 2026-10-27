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
use App\Model\Athlete;
use App\Model\Club;
use App\Model\Database;
use App\Model\Entry;
use App\Model\EntrySnapshotService;
use App\Model\Event;
use App\Model\SardinianLocation;
use App\Security\AuthenticationThrottle;
use App\Security\CredentialFingerprint;
use App\Security\DatabaseAuthenticationThrottle;
use App\Security\PasswordPolicy;
use App\Security\PasswordVerifier;
use App\Service\AthleteCsvTransfer;
use App\Service\DatabasePasswordResetRepository;
use App\Service\EventEntriesCsvTransfer;
use App\Service\EventUploadStorage;
use App\Service\EventArchiver;
use App\Service\PasswordResetRepository;
use App\Validation\ClubInputValidator;
use App\Validation\EventInputValidator;
use PDOException;

final class AdminController extends Controller
{
    private const CLUB_INLINE_FEEDBACK = 'admin_club_inline_feedback';
    private const EVENT_INLINE_FEEDBACK = 'admin_event_inline_feedback';

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

                if (!$throttle->consume('admin-login', $user, $networkSignal)) {
                    $errors[] = __('admin.login.errors.too_many_attempts');
                } elseif (
                    PasswordVerifier::matches($pass, (string) $adminHash)
                    && hash_equals((string) $adminUser, $user)
                ) {
                    $throttle->clear('admin-login', $user, $networkSignal);
                    Session::authenticateAdministrator(CredentialFingerprint::forAdministrator(
                        (string) $adminUser,
                        (string) $adminHash
                    ));

                    return $this->redirect('/admin/events');
                } else {
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

        $total = Club::count();
        $page = max(1, (int) ($request->query('page', '1')));
        $pagination = paginate($total, $page, 100, 'page', $request->queryParameters());

        $sort = resolve_table_sort(
            $request->query('sort'),
            $request->query('direction'),
            ['name', 'federal_code', 'email', 'phone', 'contact', 'address', 'affiliation', 'athletes'],
            'name'
        );
        $clubs = Club::adminPage(
            $pagination['per_page'],
            $pagination['offset'],
            $sort['column'],
            $sort['direction']
        );
        $clubIds = array_map(static fn(Club $club): int => $club->id, $clubs);

        return $this->view('admin/manage_clubs', [
            'title' => __('admin.clubs.title'),
            'clubs' => $clubs,
            'athlete_counts' => Athlete::countsByClubIds($clubIds),
            'pagination' => $pagination,
            'tableSort' => $sort,
            'inlineFeedback' => Session::pullFlash(self::CLUB_INLINE_FEEDBACK),
        ]);
    }

    public function updateClubInline(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }

        validate_csrf((string) $request->post('csrf_token'));
        $clubId = (int) $request->post('club_id');
        $page = max(1, (int) $request->post('page', 1));
        $redirect = '/admin/clubs?page=' . $page;
        if ($request->post('sort') !== null || $request->post('direction') !== null) {
            $sort = resolve_table_sort(
                $request->post('sort'),
                $request->post('direction'),
                ['name', 'federal_code', 'email', 'phone', 'contact', 'address', 'affiliation', 'athletes'],
                'name'
            );
            $redirect .= '&sort=' . rawurlencode($sort['column'])
                . '&direction=' . rawurlencode($sort['direction']);
        }
        $redirect .= '#club-row-' . $clubId;
        $club = $clubId > 0 ? Club::findById($clubId) : null;

        if ($club === null) {
            Session::flash(self::CLUB_INLINE_FEEDBACK, [
                'type' => 'error',
                'message' => __('tables.update_failed'),
            ]);

            return $this->redirect($redirect, 303);
        }

        $data = [
            'name' => trim((string) $request->post('name')),
            'federal_code' => trim((string) $request->post('federal_code')),
            'email' => Club::normalizeEmail((string) $request->post('email')),
            'phone' => trim((string) $request->post('phone')),
            'contact_first_name' => trim((string) $request->post('contact_first_name')),
            'contact_last_name' => trim((string) $request->post('contact_last_name')),
        ];
        $errors = ClubInputValidator::summaryErrors(
            $data['name'],
            $data['federal_code'],
            $data['email'],
            $data['phone']
        );

        if ($errors !== []) {
            Session::flash(self::CLUB_INLINE_FEEDBACK, [
                'type' => 'error',
                'message' => __($errors[0]),
            ]);

            return $this->redirect($redirect, 303);
        }

        try {
            Club::update($club->id, $data);
            Session::flash(self::CLUB_INLINE_FEEDBACK, [
                'type' => 'success',
                'message' => __('tables.update_success'),
            ]);
        } catch (\Throwable $exception) {
            $this->reportFailure('admin.club_inline_save_failed', $exception, $request);
            Session::flash(self::CLUB_INLINE_FEEDBACK, [
                'type' => 'error',
                'message' => $exception instanceof PDOException && (string) $exception->getCode() === '23000'
                    ? __('errors.account_conflict')
                    : __('tables.update_failed'),
            ]);
        }

        return $this->redirect($redirect, 303);
    }

    public function approveClub(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }

        validate_csrf((string) $request->post('csrf_token'));
        $clubId = (int) $request->post('club_id');
        $page = max(1, (int) $request->post('page', 1));
        $redirect = '/admin/clubs?page=' . $page;
        if ($request->post('sort') !== null || $request->post('direction') !== null) {
            $sort = resolve_table_sort(
                $request->post('sort'),
                $request->post('direction'),
                ['name', 'federal_code', 'email', 'phone', 'contact', 'address', 'affiliation', 'athletes'],
                'name'
            );
            $redirect .= '&sort=' . rawurlencode($sort['column'])
                . '&direction=' . rawurlencode($sort['direction']);
        }
        $redirect .= '#club-row-' . $clubId;
        $club = $clubId > 0 ? Club::findById($clubId) : null;

        if ($club === null) {
            Session::flash(self::CLUB_INLINE_FEEDBACK, [
                'type' => 'error',
                'message' => __('tables.update_failed'),
            ]);

            return $this->redirect($redirect, 303);
        }

        try {
            Club::approve($club->id);
            Session::flash(self::CLUB_INLINE_FEEDBACK, [
                'type' => 'success',
                'message' => __('admin.clubs.approval_success'),
            ]);
        } catch (\Throwable $exception) {
            $this->reportFailure('admin.club_approval_failed', $exception, $request);
            Session::flash(self::CLUB_INLINE_FEEDBACK, [
                'type' => 'error',
                'message' => __('tables.update_failed'),
            ]);
        }

        return $this->redirect($redirect, 303);
    }

    public function clubAthletes(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }

        $clubId = (int) ($request->query('club_id') ?? 0);
        $club = $clubId > 0 ? Club::findById($clubId) : null;
        if ($club === null) {
            return $this->redirect('/admin/clubs');
        }

        $page = max(1, (int) $request->query('page', '1'));
        $pagination = paginate(
            Athlete::countByClub($club->id),
            $page,
            50,
            'page',
            $request->queryParameters()
        );
        $sort = resolve_table_sort(
            $request->query('sort'),
            $request->query('direction'),
            [
                'athlete',
                'gender',
                'birth',
                'age_class',
                'weight',
                'belt',
                'weight_category',
                'membership_number',
                'notes',
            ],
            'athlete'
        );
        $athletes = Athlete::pageByClub(
            $club->id,
            $pagination['per_page'],
            $pagination['offset'],
            $sort['column'],
            $sort['direction']
        );

        return $this->view('admin/club_athletes', [
            'title' => __('admin.clubs.athletes_title', ['club' => $club->name]),
            'club' => $club,
            'athletes' => $athletes,
            'pagination' => $pagination,
            'athleteFeedback' => Session::pullFlash(AdminAthleteController::feedbackKey($club->id)),
            'athleteCsvFeedback' => Session::pullFlash(AdminAthleteController::importFeedbackKey($club->id)),
            'csvExportUrl' => '/admin/clubs/athletes/export?club_id=' . $club->id,
            'csvImportUrl' => '/admin/clubs/athletes/import?club_id=' . $club->id,
            'csvEditUrl' => '/admin/clubs/athletes/edit?club_id=' . $club->id . '&athlete_id=',
        ]);
    }

    public function exportClubAthletes(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }

        $clubId = (int) ($request->query('club_id') ?? 0);
        $club = $clubId > 0 ? Club::findById($clubId) : null;
        if ($club === null) {
            return $this->redirect('/admin/clubs');
        }

        $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $club->name));
        $slug = trim(substr($slug, 0, 40), '-');
        if ($slug === '') {
            $slug = 'club-' . $club->id;
        }
        $filename = sprintf('athletes-%s-%s.csv', $slug, date('Y-m-d'));
        $csv = (new AthleteCsvTransfer())->export($club->id);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
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

        $showArchived = $request->query('archived') === '1';
        $total = Event::count($showArchived);
        $page = max(1, (int) ($request->query('page', '1')));
        $pagination = paginate($total, $page, 100, 'page', $request->queryParameters());

        $sort = resolve_table_sort(
            $request->query('sort'),
            $request->query('direction'),
            [
                'name',
                'date',
                'location',
                'type',
                'visibility',
                'registration_status',
                'clubs',
                'athletes',
                'max_participants',
            ],
            'date',
            'desc'
        );
        $events = Event::adminPage(
            $pagination['per_page'],
            $pagination['offset'],
            $sort['column'],
            $sort['direction'],
            $showArchived
        );

        $eventIds = array_map(static fn(Event $event): int => $event->id, $events);
        $counts = \App\Model\Entry::countsByEventIds($eventIds);

        return $this->view('admin/manage_events', [
            'title' => __('admin.events.title'),
            'showArchived' => $showArchived,
            'archiveListPath' => '/admin/events',
            'events' => $events,
            'entry_counts' => $counts,
            'pagination' => $pagination,
            'tableSort' => $sort,
            'inlineFeedback' => Session::pullFlash(self::EVENT_INLINE_FEEDBACK),
        ]);
    }

    public function updateEventInline(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }

        validate_csrf((string) $request->post('csrf_token'));
        $eventId = (int) $request->post('event_id');
        $page = max(1, (int) $request->post('page', 1));
        $redirect = '/admin/events?page=' . $page;
        if ($request->post('sort') !== null || $request->post('direction') !== null) {
            $sort = resolve_table_sort(
                $request->post('sort'),
                $request->post('direction'),
                [
                    'name',
                    'date',
                    'location',
                    'type',
                    'visibility',
                    'registration_status',
                    'clubs',
                    'athletes',
                    'max_participants',
                ],
                'date',
                'desc'
            );
            $redirect .= '&sort=' . rawurlencode($sort['column'])
                . '&direction=' . rawurlencode($sort['direction']);
        }
        $redirect .= '#event-row-' . $eventId;
        $event = $eventId > 0 ? Event::findById($eventId) : null;

        if ($event === null || $event->archived_at !== null) {
            Session::flash(self::EVENT_INLINE_FEEDBACK, [
                'type' => 'error',
                'message' => __('tables.update_failed'),
            ]);

            return $this->redirect($redirect, 303);
        }

        $data = [
            'name' => trim((string) $request->post('name')),
            'date' => trim((string) $request->post('date')),
            'location' => trim((string) $request->post('location')),
            'type' => trim((string) $request->post('type')),
            'max_participants' => trim((string) $request->post('max_participants')),
            'published' => $request->post('published') === '1' ? 1 : 0,
            'closed' => $request->post('closed') === '1' ? 1 : 0,
        ];
        $errors = EventInputValidator::errors(
            $data['name'],
            $data['date'],
            $data['location'],
            $event->registration_deadline,
            $data['type'],
            [],
            $data['max_participants']
        );

        if ($errors !== []) {
            Session::flash(self::EVENT_INLINE_FEEDBACK, [
                'type' => 'error',
                'message' => __($errors[0]),
            ]);

            return $this->redirect($redirect, 303);
        }

        $db = Database::connection();
        try {
            $db->beginTransaction();
            $lock = $db->prepare(
                'SELECT closed FROM events WHERE id = ? AND archived_at IS NULL'
                . ($db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '')
            );
            $lock->execute([$event->id]);
            $current = $lock->fetch(\PDO::FETCH_ASSOC);
            if ($current === false) {
                throw new \RuntimeException('Event is no longer editable.');
            }
            $db->prepare(
                'UPDATE events
                 SET name = ?, date = ?, location = ?, type = ?, max_participants = ?, published = ?, closed = ?
                 WHERE id = ? AND archived_at IS NULL'
            )->execute([
                $data['name'],
                $data['date'],
                $data['location'],
                $data['type'],
                $data['max_participants'] !== '' ? (int) $data['max_participants'] : null,
                $data['published'],
                $data['closed'],
                $event->id,
            ]);
            if (empty($current['closed']) && $data['closed'] === 1) {
                (new EntrySnapshotService($db))->consolidate($event->id, $data['date']);
            }
            $db->commit();
            Session::flash(self::EVENT_INLINE_FEEDBACK, [
                'type' => 'success',
                'message' => __('tables.update_success'),
            ]);
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->reportFailure('admin.event_inline_save_failed', $exception, $request);
            Session::flash(self::EVENT_INLINE_FEEDBACK, [
                'type' => 'error',
                'message' => __('tables.update_failed'),
            ]);
        }

        return $this->redirect($redirect, 303);
    }

    public function archiveEvent(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }
        validate_csrf((string) $request->post('csrf_token'));
        try {
            (new EventArchiver(Database::connection()))->archive((int) $request->post('event_id'));
        } catch (\Throwable $exception) {
            $this->reportFailure('admin.event_archive_failed', $exception, $request);
            Session::flash(self::EVENT_INLINE_FEEDBACK, ['type' => 'error', 'message' => __('event_archive.failed')]);
        }
        return $this->redirect('/admin/events');
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

        $date = str_replace('-', '', $event->date);
        $name_or_id = $event->name;
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $name_or_id));
        $truncatedSlug = trim(substr($slug, 0, 16), '-');
        $filename = sprintf('event-entries-%s-%s.csv', $date, $truncatedSlug);

        $csv = (new EventEntriesCsvTransfer())->export($event->id, $event->closed, eventDate: $event->date);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function eventDetails(Request $request): Response
    {
        return (new EventManagementController(
            $this->view,
            $request,
            $this->logger,
            $this->eventUploadStorage
        ))->adminDetails($request);
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
}
