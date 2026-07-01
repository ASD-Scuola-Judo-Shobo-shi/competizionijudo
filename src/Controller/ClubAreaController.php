<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Model\Athlete;
use App\Model\Club;
use App\Model\Entry;
use App\Service\AthleteCsvImportException;
use App\Service\AthleteCsvTransfer;
use App\Validation\AthleteInputValidator;

final class ClubAreaController extends Controller
{
    public function index(Request $request): Response
    {
        Session::start();
        $clubId = Session::get('club_id');

        if ($clubId === null) {
            return $this->redirect('/club_login.php');
        }

        if ($request->method() === 'POST') {
            validate_csrf((string) $request->post('csrf_token'));
        }

        $club = Club::findById((int) $clubId);
        if ($club === null) {
            Session::destroy();
            return $this->redirect('/club_login.php');
        }

        $athleteCsvFeedback = Session::pullFlash('athlete_csv_feedback');
        if (!is_array($athleteCsvFeedback)) {
            $athleteCsvFeedback = null;
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
                    'date_of_birth' => trim((string) $request->input('date_of_birth')),
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
                        $data['date_of_birth'],
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
                        return $this->redirect('/club_area.php?view=add');
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
                'club' => $club,
                'athletes' => $athletes,
                'edit' => $edit,
                'errors' => $errors,
                'pagination' => $pagination,
                'athleteCategories' => $this->athleteCategories($athletes),
                'athleteCsvFeedback' => $athleteCsvFeedback,
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
        $competitions = Entry::competitionsByClub($club->id, 100);

        return $this->view('club/area_list', [
            'club' => $club,
            'athletes' => $athletes,
            'registrationCounts' => $registrationCounts,
            'competitions' => $competitions,
            'eventFilter' => $eventFilter,
            'pagination' => $pagination,
            'athleteCategories' => $this->athleteCategories($athletes),
            'athleteCsvFeedback' => $athleteCsvFeedback,
            'csvReturnView' => 'list',
        ]);
    }

    /**
     * @param list<Athlete> $athletes
     * @return array<int, array{age_below: int|null, program: string, weight_category: string}>
     */
    private function athleteCategories(array $athletes): array
    {
        $categories = [];
        foreach ($athletes as $athlete) {
            $categories[$athlete->id] = $athlete->categoryForEventDate();
        }

        return $categories;
    }

    public function deleteAthlete(Request $request): Response
    {
        Session::start();
        $clubId = Session::get('club_id');
        if ($clubId === null) {
            return $this->redirect('/club_login.php');
        }

        validate_csrf((string) $request->post('csrf_token'));
        $athleteId = (int) $request->post('athlete_id');
        if ($athleteId > 0) {
            Athlete::remove($athleteId, (int) $clubId);
        }

        return $this->redirect('/club_area.php?view=add');
    }

    public function exportAthletes(Request $request): Response
    {
        Session::start();
        $clubId = Session::get('club_id');
        if ($clubId === null || Club::findById((int) $clubId) === null) {
            return $this->redirect('/club_login.php');
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
        $clubId = Session::get('club_id');
        if ($clubId === null || Club::findById((int) $clubId) === null) {
            return $this->redirect('/club_login.php');
        }

        validate_csrf((string) $request->post('csrf_token'));
        $returnView = $request->post('return_view') === 'add' ? 'add' : 'list';
        $redirect = '/club_area.php?view=' . $returnView;
        $file = $request->file('athletes_csv');
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
            $result = (new AthleteCsvTransfer())->import($temporaryPath, (int) $clubId);
            $this->flashCsvFeedback('success', __('club.area.csv.import_success', [
                'created' => (string) $result->created,
                'updated' => (string) $result->updated,
            ]));
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

    private function flashCsvFeedback(string $type, string $message): void
    {
        Session::flash('athlete_csv_feedback', [
            'type' => $type,
            'message' => $message,
        ]);
    }
}
