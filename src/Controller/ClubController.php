<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Model\Club;
use App\Model\Database;

final class ClubController extends Controller
{
    public function register(Request $request): Response
    {
        $errors = [];
        $success = null;

        if ($request->method() === 'POST') {
            $name = trim((string) $request->input('name'));
            $federalCode = trim((string) $request->input('federal_code'));
            $email = trim((string) $request->input('email'));
            $phone = trim((string) $request->input('phone'));
            $contact = trim((string) $request->input('contact'));
            $password = (string) $request->input('password');
            $password2 = (string) $request->input('password2');

            if ($name === '') {
                $errors[] = __('club.register.errors.club_name_required');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = __('club.register.errors.valid_email_required');
            } elseif ($password === '') {
                $errors[] = __('club.register.errors.password_required');
            } elseif ($password !== $password2) {
                $errors[] = __('club.register.errors.password_mismatch');
            }

            if ($errors === []) {
                try {
                    if (Club::findByName($name) !== null) {
                        $errors[] = __('club.register.errors.club_exists');
                    } else {
                        $club = Club::add([
                            'federal_code' => $federalCode,
                            'name' => $name,
                            'email' => $email,
                            'phone' => $phone,
                            'contact_first_name' => $contact,
                            'contact_last_name' => '-',
                            'contact_phone' => $phone,
                            'contact_email' => $email,
                            'organization' => 'FIJLKAM',
                            'recovery_email' => $email,
                            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        ]);

                        $success = __('club.register.success_message');
                    }
                } catch (\Throwable $exception) {
                    $errors[] = str_replace('{message}', $exception->getMessage(), __('club.register.errors.registration_failed'));
                }
            }
        }

        return $this->view('club/register', [
            'errors' => $errors,
            'success' => $success,
        ]);
    }

    public function login(Request $request): Response
    {
        $errors = [];

        if ($request->method() === 'POST') {
            $email = trim((string) $request->input('email'));
            $password = (string) $request->input('password');

            if ($email === '' || $password === '') {
                $errors[] = __('club.login.errors.credentials_required');
            } else {
                try {
                    $attemptsKey = 'club_login_attempts';
                    $lastAttemptKey = 'club_login_last_attempt';

                    if (!isset($_SESSION[$attemptsKey]) || (time() - ($_SESSION[$lastAttemptKey] ?? 0)) > 300) {
                        $_SESSION[$attemptsKey] = 0;
                    }
                    $_SESSION[$lastAttemptKey] = time();

                    if ($_SESSION[$attemptsKey] >= 5) {
                        $errors[] = __('club.login.errors.too_many_attempts');
                    } else {
                        $club = Club::findByEmail($email);

                        if ($club === null || !password_verify($password, $club->password_hash)) {
                            $_SESSION[$attemptsKey]++;
                            $errors[] = __('club.login.errors.invalid_credentials');
                        } else {
                            session_start();
                            session_regenerate_id(true);
                            $_SESSION['club_id'] = $club->id;
                            $_SESSION[$attemptsKey] = 0;

                            return $this->redirect('/club_area.php?view=list');
                        }
                    }
                } catch (\Throwable $exception) {
                    $errors[] = str_replace('{message}', $exception->getMessage(), __('club.login.errors.login_failed'));
                }
            }
        }

        return $this->view('club/login', [
            'errors' => $errors,
        ]);
    }

    public function list(Request $request): Response
    {
        $clubs = Club::all();

        return $this->view('club/list', [
            'clubs' => $clubs,
        ]);
    }

    public function logout(Request $request): Response
    {
        session_start();
        session_unset();
        session_destroy();

        return $this->redirect('/club_login.php');
    }

