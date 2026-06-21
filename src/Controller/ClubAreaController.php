<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Model\Athlete;
use App\Model\Club;
use App\Model\Entry;

use function calculateJudoCategory;

final class ClubAreaController extends Controller
{
    public function index(Request $request): Response
    {
        session_start();
        $clubId = $_SESSION['club_id'] ?? null;

        if ($clubId === null) {
            return $this->redirect('/club_login.php');
        }

        $club = Club::findById((int) $clubId);
        if ($club === null) {
            session_destroy();
            return $this->redirect('/club_login.php');
        }

        $db = \App\Model\Database::connection();

        $view = (string) ($request->query('view') ?? 'list');

        $delete = (int) ($request->query('delete') ?? 0);
        if ($delete > 0) {
            $db->prepare('DELETE FROM athletes WHERE id = ? AND club_id = ?')->execute([$delete, $club->id]);
            return $this->redirect('/club_area.php?view=add');
        }

        if ($view === 'add') {
            $errors = [];
            $edit = null;

            if ($request->method() === 'POST') {
                $data = [
                    'club_id' => $club->id,
                    'last_name' => trim((string) $request->input('last_name')),
                    'first_name' => trim((string) $request->input('first_name')),
                    'gender' => trim((string) $request->input('gender')),
                    'date_of_birth' => trim((string) $request->input('date_of_birth')),
                    'weight_kg' => (float) str_replace(',', '.', (string) $request->input('weight_kg')),
                    'belt' => trim((string) $request->input('belt')),
                    'membership_number' => trim((string) $request->input('membership_number')),
                    'notes' => trim((string) $request->input('notes')),
                ];

                $category = calculateJudoCategory($data['date_of_birth'], $data['gender'], $data['weight_kg']);
                $data['program'] = $category['program'];
                $data['weight_category'] = $category['weight_category'];

                if ((string) $request->input('athlete_id') !== '') {
                    $edit = Athlete::findById((int) $request->input('athlete_id'), $club->id);
                    if ($edit !== null) {
                        $edit->update($data);
                    }
                } else {
                    Athlete::add($data);
                }

                return $this->redirect('/club_area.php?view=add');
            }

            if ($request->query('edit') !== null) {
                $edit = Athlete::findById((int) $request->query('edit'), $club->id);
            }

            $athletes = Athlete::findByClub($club->id);

            return $this->view('club/area_add', [
                'club' => $club,
                'athletes' => $athletes,
                'edit' => $edit,
                'errors' => $errors,
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
}
