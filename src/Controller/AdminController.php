<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Model\Club;
use App\Model\Event;

final class AdminController extends Controller
{
    public function login(Request $request): Response
    {
        $errors = [];

        if ($request->method() === 'POST') {
            $user = (string) $request->input('user');
            $pass = (string) $request->input('pass');

            $adminUser = env('ADMIN_USER');
            $adminHash = env('ADMIN_PASS_HASH');

            if ($user === '' || $pass === '') {
                $errors[] = __('admin.login.errors.credentials_required');
            } elseif ($adminUser === null || $adminHash === null) {
                $errors[] = __('admin.login.errors.not_configured');
            } else {
                $attemptsKey = 'admin_login_attempts';
                $lastAttemptKey = 'admin_login_last_attempt';

                if (!isset($_SESSION[$attemptsKey]) || (time() - ($_SESSION[$lastAttemptKey] ?? 0)) > 300) {
                    $_SESSION[$attemptsKey] = 0;
                }
                $_SESSION[$lastAttemptKey] = time();

                if ($_SESSION[$attemptsKey] >= 5) {
                    $errors[] = __('admin.login.errors.too_many_attempts');
                } elseif ($user === $adminUser && password_verify($pass, $adminHash)) {
                    session_start();
                    session_regenerate_id(true);
                    $_SESSION['is_admin'] = true;
                    $_SESSION[$attemptsKey] = 0;

                    return $this->redirect('/admin_manage_events.php');
                } else {
                    $_SESSION[$attemptsKey]++;
                    $errors[] = __('admin.login.errors.invalid_credentials');
                }
            }
        }

        return $this->view('admin/login', [
            'errors' => $errors,
        ]);
    }

    public function dashboard(Request $request): Response
    {
        session_start();
        if (empty($_SESSION['is_admin'])) {
            return $this->redirect('/admin_login.php');
        }

        return $this->redirect('/admin_manage_events.php');
    }

    public function manageClubs(Request $request): Response
    {
        session_start();
        if (empty($_SESSION['is_admin'])) {
            return $this->redirect('/admin_login.php');
        }

        $db = \App\Model\Database::connection();

        $delete = (int) ($request->query('delete') ?? 0);
        if ($delete > 0) {
            $db->prepare('DELETE FROM clubs WHERE id = ?')->execute([$delete]);
            return $this->redirect('/admin_manage_clubs.php');
        }

        $clubs = Club::all();

        return $this->view('admin/manage_clubs', [
            'clubs' => $clubs,
        ]);
    }

