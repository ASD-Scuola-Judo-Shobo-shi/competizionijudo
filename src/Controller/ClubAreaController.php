<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\AuthContext;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Model\Athlete;
use App\Model\Club;
use App\Model\Entry;
use App\Service\AthleteCsvImportException;
use App\Service\AthleteCsvTransfer;
use App\Service\AthleteImportIssue;
use App\Service\AthleteImportReconciliation;
use App\Validation\AthleteInputValidator;

final class ClubAreaController extends Controller
{
    private const IMPORT_REPORT_LIMIT = 200;
    private const INLINE_FEEDBACK = 'athlete_inline_feedback';

    public function index(Request $request): Response
    {
        Session::start();
        $clubId = AuthContext::clubId();

        if ($clubId === null) {
            return $this->redirect('/clubs/login');
        }

        if ($request->method() === 'POST') {
            validate_csrf((string) $request->post('csrf_token'));
        }

        $club = Club::findById((int) $clubId);
        if ($club === null) {
            Session::destroy();
            return $this->redirect('/clubs/login');
        }

        $athleteCsvFeedback = Session::pullFlash('athlete_csv_feedback');
        if (!is_array($athleteCsvFeedback)) {
            $athleteCsvFeedback = null;
        }
        $athleteInlineFeedback = Session::pullFlash(self::INLINE_FEEDBACK);
        if (!is_array($athleteInlineFeedback)) {
            $athleteInlineFeedback = null;
        }
        $view = (string) ($request->query('view') ?? 'list');

        if ($view === 'add') {
            $errors = [];
            $edit = null;

            if ($request->method() === 'POST') {
                $weightInput = trim((string) $request->input('weight_kg'));
                $data = [
                    'club_id' => $club->id,
                    'last_name' => trim((string) $request->input('last_name')),
                    'first_name' => trim((string) $request->input('first_name')),
                    'gender' => trim((string) $request->input('gender')),
                    'birth_date' => trim((string) $request->input('birth_date')),
                    'weight_kg' => (float) str_replace(',', '.', $weightInput),
                    'belt' => trim((string) $request->input('belt')),
                    'membership_number' => trim((string) $request->input('membership_number')),
                    'notes' => trim((string) $request->input('notes')),
                ];
                foreach (
                    AthleteInputValidator::errors(
                        $data['last_name'],
                        $data['first_name'],
                        $data['gender'],
                        $data['birth_date'],
                        $weightInput,
                        $data['belt']
                    ) as $key
                ) {
                    $errors[] = __($key);
                }

                if ($errors === []) {
                    try {
                        if ((string) $request->input('athlete_id') !== '') {
                            $edit = Athlete::findById((int) $request->input('athlete_id'), $club->id);
                            if ($edit !== null) {
                                $edit->update($data);
                            }
                        } else {
                            Athlete::add($data);
                        }
                    } catch (\Throwable $exception) {
                        $this->reportFailure('club.athlete_save_failed', $exception, $request);
                        $errors[] = __('errors.save_failed');
                    }

                    if ($errors === []) {
                        return $this->redirect('/clubs/area?view=add');
                    }
                } elseif ((string) $request->input('athlete_id') !== '') {
                    $edit = Athlete::findById((int) $request->input('athlete_id'), $club->id);
                }
            }

            if ($request->query('edit') !== null) {
                $edit = Athlete::findById((int) $request->query('edit'), $club->id);
            }

            $page = max(1, (int) ($request->query('page', '1')));
            $pagination = paginate(Athlete::countByClub($club->id), $page, 50);
            $athletes = Athlete::pageByClub(
                $club->id,
                $pagination['per_page'],
                $pagination['offset']
            );

            return $this->view('club/area_add', [
                'title' => $edit !== null ? __('club.area.edit_athlete') : __('club.area.add_athlete'),
                'club' => $club,
                'athletes' => $athletes,
                'edit' => $edit,
                'errors' => $errors,
                'pagination' => $pagination,
                'athleteCategories' => $this->athleteCategories($athletes),
                'athleteCsvFeedback' => $athleteCsvFeedback,
                'athleteInlineFeedback' => $athleteInlineFeedback,
                'csvReturnView' => 'add',
            ]);
        }

        $page = max(1, (int) ($request->query('page', '1')));
        $pagination = paginate(Athlete::countByClub($club->id), $page, 50);
        $athletes = Athlete::pageByClub(
            $club->id,
            $pagination['per_page'],
            $pagination['offset']
        );
        $eventFilter = (int) ($request->query('event') ?? 0);
        $athleteIds = array_map(static fn(Athlete $athlete): int => $athlete->id, $athletes);
        $registrationCounts = Entry::registrationCountsByAthletes(
            $club->id,
            $athleteIds,
            $eventFilter > 0 ? $eventFilter : null
        );
        $events = Entry::eventsByClub($club->id, 100);

        return $this->view('club/area_list', [
            'title' => __('club.area.athlete_archive'),
            'club' => $club,
            'athletes' => $athletes,
            'registrationCounts' => $registrationCounts,
            'events' => $events,
            'eventFilter' => $eventFilter,
            'pagination' => $pagination,
            'athleteCategories' => $this->athleteCategories($athletes),
            'athleteCsvFeedback' => $athleteCsvFeedback,
            'athleteInlineFeedback' => $athleteInlineFeedback,
            'csvReturnView' => 'list',
        ]);
    }

