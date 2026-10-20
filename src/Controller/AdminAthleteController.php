<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\AuthContext;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Model\Athlete;
use App\Model\Club;
use App\Service\AthleteCsvUpload;
use App\Service\ClubQuota;
use App\Validation\AthleteInputValidator;

final class AdminAthleteController extends Controller
{
    public static function feedbackKey(int $clubId): string
    {
        return 'admin_athletes_feedback_' . $clubId;
    }

    public static function importFeedbackKey(int $clubId): string
    {
        return 'admin_athletes_import_' . $clubId;
    }

    public function edit(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }
        if ($request->method() === 'POST') {
            validate_csrf((string) $request->post('csrf_token'));
        }
        $club = $this->selectedClub($request);
        if ($club === null) {
            return $this->redirect('/admin/clubs');
        }

        $athleteId = (int) $request->query('athlete_id', 0);
        $athlete = $athleteId > 0 ? Athlete::findById($athleteId, $club->id) : null;
        if ($request->query('athlete_id') !== null && $athlete === null) {
            throw new HttpException(404, __('admin.clubs.athlete_not_found'));
        }

        $values = [
            'last_name' => $athlete->last_name ?? '',
            'first_name' => $athlete->first_name ?? '',
            'gender' => $athlete->gender ?? '',
            'birth_date' => $athlete->birth_date ?? '',
            'weight_kg' => $athlete?->weight_kg !== null ? (string) $athlete->weight_kg : '',
            'belt' => $athlete->belt ?? '',
            'membership_number' => $athlete->membership_number ?? '',
            'notes' => $athlete->notes ?? '',
        ];
        $errors = [];
        if ($request->method() === 'POST') {
            foreach (array_keys($values) as $field) {
                $values[$field] = trim((string) $request->post($field));
            }
            $errors = array_map(static fn(string $key): string => __($key), AthleteInputValidator::errors(
                $values['last_name'],
                $values['first_name'],
                $values['gender'],
                $values['birth_date'],
                $values['weight_kg'],
                $values['belt'],
                null,
                $values['notes']
            ));
            if ($errors === []) {
                try {
                    $data = array_merge($values, [
                        'club_id' => $club->id,
                        'weight_kg' => (float) str_replace(',', '.', $values['weight_kg']),
                    ]);
                    if ($athlete !== null) {
                        $athlete->update($data);
                    } elseif (!ClubQuota::canAddAthletes($club->id, 1)) {
                        $errors[] = __('errors.athlete_quota_exceeded', [
                            'limit' => (string) ClubQuota::athleteLimit(),
                        ]);
                    } else {
                        Athlete::add($data);
                    }
                    if ($errors === []) {
                        Session::flash(self::feedbackKey($club->id), [
                            'type' => 'success', 'message' => __('tables.update_success'),
                        ]);

                        return $this->redirect('/admin/clubs/athletes?club_id=' . $club->id, 303);
                    }
                } catch (\Throwable $exception) {
                    $this->reportFailure('admin.athlete_save_failed', $exception, $request);
                    $errors[] = __('errors.save_failed');
                }
            }
        }

        return $this->view('admin/edit_athlete', [
            'title' => $athlete !== null ? __('club.area.edit_athlete') : __('club.area.add_athlete'),
            'club' => $club,
            'athlete' => $athlete,
            'values' => $values,
            'errors' => $errors,
        ], $errors === [] ? 200 : 422);
    }

    public function delete(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }
        validate_csrf((string) $request->post('csrf_token'));
        $club = $this->selectedClub($request);
        if ($club === null) {
            return $this->redirect('/admin/clubs');
        }
        $athleteId = (int) $request->post('athlete_id', 0);
        if ($athleteId <= 0 || Athlete::findById($athleteId, $club->id) === null) {
            throw new HttpException(404, __('admin.clubs.athlete_not_found'));
        }

        try {
            Athlete::remove($athleteId, $club->id);
            $feedback = ['type' => 'success', 'message' => __('admin.clubs.athlete_deleted')];
        } catch (\Throwable $exception) {
            $this->reportFailure('admin.athlete_delete_failed', $exception, $request);
            $feedback = ['type' => 'error', 'message' => __('admin.clubs.athlete_delete_failed')];
        }
        Session::flash(self::feedbackKey($club->id), $feedback);

        return $this->redirect('/admin/clubs/athletes?club_id=' . $club->id, 303);
    }

    public function import(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }
        validate_csrf((string) $request->post('csrf_token'));
        $club = $this->selectedClub($request);
        if ($club === null) {
            return $this->redirect('/admin/clubs');
        }

        try {
            $feedback = (new AthleteCsvUpload())->import(
                $request->file('athletes_file'),
                $club->id,
                $request->post('merge_incomplete') === '1'
            );
        } catch (\Throwable $exception) {
            $this->reportFailure('admin.athlete_import_failed', $exception, $request);
            $feedback = [
                'type' => 'error', 'message' => __('club.area.csv.import_failed'), 'report' => [], 'omitted' => 0,
            ];
        }
        Session::flash(self::importFeedbackKey($club->id), $feedback);

        return $this->redirect('/admin/clubs/athletes?club_id=' . $club->id, 303);
    }

    private function selectedClub(Request $request): ?Club
    {
        $clubId = (int) $request->query('club_id', 0);

        return $clubId > 0 ? Club::findById($clubId) : null;
    }
}