    public function manageEvents(Request $request): Response
    {
        session_start();
        if (empty($_SESSION['is_admin'])) {
            return $this->redirect('/admin_login.php');
        }

        $db = \App\Model\Database::connection();

        $delete = (int) ($request->query('delete') ?? 0);
        if ($delete > 0) {
            $db->prepare('DELETE FROM events WHERE id = ?')->execute([$delete]);
            return $this->redirect('/admin_manage_events.php');
        }

        $events = [];
        $rows = $db->query('SELECT * FROM events ORDER BY date DESC')->fetchAll();
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
        ]);
    }

    public function addEvent(Request $request): Response
    {
        session_start();
        if (empty($_SESSION['is_admin'])) {
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
            $date = trim((string) ($_POST['date'] ?? ''));
            if ($date === '') {
                $error = __('admin.add.errors.date_required');
            }

            $location = trim((string) ($_POST['location'] ?? ''));
            if ($error === '' && $location === '') {
                $error = __('admin.add.errors.location_required');
            }

            if ($error === '') {
                try {
                    $uploadDir = base_path('public/uploads/events/');
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $locandina = $event?->poster_file ?? null;
                    $informativa = $event?->info_file ?? null;

                    if (!empty($_FILES['poster_file']) && $_FILES['poster_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                        if ($_FILES['poster_file']['error'] !== UPLOAD_ERR_OK) {
                            throw new \Exception('Poster upload failed: ' . $this->uploadErrorMessage($_FILES['poster_file']['error']));
                        }
                        $ext = strtolower(pathinfo($_FILES['poster_file']['name'], PATHINFO_EXTENSION));
                        if (!in_array($ext, ['pdf','jpg','jpeg','png'], true)) {
                            throw new \Exception('Invalid poster format');
                        }
                        $safe = 'poster' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        $target = $uploadDir . $safe;
                        if (!move_uploaded_file($_FILES['poster_file']['tmp_name'], $target)) {
                            throw new \Exception('Unable to save poster');
                        }
                        $locandina = 'uploads/events/' . $safe;
                    }

                    if (!empty($_FILES['info_file']) && $_FILES['info_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                        if ($_FILES['info_file']['error'] !== UPLOAD_ERR_OK) {
                            throw new \Exception('Info file upload failed: ' . $this->uploadErrorMessage($_FILES['info_file']['error']));
                        }
                        $ext = strtolower(pathinfo($_FILES['info_file']['name'], PATHINFO_EXTENSION));
                        if (!in_array($ext, ['pdf','jpg','jpeg','png'], true)) {
                            throw new \Exception('Invalid info format');
                        }
                        $safe = 'info_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        $target = $uploadDir . $safe;
                        if (!move_uploaded_file($_FILES['info_file']['tmp_name'], $target)) {
                            throw new \Exception('Unable to save info file');
                        }
                        $informativa = 'uploads/events/' . $safe;
                    }

                    $published = isset($_POST['published']) ? 1 : 0;
                    $closed = isset($_POST['closed']) ? 1 : 0;

                    if ($event) {
                        $sql = "UPDATE events SET name=?, date=?, location=?, organizer=?, registration_deadline=?, type=?, description=?, notes=?, poster_file=?, info_file=?, published=?, closed=? WHERE id=?";
                        $params = [
                            trim($_POST['name'] ?? ''),
                            $date,
                            $location,
                            trim($_POST['organizer'] ?? ''),
                            trim($_POST['registration_deadline'] ?? ''),
                            trim($_POST['type'] ?? ''),
                            trim($_POST['description'] ?? ''),
                            trim($_POST['notes'] ?? ''),
                            $locandina,
                            $informativa,
                            $published,
                            $closed,
                            $eventId,
                        ];
                        $db->prepare($sql)->execute($params);
                    } else {
                        $db->prepare(
                            "INSERT INTO events (name, date, location, organizer, registration_deadline, type, description, notes, poster_file, info_file, published, closed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                        )->execute([
                            trim($_POST['name'] ?? ''),
                            $date,
                            $location,
                            trim($_POST['organizer'] ?? ''),
                            trim($_POST['registration_deadline'] ?? ''),
                            trim($_POST['type'] ?? ''),
                            trim($_POST['description'] ?? ''),
                            trim($_POST['notes'] ?? ''),
                            $locandina,
                            $informativa,
                            $published,
                            $closed,
                        ]);
                    }

                    return $this->redirect('/admin_manage_events.php');
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
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

    private function uploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary upload directory',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
            default => 'Unknown upload error (code: ' . $errorCode . ')',
        };
    }

    public function editClub(Request $request): Response
    {
        session_start();
        if (empty($_SESSION['is_admin'])) {
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
            try {
                $data = [
                    'name' => trim((string) ($_POST['name'] ?? '')),
                    'email' => trim((string) ($_POST['email'] ?? '')),
                    'phone' => trim((string) ($_POST['phone'] ?? '')),
                    'contact_first_name' => trim((string) ($_POST['contact_first_name'] ?? '')),
                    'contact_last_name' => trim((string) ($_POST['contact_last_name'] ?? '')),
                    'contact_phone' => trim((string) ($_POST['contact_phone'] ?? '')),
                    'contact_email' => trim((string) ($_POST['contact_email'] ?? '')),
                    'organization' => trim((string) ($_POST['organization'] ?? '')),
                    'recovery_email' => trim((string) ($_POST['recovery_email'] ?? '')),
                    'federal_code' => trim((string) ($_POST['federal_code'] ?? '')),
                ];

                $password = (string) ($_POST['password_hash'] ?? '');
                if ($password !== '') {
                    $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }

                Club::update($id, $data);

                return $this->redirect('/admin_manage_clubs.php');
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return $this->view('admin/edit_club', [
            'club' => $club,
            'error' => $error,
        ]);
    }

    public function logout(Request $request): Response
    {
        session_start();
        session_unset();
        session_destroy();

        return $this->redirect('/admin_login.php');
    }

    public function editEvent(Request $request): Response
    {
        session_start();
        if (empty($_SESSION['is_admin'])) {
            return $this->redirect('/admin_login.php');
        }

        $id = (int) ($request->input('id') ?? $request->query('id'));
        if ($id <= 0) {
            return $this->redirect('/admin_manage_events.php');
        }

        return $this->redirect('/admin_add_event.php?event_id=' . $id);
    }
}