    /**
     * @param list<Athlete> $athletes
     * @return array<int, array{age_below: int|null, type: string, weight_category: string}>
     */
    private function athleteCategories(array $athletes): array
    {
        $categories = [];
        foreach ($athletes as $athlete) {
            $categories[$athlete->id] = $athlete->categoryForEventDate();
        }

        return $categories;
    }

    public function updateAthleteInline(Request $request): Response
    {
        Session::start();
        $clubId = AuthContext::clubId();
        if ($clubId === null) {
            return $this->redirect('/clubs/login');
        }

        validate_csrf((string) $request->post('csrf_token'));
        $athleteId = (int) $request->post('athlete_id');
        $returnView = $request->post('return_view') === 'add' ? 'add' : 'list';
        $page = max(1, (int) $request->post('page', 1));
        $eventFilter = max(0, (int) $request->post('event', 0));
        $redirect = '/clubs/area?view=' . $returnView . '&page=' . $page;
        if ($returnView === 'list' && $eventFilter > 0) {
            $redirect .= '&event=' . $eventFilter;
        }
        $redirect .= '#athlete-row-' . $athleteId;

        $athlete = $athleteId > 0 ? Athlete::findById($athleteId, (int) $clubId) : null;
        if ($athlete === null) {
            Session::flash(self::INLINE_FEEDBACK, [
                'type' => 'error',
                'message' => __('tables.update_failed'),
            ]);

            return $this->redirect($redirect, 303);
        }

        $weightInput = trim((string) $request->post('weight_kg'));
        $data = [
            'club_id' => (int) $clubId,
            'last_name' => trim((string) $request->post('last_name')),
            'first_name' => trim((string) $request->post('first_name')),
            'gender' => trim((string) $request->post('gender')),
            'birth_date' => trim((string) $request->post('birth_date')),
            'weight_kg' => (float) str_replace(',', '.', $weightInput),
            'belt' => trim((string) $request->post('belt')),
            'membership_number' => trim((string) $request->post('membership_number')),
            'notes' => trim((string) $request->post('notes')),
        ];
        $errors = AthleteInputValidator::errors(
            $data['last_name'],
            $data['first_name'],
            $data['gender'],
            $data['birth_date'],
            $weightInput,
            $data['belt']
        );

        if ($errors !== []) {
            Session::flash(self::INLINE_FEEDBACK, [
                'type' => 'error',
                'message' => __($errors[0]),
            ]);

            return $this->redirect($redirect, 303);
        }

        try {
            $athlete->update($data);
            Session::flash(self::INLINE_FEEDBACK, [
                'type' => 'success',
                'message' => __('tables.update_success'),
            ]);
        } catch (\Throwable $exception) {
            $this->reportFailure('club.athlete_inline_save_failed', $exception, $request);
            Session::flash(self::INLINE_FEEDBACK, [
                'type' => 'error',
                'message' => __('tables.update_failed'),
            ]);
        }

        return $this->redirect($redirect, 303);
    }

    public function deleteAthlete(Request $request): Response
    {
        Session::start();
        $clubId = AuthContext::clubId();
        if ($clubId === null) {
            return $this->redirect('/clubs/login');
        }

        validate_csrf((string) $request->post('csrf_token'));
        $athleteId = (int) $request->post('athlete_id');
        if ($athleteId > 0) {
            Athlete::remove($athleteId, (int) $clubId);
        }

        return $this->redirect('/clubs/area?view=add');
    }

