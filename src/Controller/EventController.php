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
use App\Model\Athlete;
use App\Model\Club;
use App\Model\ClubDataRightsDeclaration;
use App\Model\ClubTermsAcceptance;
use App\Model\Entry;
use App\Model\EntryRegistrationResult;
use App\Model\Event;
use App\Model\EventRegistrationException;
use App\Model\EventRegistrationOption;
use App\Presentation\EventEntriesRoster;
use App\Presentation\EventEntriesViewModel;
use App\Service\EventEntriesCsvTransfer;
use App\Service\EventRegistrationSelection;
use App\Service\EventUploadStorage;
use App\Service\ClubQuota;
use App\Service\PasswordResetMailer;
use App\Service\PasswordResetMailerFactory;
use App\Service\RegistrationPaymentService;
use App\Service\RegistrationRecapFormatter;
use Throwable;

final class EventController extends Controller
{
    private const REGISTRATION_FEEDBACK_PREFIX = 'event_registration_';
    private const REGISTRATION_ATHLETES_PER_PAGE = 50;
    private const REGISTRATION_SORT_COLUMNS = ['athlete', 'birth', 'weight', 'weight_category', 'current_option'];
    private const ENTRIES_PER_PAGE = 50;
    private const ENTRY_FILTER_MAX_LENGTH = 100;
    private const ENTRY_CLUB_FILTER_PARAMETERS = [
        'club' => 'clubs_filter_club',
        'federal_code' => 'clubs_filter_federal_code',
        'athletes' => 'clubs_filter_athletes',
        'breakdown' => 'clubs_filter_breakdown',
    ];
    private const ENTRY_ATHLETE_FILTER_PARAMETERS = [
        'age_class' => 'athletes_filter_age_class',
        'weight_category' => 'athletes_filter_weight_category',
        'athlete' => 'athletes_filter_athlete',
        'club' => 'athletes_filter_club',
        'gender' => 'athletes_filter_gender',
        'belt' => 'athletes_filter_belt',
    ];
    private const CURRENT_CLUB_FILTER_PARAMETERS = [
        'athlete' => 'club_entries_filter_athlete',
        'gender' => 'club_entries_filter_gender',
        'weight' => 'club_entries_filter_weight',
        'weight_category' => 'weight_category',
        'belt' => 'club_entries_filter_belt',
    ];
    private const ENTRY_OPPONENT_FILTER_PARAMETERS = [
        'age_class' => 'opponents_filter_age_class',
        'weight_category' => 'opponents_filter_weight_category',
        'athlete' => 'opponents_filter_athlete',
        'club' => 'opponents_filter_club',
        'gender' => 'opponents_filter_gender',
        'belt' => 'opponents_filter_belt',
    ];
    private readonly EventUploadStorage $eventUploadStorage;

    public function __construct(
        View $view,
        Request $request,
        ?Logger $logger = null,
        private ?PasswordResetMailer $registrationMailer = null,
        ?EventUploadStorage $eventUploadStorage = null
    ) {
        parent::__construct($view, $request, $logger);
        $this->eventUploadStorage = $eventUploadStorage ?? new EventUploadStorage();
    }

    public function index(Request $request): Response
    {
        $limit = max(1, (int) config('app.events_upcoming_limit'));
        $events = Event::allPublishedIncludingClosed(date('Y-m-d'), $limit);

        $loggedInClubId = AuthContext::clubId();

        return $this->view('events/index', [
            'title' => __('nav.events'),
            'events' => $events,
            'canViewEntries' => $this->canViewEntries(),
            'canViewPoster' => $loggedInClubId !== null,
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
                'canViewPoster' => $clubId !== null,
                'hasRegistrationException' => $hasRegistrationException,
                'registrationOptions' => $event->registrationOptions(),
            ]);
        }

        $upcomingEvents = $this->resolveUpcomingEvents(null, $date, $limit);

