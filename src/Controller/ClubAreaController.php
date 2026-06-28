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
use App\Validation\AthleteInputValidator;

use function calculateJudoCategory;

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

        $db = \App\Model\Database::connection();

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
                    $category = calculateJudoCategory(
                        $data['date_of_birth'],
                        $data['gender'],
                        $data['weight_kg']
                    );
                    $data['program'] = $category['program'];
                    $data['weight_category'] = $category['weight_category'];

                    try {
                        if ((string) $request->input('athlete_id') !== '') {
                            $edit = Athlete::findById((int) $request->input('athlete_id'), $club->id);
                            if ($edit !== null) {
                                $edit->update($data);
                            }
                        } else {
                            Athlete::add($data);
                        }
                    } catch (\Throwable) {
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

            $stmt = $db->prepare('SELECT COUNT(*) FROM athletes WHERE club_id = ?');
            $stmt->execute([$club->id]);
            $total = (int) $stmt->fetchColumn();
            $page = max(1, (int) ($request->query('page', '1')));
            $pagination = paginate($total, $page, 50);

            $perPage = (int) $pagination['per_page'];
            $offset = (int) $pagination['offset'];
            $stmt = $db->prepare("SELECT * FROM athletes WHERE club_id = ? ORDER BY last_name, first_name LIMIT $perPage OFFSET $offset");
            $stmt->execute([$club->id]);
            $athletes = array_map(fn(array $row) => Athlete::fromArray($row), $stmt->fetchAll() ?: []);

            return $this->view('club/area_add', [
                'club' => $club,
                'athletes' => $athletes,
                'edit' => $edit,
                'errors' => $errors,
                'pagination' => $pagination,
            ]);
        }

        $allEntries = Entry::findByClub($club->id);
        $athletes = Athlete::findByClub($club->id);

        $eventFilter = (int) ($request->query('event') ?? 0);

        $rows = $allEntries;
        if ($eventFilter > 0) {
            $rows = array_filter($rows, fn($r) => (int) ($r['event_id'] ?? 0) === $eventFilter);
        }

        $competitions = [];
        foreach ($allEntries as $e) {
            $eid = (int) ($e['event_id'] ?? 0);
            if (!isset($competitions[$eid])) {
                $competitions[$eid] = [
                    'id' => $eid,
                    'name' => (string) ($e['nome_evento'] ?? ''),
                    'date' => (string) ($e['data_gara'] ?? ''),
                ];
            }
        }

        return $this->view('club/area_list', [
            'club' => $club,
            'athletes' => $athletes,
            'entries' => $rows,
            'competitions' => $competitions,
            'eventFilter' => $eventFilter,
        ]);
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
}