    public function exportAthletes(Request $request): Response
    {
        Session::start();
        $clubId = AuthContext::clubId();
        if ($clubId === null || Club::findById((int) $clubId) === null) {
            return $this->redirect('/clubs/login');
        }

        $csv = (new AthleteCsvTransfer())->export((int) $clubId);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="athletes-' . date('Y-m-d') . '.csv"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function importAthletes(Request $request): Response
    {
        Session::start();
        $clubId = AuthContext::clubId();
        if ($clubId === null || Club::findById((int) $clubId) === null) {
            return $this->redirect('/clubs/login');
        }

        validate_csrf((string) $request->post('csrf_token'));
        $returnView = $request->post('return_view') === 'add' ? 'add' : 'list';
        $redirect = '/clubs/area?view=' . $returnView;
        $file = $request->file('athletes_file') ?? $request->file('athletes_csv');
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            $this->flashCsvFeedback('error', __('club.area.csv.file_required'));
            return $this->redirect($redirect);
        }
        if ($uploadError !== UPLOAD_ERR_OK) {
            $this->flashCsvFeedback('error', __('club.area.csv.upload_failed'));
            return $this->redirect($redirect);
        }

        $temporaryPath = is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : '';
        $declaredSize = (int) ($file['size'] ?? 0);
        if ($declaredSize > AthleteCsvTransfer::MAX_BYTES) {
            $this->flashCsvFeedback('error', __('club.area.csv.too_large'));
            return $this->redirect($redirect);
        }
        if (
            $temporaryPath === ''
            || !is_file($temporaryPath)
            || (PHP_SAPI !== 'cli' && !is_uploaded_file($temporaryPath))
        ) {
            $this->flashCsvFeedback('error', __('club.area.csv.upload_failed'));
            return $this->redirect($redirect);
        }

        try {
            $result = (new AthleteCsvTransfer())->import(
                $temporaryPath,
                (int) $clubId,
                $request->post('merge_incomplete') === '1'
            );
            $replacements = [
                'created' => (string) $result->created,
                'updated' => (string) $result->updated,
                'unchanged' => (string) $result->unchanged,
                'skipped' => (string) $result->skipped(),
            ];
            $report = array_map(
                fn(AthleteImportIssue $issue): array => [
                    'row' => $issue->row,
                    'identity' => $issue->identity,
                    'message' => $this->importIssueMessage($issue),
                    'existing_athlete_id' => $issue->existingAthleteId,
                ],
                $result->issues
            );
            foreach ($result->reconciliations as $reconciliation) {
                $report[] = [
                    'row' => $reconciliation->row,
                    'identity' => $reconciliation->identity,
                    'message' => $this->reconciliationMessage($reconciliation),
                    'existing_athlete_id' => $reconciliation->existingAthleteId,
                ];
            }
            usort(
                $report,
                static fn(array $left, array $right): int => $left['row'] <=> $right['row']
            );
            $reportCount = count($report);
            $report = array_slice($report, 0, self::IMPORT_REPORT_LIMIT);
            $omitted = max(0, $reportCount - count($report));

            if ($result->issues === []) {
                $this->flashCsvFeedback(
                    'success',
                    __('club.area.csv.import_success', $replacements),
                    $report,
                    $omitted
                );
            } else {
                $hasImportedRows = $result->created + $result->updated + $result->unchanged > 0;
                $this->flashCsvFeedback(
                    $hasImportedRows ? 'warning' : 'error',
                    __('club.area.csv.import_with_issues', $replacements),
                    $report,
                    $omitted
                );
            }
        } catch (AthleteCsvImportException $exception) {
            $this->flashCsvFeedback('error', $this->csvImportError($exception));
        } catch (\Throwable $exception) {
            $this->reportFailure('club.athlete_csv_import_failed', $exception, $request);
            $this->flashCsvFeedback('error', __('club.area.csv.import_failed'));
        }

        return $this->redirect($redirect);
    }

    private function csvImportError(AthleteCsvImportException $exception): string
    {
        $replacements = [];
        if ($exception->row !== null) {
            $replacements['row'] = (string) $exception->row;
        }
        if ($exception->validationKeys !== []) {
            $replacements['errors'] = implode(' ', array_map(
                static fn(string $key): string => __($key),
                $exception->validationKeys
            ));
        }

        return __($exception->translationKey, $replacements);
    }

    private function importIssueMessage(AthleteImportIssue $issue): string
    {
        $replacements = [
            'row' => (string) $issue->row,
            'athlete' => $issue->identity,
        ];
        if ($issue->validationKeys !== []) {
            $replacements['errors'] = implode(' ', array_map(
                static fn(string $key): string => __($key),
                $issue->validationKeys
            ));
        }
        if ($issue->fields !== []) {
            $replacements['fields'] = implode(', ', array_map(
                static fn(string $field): string => __('club.area.csv.headers.' . $field),
                $issue->fields
            ));
        }

        return __($issue->translationKey, $replacements);
    }

    private function reconciliationMessage(AthleteImportReconciliation $reconciliation): string
    {
        $details = [];
        foreach ($reconciliation->resolutions as $field => $resolution) {
            $details[] = __('club.area.csv.headers.' . $field)
                . ': '
                . __('club.area.csv.reconciliation.' . $resolution);
        }

        return __('club.area.csv.reconciled', [
            'details' => implode('; ', $details),
        ]);
    }

    /**
     * @param list<array{
     *     row: int,
     *     identity: string,
     *     message: string,
     *     existing_athlete_id: int|null
     * }> $report
     */
    private function flashCsvFeedback(
        string $type,
        string $message,
        array $report = [],
        int $omitted = 0
    ): void {
        Session::flash('athlete_csv_feedback', [
            'type' => $type,
            'message' => $message,
            'report' => $report,
            'omitted' => $omitted,
        ]);
    }
}
