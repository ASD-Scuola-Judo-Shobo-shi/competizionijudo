<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\AuthContext;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Model\Club;
use App\Model\Entry;
use App\Model\Event;
use App\Model\EventRegistrationException;
use App\Model\EventRegistrationOption;
use App\Service\EventEditor;
use App\Service\EventEntriesCsvTransfer;
use App\Service\EventUploadStorage;
use App\Validation\EventInputValidator;

final class EventManagementController extends Controller
{
    private const EVENT_ENROLLMENTS_PER_PAGE = 50;
    private readonly EventUploadStorage $eventUploadStorage;

    public function __construct(View $view, Request $request, ?Logger $logger = null, ?EventUploadStorage $eventUploadStorage = null)
    {
        parent::__construct($view, $request, $logger);
        $this->eventUploadStorage = $eventUploadStorage ?? new EventUploadStorage();
    }

    public function adminDetails(Request $request): Response
    {
        Session::start();
        return AuthContext::isAdministrator()
            ? $this->details($request, null)
            : $this->redirect('/admin/login');
    }

    public function clubDetails(Request $request): Response
    {
        Session::start();
        $clubId = AuthContext::clubId();
        return $clubId !== null
            ? $this->details($request, $clubId)
            : $this->redirect('/clubs/login');
    }

    public function clubIndex(Request $request): Response
    {
        Session::start();
        $clubId = AuthContext::clubId();
        if ($clubId === null) {
            return $this->redirect('/clubs/login');
        }
        $pagination = paginate(Event::countOwned($clubId), (int) $request->query('page', 1), 50);
        return $this->view('club/events', [
            'title' => __('club_events.title'),
            'events' => Event::pageOwned($clubId, $pagination['per_page'], $pagination['offset']),
            'pagination' => $pagination,
        ]);
    }

    public function clubDelete(Request $request): Response
    {
        Session::start();
        $clubId = AuthContext::clubId();
        if ($clubId === null) {
            return $this->redirect('/clubs/login');
        }
        validate_csrf((string) $request->post('csrf_token'));
        $eventId = (int) $request->post('event_id');
        $event = Event::findOwnedById($eventId, $clubId);
        if ($event !== null) {
            Event::removeOwned($eventId, $clubId);
            try {
                $this->eventUploadStorage->purgeMany([$event->poster_file, $event->info_file]);
            } catch (\Throwable $exception) {
                $this->reportFailure('event.upload_purge_failed', $exception, $request);
            }
        }
        return $this->redirect('/clubs/events');
    }

    private function details(Request $request, ?int $ownerClubId): Response
    {
        Session::start();
        $listPath = $ownerClubId === null ? '/admin/events' : '/clubs/events';
        $eventId = (int) ($request->input('event_id') ?? $request->input('id') ?? 0);
        $event = $eventId > 0
            ? ($ownerClubId === null ? Event::findById($eventId) : Event::findOwnedById($eventId, $ownerClubId))
            : null;
        if ($eventId > 0 && $event === null) {
            return $this->redirect($listPath);
        }

        $enrolledClubs = $event !== null && $ownerClubId === null
            ? Entry::findClubsByEvent($event->id, null)
            : [];
        $requestedEnrollmentClubId = (int) $request->query('club_id', 0);
        $enrolledClubIds = array_map(
            static fn(array $club): int => (int) ($club['id'] ?? 0),
            $enrolledClubs
        );
        $selectedEnrollmentClubId = in_array($requestedEnrollmentClubId, $enrolledClubIds, true)
            ? $requestedEnrollmentClubId
            : null;
        $enrollmentTotal = $event !== null && $ownerClubId === null
            ? Entry::countByEvent($event->id, $selectedEnrollmentClubId)
            : 0;
        $enrollmentPage = max(1, (int) $request->query('enrollment_page', '1'));
        $enrollmentPagination = paginate(
            $enrollmentTotal,
            $enrollmentPage,
            self::EVENT_ENROLLMENTS_PER_PAGE,
            'enrollment_page',
            $request->queryParameters(),
            __('admin.event_details.enrolled_athletes')
        );
        $enrollmentFields = EventEntriesCsvTransfer::headers();
        $enrollmentSort = resolve_table_sort(
            $request->query('enrollment_sort'),
            $request->query('enrollment_direction'),
            $enrollmentFields,
            'club_name'
        );
        $enrolledAthletes = $event !== null && $ownerClubId === null
            ? Entry::pageByEvent(
                $event->id,
                $selectedEnrollmentClubId,
                $event->closed,
                $enrollmentPagination['per_page'],
                $enrollmentPagination['offset'],
                $enrollmentSort['column'],
                $enrollmentSort['direction'],
                $event->date
            )
            : [];

        $locations = Event::locations($ownerClubId);
        $clubs = $ownerClubId === null ? Club::eventExceptionChoices() : [];
        $exceptionClubIds = $event !== null ? EventRegistrationException::clubIdsForEvent($event->id) : [];

        $error = '';
        $formRegistrationOptions = $this->registrationOptionsForForm($event);
        $formSepaAccountHolder = $event->sepa_account_holder ?? '';
        $formSepaIban = $event->sepa_iban ?? '';
        $formSepaBic = $event->sepa_bic ?? '';

        if ($request->method() === 'POST') {
            validate_csrf((string) $request->post('csrf_token'));
            $formRegistrationOptions = $this->registrationOptionsFromRequest($request);
            $formSepaAccountHolder = trim((string) $request->post('sepa_account_holder'));
            $formSepaIban = EventInputValidator::normalizeIban((string) $request->post('sepa_iban'));
            $formSepaBic = EventInputValidator::normalizeBic((string) $request->post('sepa_bic'));
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
             *     closed: int,
             *     registration_options: list<array{
             *         id:int|null,
             *         name:string,
             *         fee_amount:string,
             *         fee_cents:int|null,
             *         is_default:bool
             *     }>,
             *     sepa_account_holder:string,
             *     sepa_iban:string,
             *     sepa_bic:string
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
                'registration_options' => $formRegistrationOptions,
                'sepa_account_holder' => $formSepaAccountHolder,
                'sepa_iban' => $formSepaIban,
                'sepa_bic' => $formSepaBic,
            ];
            $uploads = $this->eventUploads();
            $validationErrors = array_merge(EventInputValidator::errors(
                $data['name'],
                $data['date'],
                $data['location'],
                $data['registration_deadline'],
                $data['type'],
                $uploads,
                $data['max_participants'],
                $data['notes']
            ), EventInputValidator::registrationConfigurationErrors(
                $data['registration_options'],
                $data['sepa_account_holder'],
                $data['sepa_iban'],
                $data['sepa_bic']
            ));
            if ($validationErrors !== []) {
                $error = __($validationErrors[0]);
            }

            if ($error === '') {
                $exceptionInput = $request->post('registration_exceptions', []);
                $exceptionClubIds = is_array($exceptionInput)
                    ? array_values(array_filter(array_map('intval', $exceptionInput), static fn(int $id): bool => $id > 0))
                    : [];
                try {
                    (new EventEditor($this->eventUploadStorage, $this->logger))->save(
                        $event,
                        $data,
                        $uploads,
                        $exceptionClubIds,
                        $ownerClubId,
                        $request->correlationId()
                    );
                    return $this->redirect($listPath);
                } catch (\Throwable $exception) {
                    $this->reportFailure('admin.event_save_failed', $exception, $request);
                    $error = __('errors.save_failed');
                }
            }
        }