        return $this->view('events/details', [
            'title' => __('nav.events'),
            'event' => null,
            'upcomingEvents' => $upcomingEvents,
            'eventExceptions' => $this->resolveEventExceptions($upcomingEvents),
            'canViewEntries' => $this->canViewEntries(),
            'canViewPoster' => $clubId !== null,
            'hasRegistrationException' => false,
            'registrationOptions' => [],
        ]);
    }

    public function poster(Request $request): Response
    {
        Session::start();
        $isAdministrator = AuthContext::isAdministrator();
        if (AuthContext::clubId() === null && !$isAdministrator) {
            return $this->redirect(
                str_starts_with($request->path(), '/admin/') ? '/admin/login' : '/clubs/login'
            );
        }

        $eventId = (int) ($request->query('event') ?? $request->input('event') ?? 0);
        $event = $eventId > 0
            ? ($isAdministrator ? Event::findById($eventId) : Event::findPublishedById($eventId))
            : null;
        if ($event === null && !$isAdministrator && $eventId > 0) {
            $event = Event::findOwnedById($eventId, (int) AuthContext::clubId());
        }
        $fallback = $isAdministrator ? '/admin/events' : '/events';
        if ($event === null || $event->poster_file === null) {
            return $this->redirect($fallback);
        }

        try {
            $poster = $this->eventUploadStorage->read($event->poster_file);
        } catch (Throwable $exception) {
            $this->reportFailure('event.poster_read_failed', $exception, $request);

            return $this->redirect($fallback);
        }

        if ($poster === null) {
            return $this->redirect($fallback);
        }

        $disposition = $poster['mime_type'] === 'application/pdf' ? 'attachment' : 'inline';

        return new Response($poster['contents'], 200, [
            'Content-Type' => $poster['mime_type'],
            'Content-Disposition' => $disposition . '; filename="' . $poster['filename'] . '"',
            'Content-Length' => (string) strlen($poster['contents']),
            'Cache-Control' => 'private, no-store, max-age=0',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'X-Content-Type-Options' => 'nosniff',
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

        if ($request->method() === 'POST') {
            validate_csrf((string) $request->post('csrf_token'));
            if (
                !ClubTermsAcceptance::hasCurrentVersion($clubId)
                || !ClubDataRightsDeclaration::hasCurrentVersion($clubId)
            ) {
                return $this->redirect('/clubs/agreements', 303);
            }
        }

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
                'registeredEnrollmentDetails' => [],
                'registrationOptions' => [],
                'defaultRegistrationOptionId' => null,
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
                'registeredEnrollmentDetails' => [],
                'registrationOptions' => [],
                'defaultRegistrationOptionId' => null,
            ]);
        }

        $athletePage = max(1, (int) $request->query('athletes_page', '1'));
        $athletePagination = paginate(
            Athlete::countByClub($clubId),
            $athletePage,
            self::REGISTRATION_ATHLETES_PER_PAGE,
            'athletes_page',
            $request->queryParameters(),
            __('events.register_select')
        );
        $registrationSort = resolve_table_sort(
            $request->query('registration_sort'),
            $request->query('registration_direction'),
            self::REGISTRATION_SORT_COLUMNS,
            'athlete'
        );
        $preserveRegistrationSort = $request->query('registration_sort') !== null
            || $request->query('registration_direction') !== null;
        $athletes = Athlete::pageByClub(
            $clubId,
            $athletePagination['per_page'],
            $athletePagination['offset'],
            $registrationSort['column'],
            $registrationSort['direction'],
            $eventId
        );
        $editableAthleteIds = array_map(
            static fn(Athlete $athlete): int => $athlete->id,
            $athletes
        );
        $registrationRedirect = '/events/register?event=' . $eventId;
        if ($athletePagination['page'] > 1) {
            $registrationRedirect .= '&athletes_page=' . $athletePagination['page'];
        }
        if ($preserveRegistrationSort) {
            $registrationRedirect .= '&registration_sort=' . rawurlencode($registrationSort['column'])
                . '&registration_direction=' . rawurlencode($registrationSort['direction']);
        }

        $selection = new EventRegistrationSelection($clubId, $eventId);
        if ($request->method() === 'POST') {
            $selectedOptionId = (int) ($request->post('registration_option_id') ?? 0);
            $selectedOption = EventRegistrationOption::activeForEventById(
                $eventId,
                $selectedOptionId
            );

            // Keep page navigation as a draft; enrollment only happens on Save changes.
            $currentEnrollmentDetails = Entry::enrollmentDetailsByClubEvent($eventId, $clubId);
            $currentlyRegistered = array_keys($currentEnrollmentDetails);
            $rejectedCount = $selection->updatePage(
                $editableAthleteIds,
                $request->post('athletes', []),
                $currentlyRegistered,
                $selectedOption?->id
            );
            if ($request->post('registration_action') === 'navigate') {
                $nextSort = resolve_table_sort(
                    $request->post('next_sort'),
                    $request->post('next_direction'),
                    self::REGISTRATION_SORT_COLUMNS,
                    'athlete'
                );
                $nextPage = max(1, (int) $request->post('next_page', 1));

                return $this->redirect('/events/register?' . http_build_query([
                    'event' => $eventId,
                    'athletes_page' => $nextPage,
                    'registration_sort' => $nextSort['column'],
                    'registration_direction' => $nextSort['direction'],
                ]), 303);
            }

            if ($selectedOption === null) {
                $hasRegistrationOptions = $event->registrationOptions() !== [];
                Session::flash(self::REGISTRATION_FEEDBACK_PREFIX . $eventId, [
                    'added' => 0,
                    'already_registered' => 0,
                    'removed' => 0,
                    'rejected' => 0,
                    'missing_weight' => 0,
                    'capacity_exceeded' => 0,
                    'failed' => 0,
                    'option_required_error' => $hasRegistrationOptions,
                    'option_configuration_error' => !$hasRegistrationOptions,
                    'payment_summary' => null,
                ]);

                return $this->redirect($registrationRedirect);
            }

            $validAthleteIds = $selection->selectedAthleteIds($currentlyRegistered);

            // Apply changes from every visited page, preserving untouched registrations.
            $currentlyRegisteredOnPage = array_values(array_intersect(
                $currentlyRegistered,
                $editableAthleteIds
            ));
            $toRegister = array_values(array_diff($validAthleteIds, $currentlyRegistered));
            $toUnregister = array_values(array_diff($currentlyRegistered, $validAthleteIds));

            $feedback = [
                'added' => 0,
                'removed' => 0,
                'rejected' => $rejectedCount,
                'missing_weight' => 0,
                'already_registered' => 0,
                'capacity_exceeded' => 0,
                'quota_exceeded' => 0,
                'failed' => 0,
            ];

            $newlyRegisteredAthleteIds = [];
            $removedEnrollments = [];

            // Handle unregistrations first (removals take priority)
            foreach ($toUnregister as $athleteId) {
                try {
                    $result = Entry::unregister($eventId, $clubId, $athleteId, $registrationDate);
                    if ($result === EntryRegistrationResult::Unsubscribed) {
                        $feedback['removed']++;
                        $removedEnrollment = $currentEnrollmentDetails[$athleteId];
                        $removedEnrollments[] = [
                            'athlete_name' => $removedEnrollment['athlete_name'],
                            'option_name' => $removedEnrollment['option_name'],
                            'fee_cents' => $removedEnrollment['fee_cents'],
                        ];
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
                    $result = Entry::register(
                        $eventId,
                        $clubId,
                        $athleteId,
                        $selectedOption->id,
                        $registrationDate
                    );
                    match ($result) {
                        EntryRegistrationResult::Registered => $feedback['added']++,
                        EntryRegistrationResult::AlreadyRegistered => $feedback['already_registered']++,
                        EntryRegistrationResult::AthleteRejected => $feedback['rejected']++,
                        EntryRegistrationResult::AthleteWeightMissing => $feedback['missing_weight']++,
                        EntryRegistrationResult::CapacityExceeded => $feedback['capacity_exceeded']++,
                        EntryRegistrationResult::QuotaExceeded => $feedback['quota_exceeded']++,
                    };
                    if ($result === EntryRegistrationResult::Registered) {
                        $newlyRegisteredAthleteIds[] = $athleteId;
                    }
                } catch (Throwable $exception) {
                    $feedback['failed']++;
                    $this->reportFailure('event.registration_failed', $exception, $request);
                }
            }

            // Count athletes that were already registered and still checked (no change)
            $stillChecked = array_intersect($currentlyRegisteredOnPage, $validAthleteIds);
            $feedback['already_registered'] += count($stillChecked);

            // Read back the snapshots so the summary reflects the exact option
            // name and fee persisted by the atomic enrollment query.
            $finalEnrollmentDetails = Entry::enrollmentDetailsByClubEvent($eventId, $clubId);
            $newEnrollments = [];
            foreach ($newlyRegisteredAthleteIds as $athleteId) {
                $newEnrollment = $finalEnrollmentDetails[$athleteId] ?? null;
                if ($newEnrollment === null) {
                    continue;
                }
                $newEnrollments[] = [
                    'athlete_name' => $newEnrollment['athlete_name'],
                    'option_name' => $newEnrollment['option_name'],
                    'fee_cents' => $newEnrollment['fee_cents'],
                ];
            }

            $newEnrollmentCents = array_sum(array_column($newEnrollments, 'fee_cents'));
            $removedEnrollmentCents = array_sum(array_column($removedEnrollments, 'fee_cents'));
            $changeBalanceCents = $newEnrollmentCents - $removedEnrollmentCents;
            $amountDueCents = max(0, $changeBalanceCents);
            $creditCents = max(0, -$changeBalanceCents);
            $club = Club::findById($clubId);
            $paymentInfo = RegistrationPaymentService::completePaymentInfoForEvent($event);
            $paymentReason = $club !== null && $paymentInfo !== null
                ? RegistrationPaymentService::buildPaymentReason($event, $club)
                : '';
            $qrCodeDataUri = $club !== null && $paymentInfo !== null && $amountDueCents > 0
                ? RegistrationPaymentService::buildQrCodeDataUri($event, $club, $amountDueCents)
                : null;
            $paymentSummary = [
                'selected_option_name' => $selectedOption->name,
                'selected_option_fee_cents' => $selectedOption->fee_cents,
                'total_athletes' => count($finalEnrollmentDetails),
                'new_enrollments' => $newEnrollments,
                'removed_enrollments' => $removedEnrollments,
                'new_enrollment_cents' => $newEnrollmentCents,
                'removed_enrollment_cents' => $removedEnrollmentCents,
                'amount_due_cents' => $amountDueCents,
                'credit_cents' => $creditCents,
                'payment_info' => $paymentInfo,
                'payment_reason' => $paymentReason,
                'qr_code_data_uri' => $qrCodeDataUri,
            ];

            $recapDeliveryFailed = false;
            if ($club !== null && ($feedback['added'] > 0 || $feedback['removed'] > 0)) {
                try {
                    $this->registrationMailer()->sendRegistrationRecap(
                        $club->email,
                        RegistrationRecapFormatter::subject($event),
                        RegistrationRecapFormatter::plainText($event, $club, $feedback, $paymentSummary)
                    );
                } catch (Throwable $exception) {
                    $recapDeliveryFailed = true;
                    $this->reportFailure('event.registration_recap_delivery_failed', $exception, $request);
                }
            }

            $flashFeedback = [
                'added' => $feedback['added'],
                'already_registered' => $feedback['already_registered'],
                'removed' => $feedback['removed'],
                'rejected' => $feedback['rejected'],
                'missing_weight' => $feedback['missing_weight'],
                'capacity_exceeded' => $feedback['capacity_exceeded'],
                'quota_exceeded' => $feedback['quota_exceeded'],
                'failed' => $feedback['failed'],
                'recap_delivery_failed' => $recapDeliveryFailed,
                'option_required_error' => false,
                'option_configuration_error' => false,
                'payment_summary' => $paymentSummary,
            ];

            Session::flash(self::REGISTRATION_FEEDBACK_PREFIX . $eventId, $flashFeedback);
            $selection->clear();

            return $this->redirect($registrationRedirect);
        }

        $registeredEnrollmentDetails = Entry::enrollmentDetailsByClubEvent($eventId, $clubId);
        $registered = array_keys($registeredEnrollmentDetails);
        $registrationFeedback = $this->registrationFeedback($eventId);
        $upcomingEvents = $this->resolveUpcomingEvents($eventId, $registrationDate, $limit);
        $registrationOptions = $event->registrationOptions();
        $defaultRegistrationOptionId = null;
        foreach ($registrationOptions as $registrationOption) {
            if ($registrationOption->is_default) {
                $defaultRegistrationOptionId = $registrationOption->id;
                break;
            }
        }
        $draftOptionId = $selection->optionId();
        foreach ($registrationOptions as $registrationOption) {
            if ($registrationOption->id === $draftOptionId) {
                $defaultRegistrationOptionId = $registrationOption->id;
                break;
            }
        }

        return $this->view('events/register', [
            'title' => __('events.registration') . ' - ' . $event->name,
            'event' => $event,
            'athletes' => $athletes,
            'registered' => $registered,
            'selectedAthleteIds' => $selection->selectedAthleteIds($registered),
            'selectionChanges' => $selection->changes(),
            'upcomingEvents' => $upcomingEvents,
            'eventExceptions' => $this->resolveEventExceptions($upcomingEvents),
            'registrationFeedback' => $registrationFeedback,
            'athleteCategories' => $this->athleteCategories($athletes, $event->date),
            'registrationOptions' => $registrationOptions,
            'defaultRegistrationOptionId' => $defaultRegistrationOptionId,
            'registeredEnrollmentDetails' => $registeredEnrollmentDetails,
            'athletePagination' => $athletePagination,
            'registrationSort' => $registrationSort,
            'preserveRegistrationSort' => $preserveRegistrationSort,
            'clubEntryQuotaRemaining' => ClubQuota::remainingEntriesForEvent($eventId, $clubId),
            'clubEntryQuotaLimit' => ClubQuota::entryLimit(),
        ]);
    }

    public function entries(Request $request): Response
    {
        Session::start();
        $isAdmin = AuthContext::isAdministrator();
        $canViewEntryBreakdowns = AuthContext::isAuthenticated();
        $canViewAllAthleteEntries = $isAdmin;
        $requiresRosterAgreements = false;
        $limit = max(1, (int) config('app.events_upcoming_limit'));
        $date = date('Y-m-d');

        $eventId = (int) ($request->input('event') ?? $request->query('event') ?? $request->query('id'));
        if ($eventId <= 0) {
            $upcomingEvents = $this->resolveUpcomingEvents(null, $date, $limit);

            return $this->view('events/entries', [
                'title' => __('events.entries'),
                'event' => null,
                'loggedInClubId' => null,
                'canViewEntryBreakdowns' => false,
                'canViewAllAthleteEntries' => false,
                'requiresRosterAgreements' => false,
                'hasRegistrationException' => false,
                'entryReport' => EventEntriesViewModel::empty(),
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

        if ($event->closed && $clubId !== null && !$hasRegistrationException) {
            $hasCurrentRosterAgreements = ClubTermsAcceptance::hasCurrentVersion((int) $clubId)
                && ClubDataRightsDeclaration::hasCurrentVersion((int) $clubId);
            $canViewAllAthleteEntries = $hasCurrentRosterAgreements;
            $requiresRosterAgreements = !$hasCurrentRosterAgreements;
        }

        $entryClubFilterParameters = self::ENTRY_CLUB_FILTER_PARAMETERS;
        if (!$canViewEntryBreakdowns) {
            unset($entryClubFilterParameters['breakdown']);
        }
        $entryClubFilters = $this->entryFilters($request, $entryClubFilterParameters);
        $entryAthleteFilters = $event->closed && $canViewAllAthleteEntries
            ? $this->entryFilters($request, self::ENTRY_ATHLETE_FILTER_PARAMETERS)
            : $this->emptyEntryFilters(self::ENTRY_ATHLETE_FILTER_PARAMETERS);
        $currentClubFilters = $clubId !== null
            ? $this->entryFilters($request, self::CURRENT_CLUB_FILTER_PARAMETERS)
            : $this->emptyEntryFilters(self::CURRENT_CLUB_FILTER_PARAMETERS);
        $entryOpponentFilters = $event->closed && $clubId !== null && $canViewAllAthleteEntries
            ? $this->entryFilters($request, self::ENTRY_OPPONENT_FILTER_PARAMETERS)
            : $this->emptyEntryFilters(self::ENTRY_OPPONENT_FILTER_PARAMETERS);

        $clubs = Entry::findClubsByEvent($eventId, null);
        $rows = Entry::findByEvent($eventId, null, $event->closed, $event->date);
        $entryReport = EventEntriesViewModel::fromRows(
            $rows,
            $clubs,
            $clubId,
            $currentClubFilters['weight_category']
        );
        $currentClubFilters['weight_category'] = $entryReport->selectedWeightCategory;
        $entryClubsSort = resolve_table_sort(
            $request->query('clubs_sort'),
            $request->query('clubs_direction'),
            ['club', 'federal_code', 'athletes', 'breakdown'],
            'club'
        );
        $entryAthletesSort = resolve_table_sort(
            $request->query('athletes_sort'),
            $request->query('athletes_direction'),
            ['age_class', 'weight_category', 'athlete', 'club', 'gender', 'belt'],
            'age_class'
        );
        $currentClubSort = resolve_table_sort(
            $request->query('club_entries_sort'),
            $request->query('club_entries_direction'),
            ['athlete', 'gender', 'weight', 'weight_category', 'belt'],
            'weight'
        );

        $filteredEntryClubs = $entryReport->filteredClubs($entryClubFilters);
        $filteredEntryAthletes = $event->closed && $canViewAllAthleteEntries
            ? $entryReport->filteredEntries($entryAthleteFilters)
            : [];
        $filteredCurrentClubEntries = $clubId !== null
            ? $entryReport->filteredCurrentClubEntries($currentClubFilters)
            : [];
        $entryOpponentGroups = $event->closed && $clubId !== null && $canViewAllAthleteEntries
            ? $entryReport->opponentGroups($entryOpponentFilters)
            : [];

        $entriesQueryState = [
            'event' => (string) $eventId,
            'clubs_sort' => $entryClubsSort['column'],
            'clubs_direction' => $entryClubsSort['direction'],
            'athletes_sort' => $entryAthletesSort['column'],
            'athletes_direction' => $entryAthletesSort['direction'],
            'club_entries_sort' => $currentClubSort['column'],
            'club_entries_direction' => $currentClubSort['direction'],
            ...$this->entryFilterQuery($entryClubFilters, $entryClubFilterParameters),
            ...$this->entryFilterQuery($entryAthleteFilters, self::ENTRY_ATHLETE_FILTER_PARAMETERS),
            ...$this->entryFilterQuery($currentClubFilters, self::CURRENT_CLUB_FILTER_PARAMETERS),
            ...$this->entryFilterQuery($entryOpponentFilters, self::ENTRY_OPPONENT_FILTER_PARAMETERS),
        ];
        $entriesPaginationState = $entriesQueryState;
        foreach (['clubs_page', 'athletes_page', 'club_entries_page'] as $pageParameter) {
            $page = max(1, (int) $request->query($pageParameter, '1'));
            if ($page > 1) {
                $entriesPaginationState[$pageParameter] = (string) $page;
            }
        }
        $entryClubsPagination = paginate(
            count($filteredEntryClubs),
            max(1, (int) $request->query('clubs_page', '1')),
            self::ENTRIES_PER_PAGE,
            'clubs_page',
            $entriesPaginationState,
            __('events.entries_clubs_heading')
        );
        $entryAthletesPagination = paginate(
            count($filteredEntryAthletes),
            max(1, (int) $request->query('athletes_page', '1')),
            self::ENTRIES_PER_PAGE,
            'athletes_page',
            $entriesPaginationState,
            __('events.entries_athletes_heading')
        );
        $currentClubPagination = paginate(
            count($filteredCurrentClubEntries),
            max(1, (int) $request->query('club_entries_page', '1')),
            self::ENTRIES_PER_PAGE,
            'club_entries_page',
            $entriesPaginationState,
            __('events.entries_current_club_title')
        );

        $upcomingEvents = $this->resolveUpcomingEvents($eventId, $date, $limit);
        $sortedEntryClubs = $entryReport->sortedClubs(
            $entryClubsSort['column'],
            $entryClubsSort['direction'],
            $filteredEntryClubs
        );
        $sortedCurrentClubEntries = $entryReport->sortedCurrentClubEntries(
            $currentClubSort['column'],
            $currentClubSort['direction'],
            $filteredCurrentClubEntries
        );
        $entryClubsFilterResetUrl = $this->entriesUrl(
            $entriesQueryState,
            array_values($entryClubFilterParameters)
        );
        $entryAthletesFilterResetUrl = $this->entriesUrl(
            $entriesQueryState,
            array_values(self::ENTRY_ATHLETE_FILTER_PARAMETERS)
        );
        $currentClubFilterResetUrl = $this->entriesUrl(
            $entriesQueryState,
            array_values(self::CURRENT_CLUB_FILTER_PARAMETERS)
        );
        $entryOpponentsFilterResetUrl = $this->entriesUrl(
            $entriesQueryState,
            array_values(self::ENTRY_OPPONENT_FILTER_PARAMETERS)
        );
        $clubEntriesExportUrl = $this->queryUrl('/events/entries/export', [
            'event' => (string) $eventId,
            ...$this->entryFilterQuery($currentClubFilters, self::CURRENT_CLUB_FILTER_PARAMETERS),
        ]);

        return $this->view('events/entries', [
            'title' => __('events.entries') . ' - ' . $event->name,
            'event' => $event,
            'entryReport' => $entryReport,
            'entryClubs' => array_slice(
                $sortedEntryClubs,
                $entryClubsPagination['offset'],
                $entryClubsPagination['per_page']
            ),
            'entryAthleteGroups' => $event->closed && $canViewAllAthleteEntries
                ? $entryReport->athleteGroupsSortedPage(
                    $entryAthletesSort['column'],
                    $entryAthletesSort['direction'],
                    $entryAthletesPagination['offset'],
                    $entryAthletesPagination['per_page'],
                    $filteredEntryAthletes
                )
                : [],
            'currentClubEntries' => array_slice(
                $sortedCurrentClubEntries,
                $currentClubPagination['offset'],
                $currentClubPagination['per_page']
            ),
            'entryClubsPagination' => $entryClubsPagination,
            'entryAthletesPagination' => $entryAthletesPagination,
            'currentClubPagination' => $currentClubPagination,
            'entryClubsSort' => $entryClubsSort,
            'entryAthletesSort' => $entryAthletesSort,
            'currentClubSort' => $currentClubSort,
            'entryClubFilters' => $entryClubFilters,
            'entryAthleteFilters' => $entryAthleteFilters,
            'currentClubFilters' => $currentClubFilters,
            'entryOpponentGroups' => $entryOpponentGroups,
            'entryOpponentFilters' => $entryOpponentFilters,
            'entriesQueryState' => $entriesQueryState,
            'entryClubsFilterResetUrl' => $entryClubsFilterResetUrl,
            'entryAthletesFilterResetUrl' => $entryAthletesFilterResetUrl,
            'currentClubFilterResetUrl' => $currentClubFilterResetUrl,
            'entryOpponentsFilterResetUrl' => $entryOpponentsFilterResetUrl,
            'clubEntriesExportUrl' => $clubEntriesExportUrl,
            'loggedInClubId' => $clubId !== null ? (int) $clubId : null,
            'canViewEntryBreakdowns' => $canViewEntryBreakdowns,
            'canViewAllAthleteEntries' => $canViewAllAthleteEntries,
            'requiresRosterAgreements' => $requiresRosterAgreements,
            'hasRegistrationException' => $hasRegistrationException,
            'upcomingEvents' => $upcomingEvents,
            'eventExceptions' => $this->resolveEventExceptions($upcomingEvents),
        ]);
    }

    public function exportClubEntries(Request $request): Response
    {
        Session::start();
        $clubId = AuthContext::clubId();
        if ($clubId === null) {
            return $this->redirect('/clubs/login');
        }

        $eventId = (int) ($request->query('event') ?? $request->input('event') ?? 0);
        $event = $eventId > 0
            ? Event::findPublishedByIdOrClosedWithEntries($eventId, $clubId)
            : null;
        if ($event === null) {
            return $this->redirect('/events');
        }
        if (!$event->closed) {
            return $this->redirect('/events/entries?event=' . $eventId);
        }

        $currentClubFilters = $this->entryFilters($request, self::CURRENT_CLUB_FILTER_PARAMETERS);
        $rows = Entry::findSnapshotsByEvent($eventId, $clubId);
        $weightCategories = EventEntriesRoster::weightCategories($rows);
        $weightCategory = $currentClubFilters['weight_category'];
        if ($weightCategory !== '' && !in_array($weightCategory, $weightCategories, true)) {
            return $this->redirect('/events/entries?event=' . $eventId);
        }

        $entryReport = EventEntriesViewModel::fromRows($rows, [], $clubId, $weightCategory);
        $currentClubFilters['weight_category'] = $entryReport->selectedWeightCategory;
        $csv = (new EventEntriesCsvTransfer())->exportRows(
            $entryReport->filteredCurrentClubEntries($currentClubFilters)
        );
        $eventSlug = trim(strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $event->name) ?? ''), '-');
        $eventSlug = substr($eventSlug !== '' ? $eventSlug : 'event-' . $eventId, 0, 24);
        $weightSlug = trim(strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $weightCategory) ?? ''), '-');
        $filename = sprintf(
            'club-athletes-%s-%s%s.csv',
            str_replace('-', '', $event->date),
            $eventSlug,
            $weightSlug !== '' ? '-' . $weightSlug : ''
        );

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @param array<string, string> $parameters Field names mapped to query parameters.
     * @return array<string, string>
     */
    private function entryFilters(Request $request, array $parameters): array
    {
        $filters = [];
        foreach ($parameters as $field => $parameter) {
            $value = $request->query($parameter, '');
            if (!is_string($value) && !is_numeric($value)) {
                $filters[$field] = '';
                continue;
            }
            $filters[$field] = mb_substr(
                trim((string) $value),
                0,
                self::ENTRY_FILTER_MAX_LENGTH,
                'UTF-8'
            );
        }

        return $filters;
    }

    /**
     * @param array<string, string> $parameters
     * @return array<string, string>
     */
    private function emptyEntryFilters(array $parameters): array
    {
        $filters = [];
        foreach ($parameters as $field => $_parameter) {
            $filters[$field] = '';
        }

        return $filters;
    }

    /**
     * @param array<string, string> $filters
     * @param array<string, string> $parameters
     * @return array<string, string>
     */
    private function entryFilterQuery(array $filters, array $parameters): array
    {
        $query = [];
        foreach ($parameters as $field => $parameter) {
            $value = $filters[$field] ?? '';
            if ($value !== '') {
                $query[$parameter] = $value;
            }
        }

        return $query;
    }

    /**
     * @param array<string, string> $query
     * @param list<string> $removeParameters
     */
    private function entriesUrl(array $query, array $removeParameters): string
    {
        foreach ($removeParameters as $parameter) {
            unset($query[$parameter]);
        }

        return $this->queryUrl('/events/entries', $query);
    }

    /** @param array<string, string> $query */
    private function queryUrl(string $path, array $query): string
    {
        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return $queryString !== '' ? $path . '?' . $queryString : $path;
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

    /** @return array<string, mixed>|null */
    private function registrationFeedback(int $eventId): ?array
    {
        $feedback = Session::pullFlash(self::REGISTRATION_FEEDBACK_PREFIX . $eventId);
        if (!is_array($feedback)) {
            return null;
        }

        $paymentSummary = null;
        if (isset($feedback['payment_summary']) && is_array($feedback['payment_summary'])) {
            $ps = $feedback['payment_summary'];
            $rawPaymentInfo = is_array($ps['payment_info'] ?? null) ? $ps['payment_info'] : null;
            $paymentInfo = $rawPaymentInfo !== null
                && trim((string) ($rawPaymentInfo['account_holder'] ?? '')) !== ''
                && trim((string) ($rawPaymentInfo['iban'] ?? '')) !== ''
                ? [
                    'account_holder' => (string) $rawPaymentInfo['account_holder'],
                    'iban' => (string) $rawPaymentInfo['iban'],
                    'bic' => (string) ($rawPaymentInfo['bic'] ?? ''),
                ]
                : null;
            $qrCodeDataUri = is_string($ps['qr_code_data_uri'] ?? null)
                ? $ps['qr_code_data_uri']
                : '';
            if (!str_starts_with($qrCodeDataUri, 'data:image/svg+xml;base64,')) {
                $qrCodeDataUri = '';
            }
            $paymentSummary = [
                'selected_option_name' => (string) ($ps['selected_option_name'] ?? ''),
                'selected_option_fee_cents' => max(0, (int) ($ps['selected_option_fee_cents'] ?? 0)),
                'total_athletes' => (int) ($ps['total_athletes'] ?? 0),
                'new_enrollments' => $this->sanitizeEnrollmentChanges($ps['new_enrollments'] ?? null),
                'removed_enrollments' => $this->sanitizeEnrollmentChanges($ps['removed_enrollments'] ?? null),
                'new_enrollment_cents' => max(0, (int) ($ps['new_enrollment_cents'] ?? 0)),
                'removed_enrollment_cents' => max(0, (int) ($ps['removed_enrollment_cents'] ?? 0)),
                'amount_due_cents' => max(0, (int) ($ps['amount_due_cents'] ?? 0)),
                'credit_cents' => max(0, (int) ($ps['credit_cents'] ?? 0)),
                'payment_info' => $paymentInfo,
                'payment_reason' => (string) ($ps['payment_reason'] ?? ''),
                'qr_code_data_uri' => $qrCodeDataUri,
            ];
        }

        return [
            'added' => max(0, (int) ($feedback['added'] ?? 0)),
            'already_registered' => max(0, (int) ($feedback['already_registered'] ?? 0)),
            'rejected' => max(0, (int) ($feedback['rejected'] ?? 0)),
            'missing_weight' => max(0, (int) ($feedback['missing_weight'] ?? 0)),
            'capacity_exceeded' => max(0, (int) ($feedback['capacity_exceeded'] ?? 0)),
            'failed' => max(0, (int) ($feedback['failed'] ?? 0)),
            'recap_delivery_failed' => !empty($feedback['recap_delivery_failed']),
            'removed' => max(0, (int) ($feedback['removed'] ?? 0)),
            'unsubscribed_failed' => max(0, (int) ($feedback['unsubscribed_failed'] ?? 0)),
            'option_required_error' => !empty($feedback['option_required_error']),
            'option_configuration_error' => !empty($feedback['option_configuration_error']),
            'payment_summary' => $paymentSummary,
        ];
    }

    /**
     * @return list<array{athlete_name:string, option_name:string, fee_cents:int}>
     */
    private function sanitizeEnrollmentChanges(mixed $changes): array
    {
        if (!is_array($changes)) {
            return [];
        }

        $sanitized = [];
        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }
            $sanitized[] = [
                'athlete_name' => (string) ($change['athlete_name'] ?? ''),
                'option_name' => (string) ($change['option_name'] ?? ''),
                'fee_cents' => max(0, (int) ($change['fee_cents'] ?? 0)),
            ];
        }

        return $sanitized;
    }

    private function registrationMailer(): PasswordResetMailer
    {
        return $this->registrationMailer ??= PasswordResetMailerFactory::fromEnvironment();
    }
}