    public function forgotPassword(Request $request): Response
    {
        $errors = [];
        $success = null;
        $devLink = null;

        if ($request->method() === 'POST') {
            $email = trim((string) $request->input('email'));

            if ($email === '') {
                $errors[] = __('club.forgot_password.errors.email_required');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = __('club.forgot_password.errors.valid_email_required');
            } else {
                try {
                    $club = Club::findByEmail($email);

                    if ($club !== null) {
                        $rawToken = bin2hex(random_bytes(32));
                        $tokenHash = hash('sha256', $rawToken);
                        $expiresAt = (new \DateTime('now', new \DateTimeZone('UTC')))->modify('+1 hour')->format('Y-m-d H:i:s');

                        $db = Database::connection();
                        $db->prepare('UPDATE password_reset_tokens SET used = 1 WHERE club_id = ? AND used = 0')->execute([$club->id]);

                        $db->prepare(
                            'INSERT INTO password_reset_tokens (club_id, token_hash, expires_at) VALUES (?, ?, ?)'
                        )->execute([$club->id, $tokenHash, $expiresAt]);

                        $resetUrl = sprintf(
                            '%s/club_reset_password.php?token=%s',
                            rtrim((string) env('APP_URL', 'http://localhost:8080'), '/'),
                            $rawToken
                        );

                        $success = __('club.forgot_password.success_message');
                        $devLink = $resetUrl;
                    } else {
                        $errors[] = __('club.forgot_password.errors.email_not_found');
                    }
                } catch (\Throwable $exception) {
                    $errors[] = str_replace('{message}', $exception->getMessage(), __('club.forgot_password.errors.request_failed'));
                }
            }
        }

        return $this->view('club/forgot_password', [
            'errors' => $errors,
            'success' => $success,
            'dev_link' => $devLink,
        ]);
    }

    public function resetPassword(Request $request): Response
    {
        $errors = [];
        $token = '';
        $valid = false;

        if ($request->method() === 'GET') {
            $token = (string) $request->query('token', '');
        } elseif ($request->method() === 'POST') {
            $token = (string) $request->input('token', '');
        }

        if ($token !== '') {
            $tokenHash = hash('sha256', $token);
            $stmt = Database::connection()->prepare(
                'SELECT * FROM password_reset_tokens WHERE token_hash = ? AND used = 0'
            );
            $stmt->execute([$tokenHash]);
            $row = $stmt->fetch();

            if ($row) {
                $expiresAt = new \DateTime($row['expires_at'], new \DateTimeZone('UTC'));
                $now = new \DateTime('now', new \DateTimeZone('UTC'));

                if ($expiresAt > $now) {
                    $valid = true;
                    $club = Club::findById($row['club_id']);
                    $email = $club !== null ? (string) $club->email : '';
                }
            }
        }

        if ($request->method() === 'POST') {
            if (!$valid) {
                $errors[] = __('club.reset_password.errors.invalid_token');
            } else {
                $password = (string) $request->input('password');
                $password2 = (string) $request->input('password2');

                if ($password === '' || $password2 === '') {
                    $errors[] = __('club.reset_password.errors.password_required');
                } elseif ($password !== $password2) {
                    $errors[] = __('club.reset_password.errors.password_mismatch');
                } else {
                    try {
                        $stmt = Database::connection()->prepare(
                            'SELECT club_id FROM password_reset_tokens WHERE token_hash = ? AND used = 0'
                        );
                        $stmt->execute([$tokenHash]);
                        $tokenRow = $stmt->fetch();

                        if ($tokenRow) {
                            $clubId = (int) $tokenRow['club_id'];
                            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                            $stmt = Database::connection()->prepare(
                                'UPDATE clubs SET password_hash = ? WHERE id = ?'
                            );
                            $stmt->execute([$passwordHash, $clubId]);

                            $stmt = Database::connection()->prepare(
                                'UPDATE password_reset_tokens SET used = 1 WHERE token_hash = ?'
                            );
                            $stmt->execute([$tokenHash]);

                            return $this->redirect('/club_login.php');
                        }
                    } catch (\Throwable $exception) {
                        $errors[] = str_replace('{message}', $exception->getMessage(), __('club.reset_password.errors.reset_failed'));
                    }
                }
            }
        }

        if (!$valid && $request->method() === 'GET') {
            $errors[] = __('club.reset_password.errors.invalid_token');
        }

        return $this->view('club/reset_password', [
            'errors' => $errors,
            'token' => $token,
            'valid' => $valid,
            'email' => $email ?? '',
        ]);
    }
}