        if (isset($data) && $error !== '') {
            $event = Event::fromArray(array_merge([
                'id' => $event->id ?? 0,
                'poster_file' => $event->poster_file ?? '',
                'info_file' => $event->info_file ?? '',
            ], $data));
        }

        $upcomingEventLimit = max(1, (int) config('app.events_upcoming_limit'));
        $upcomingEvents = $event !== null
            ? Event::nextUpcomingPublishedIncludingClosed($event->id, date('Y-m-d'), $upcomingEventLimit)
            : Event::upcomingPublishedIncludingClosed(date('Y-m-d'), $upcomingEventLimit);

        return $this->view('admin/event_details', [
            'clubManaged' => $ownerClubId !== null,
            'title' => $event !== null
                ? __('admin.edit.title') . ' - ' . $event->name
                : __('admin.event_details.title'),
            'event' => $event,
            'error' => $error,
            'locations' => $locations,
            'clubs' => $clubs,
            'enrolledClubs' => $enrolledClubs,
            'enrolledAthletes' => $enrolledAthletes,
            'enrollmentPagination' => $enrollmentPagination,
            'enrollmentFields' => $enrollmentFields,
            'enrollmentSort' => $enrollmentSort,
            'selectedEnrollmentClubId' => $selectedEnrollmentClubId,
            'exceptionClubIds' => $exceptionClubIds,
            'formRegistrationOptions' => $formRegistrationOptions,
            'formSepaAccountHolder' => $formSepaAccountHolder,
            'formSepaIban' => $formSepaIban,
            'formSepaBic' => $formSepaBic,
            'upcomingEvents' => $upcomingEvents,
        ]);
    }

    /**
     * @return list<array{
     *     id:int|null,
     *     name:string,
     *     fee_amount:string,
     *     fee_cents:int|null,
     *     is_default:bool
     * }>
     */
    private function registrationOptionsFromRequest(Request $request): array
    {
        $rawOptions = $request->post('registration_options', []);
        if (!is_array($rawOptions)) {
            return [];
        }

        $defaultIndex = $request->post('registration_option_default');
        $defaultIndex = is_scalar($defaultIndex) ? (string) $defaultIndex : '';

        $options = [];
        foreach ($rawOptions as $index => $option) {
            if (!is_array($option)) {
                continue;
            }
            $name = trim((string) ($option['name'] ?? ''));
            $feeAmount = trim((string) ($option['fee_amount'] ?? ''));
            if ($name === '' && $feeAmount === '') {
                continue;
            }
            $optionId = filter_var($option['id'] ?? null, FILTER_VALIDATE_INT);
            $options[] = [
                'id' => $optionId !== false && $optionId > 0 ? $optionId : null,
                'name' => $name,
                'fee_amount' => $feeAmount,
                'fee_cents' => EventInputValidator::registrationFeeCents($feeAmount),
                'is_default' => $defaultIndex !== '' && (string) $index === $defaultIndex,
            ];
        }

        return $options;
    }

    /**
     * @return list<array{
     *     id:int|null,
     *     name:string,
     *     fee_amount:string,
     *     fee_cents:int|null,
     *     is_default:bool
     * }>
     */
    private function registrationOptionsForForm(?Event $event): array
    {
        if ($event === null) {
            return [[
                'id' => null,
                'name' => '',
                'fee_amount' => '',
                'fee_cents' => null,
                'is_default' => true,
            ]];
        }

        $options = array_map(
            static fn(EventRegistrationOption $option): array => [
                'id' => $option->id,
                'name' => $option->name,
                'fee_amount' => number_format($option->fee_cents / 100, 2, '.', ''),
                'fee_cents' => $option->fee_cents,
                'is_default' => $option->is_default,
            ],
            $event->registrationOptions()
        );

        return $options !== [] ? $options : [[
            'id' => null,
            'name' => '',
            'fee_amount' => '',
            'fee_cents' => null,
            'is_default' => true,
        ]];
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
}
