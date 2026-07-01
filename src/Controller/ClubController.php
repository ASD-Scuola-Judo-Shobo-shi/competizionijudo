<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Model\Club;
use App\Model\Database;
use App\Security\AuthenticationThrottle;
use App\Security\DatabaseAuthenticationThrottle;
use App\Security\PasswordPolicy;
use App\Service\DatabasePasswordResetTokenIssuer;
use App\Service\DatabasePasswordResetRepository;
use App\Service\PasswordResetMailer;
use App\Service\PasswordResetMailerFactory;
use App\Service\PasswordResetTokenIssuer;
use App\Service\PasswordResetRepository;
use App\Validation\ClubInputValidator;
use PDOException;

final class ClubController extends Controller
{
    private readonly PasswordResetTokenIssuer $passwordResetTokens;
    private ?PasswordResetMailer $passwordResetMailer;
    private ?AuthenticationThrottle $authenticationThrottle;
    private ?PasswordResetRepository $passwordResetRepository;

    public function __construct(
        View $view,
        Request $request,
        ?PasswordResetTokenIssuer $passwordResetTokens = null,
        ?AuthenticationThrottle $authenticationThrottle = null,
        ?PasswordResetRepository $passwordResetRepository = null,
        ?Logger $logger = null,
        ?PasswordResetMailer $passwordResetMailer = null
    ) {
        parent::__construct($view, $request, $logger);
        $this->passwordResetTokens = $passwordResetTokens ?? new DatabasePasswordResetTokenIssuer();
        $this->passwordResetMailer = $passwordResetMailer;
        $this->authenticationThrottle = $authenticationThrottle;
        $this->passwordResetRepository = $passwordResetRepository;
    }

    public function register(Request $request): Response
    {
        $errors = [];
        $success = null;

        if ($request->method() === 'POST') {
            validate_csrf((string) $request->post('csrf_token'));
            $name = trim((string) $request->post('name'));
            $federalCode = trim((string) $request->post('federal_code'));
            $email = Club::normalizeEmail((string) $request->post('email'));
            $phone = trim((string) $request->post('phone'));
            $contact = trim((string) $request->post('contact'));
            $password = (string) $request->post('password');
            $password2 = (string) $request->post('password2');
            $athleteDataRightsDeclared = $request->post('athlete_data_rights_declaration') === '1';

            foreach (
                ClubInputValidator::registrationErrors(
                    $name,
                    $federalCode,
                    $email,
                    $athleteDataRightsDeclared
                ) as $key
            ) {
                $errors[] = __($key);
            }

            if ($password === '') {
                $errors[] = __('club.register.errors.password_required');
            } elseif ($password !== $password2) {
                $errors[] = __('club.register.errors.password_mismatch');
            } elseif (!PasswordPolicy::accepts($password)) {
                $errors[] = $this->passwordPolicyError();
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
                    $this->reportFailure('club.registration_failed', $exception, $request);
                    $errors[] = $exception instanceof PDOException && (string) $exception->getCode() === '23000'
                        ? __('errors.account_conflict')
                        : __('errors.save_failed');
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
            validate_csrf((string) $request->post('csrf_token'));
            $email = Club::normalizeEmail((string) $request->post('email'));
            $password = (string) $request->post('password');

            if ($email === '' || $password === '') {
                $errors[] = __('club.login.errors.credentials_required');
            } else {
                try {
                    $networkSignal = $this->networkSignal($request);
                    $throttle = $this->authenticationThrottle();

                    if ($throttle->isBlocked('club-login', $email, $networkSignal)) {
                        $errors[] = __('club.login.errors.too_many_attempts');
                    } else {
                        $club = Club::findByEmail($email);

                        if ($club === null || !password_verify($password, $club->password_hash)) {
                            $throttle->recordAttempt('club-login', $email, $networkSignal);
                            $errors[] = __('club.login.errors.invalid_credentials');
                        } else {
                            $throttle->clear('club-login', $email, $networkSignal);
                            Session::regenerate();
                            Session::set('club_id', $club->id);

                            return $this->redirect('/club_area.php?view=list');
                        }
                    }
                } catch (\Throwable $exception) {
                    $this->reportFailure('club.login_failed', $exception, $request);
                    $errors[] = __('club.login.errors.login_failed');
                }
            }
        }

        return $this->view('club/login', [
            'errors' => $errors,
        ]);
    }

    public function list(Request $request): Response
    {
        $page = max(1, (int) $request->query('page', '1'));
        $pagination = paginate(Club::count(), $page, 50);
        $clubs = Club::page($pagination['per_page'], $pagination['offset']);

        return $this->view('club/list', [
            'clubs' => $clubs,
            'pagination' => $pagination,
        ]);
    }

    public function logout(Request $request): Response
    {
        validate_csrf((string) $request->post('csrf_token'));
        Session::destroy();

        return $this->redirect('/club_login.php');
    }

    public function forgotPassword(Request $request): Response
    {
        $errors = [];
        $success = null;
        $devLink = null;

        if ($request->method() === 'POST') {
            validate_csrf((string) $request->post('csrf_token'));
            $email = Club::normalizeEmail((string) $request->post('email'));

            if ($email === '') {
                $errors[] = __('club.forgot_password.errors.email_required');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = __('club.forgot_password.errors.valid_email_required');
            } else {
                try {
                    $networkSignal = $this->networkSignal($request);
                    $throttle = $this->authenticationThrottle();
                    $canExposeResetLink = $this->canExposeResetLink();
                    $success = __('club.forgot_password.success_message');

                    if (!$throttle->isBlocked('password-reset', $email, $networkSignal)) {
                        $throttle->recordAttempt('password-reset', $email, $networkSignal);

                        $rawToken = $this->passwordResetTokens->issueForEmail($email);
                        if ($rawToken !== null) {
                            $resetUrl = sprintf(
                                '%s/club_reset_password.php?token=%s',
                                rtrim((string) env('APP_URL', 'http://localhost:8080'), '/'),
                                $rawToken
                            );
                            if ($canExposeResetLink) {
                                $devLink = $resetUrl;
                            } else {
                                try {
                                    $this->passwordResetMailer()->sendResetLink($email, $resetUrl);
                                } catch (\Throwable $exception) {
                                    $this->reportFailure(
                                        'club.password_reset_delivery_failed',
                                        $exception,
                                        $request
                                    );
                                }
                            }
                        }
                    }
                } catch (\Throwable $exception) {
                    $this->reportFailure('club.password_reset_request_failed', $exception, $request);
                    $success = __('club.forgot_password.success_message');
                }
            }
        }

        return $this->view('club/forgot_password', [
            'errors' => $errors,
            'success' => $success,
            'dev_link' => $devLink,
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

    private function canExposeResetLink(): bool
    {
        return strtolower((string) env('APP_ENV', 'production')) === 'local'
            && filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL) === true
            && filter_var(env('APP_TEST_RESET_LINKS', false), FILTER_VALIDATE_BOOL) === true;
    }

    private function passwordResetMailer(): PasswordResetMailer
    {
        return $this->passwordResetMailer ??= PasswordResetMailerFactory::fromEnvironment();
    }

    public function resetPassword(Request $request): Response
    {
        $errors = [];
        $token = '';
        $valid = false;
        $email = '';

        if ($request->method() === 'GET') {
            $token = (string) $request->query('token', '');
        } elseif ($request->method() === 'POST') {
            $token = (string) $request->input('token', '');
            validate_csrf((string) $request->post('csrf_token'));
        }

        if ($token !== '') {
            $email = $this->passwordResetRepository()->findValidEmail(hash('sha256', $token)) ?? '';
            $valid = $email !== '';
        }

        if ($request->method() === 'POST') {
            if (!$valid) {
                $errors[] = __('club.reset_password.errors.invalid_token');
            } else {
                $password = (string) $request->post('password');
                $password2 = (string) $request->post('password2');

                if ($password === '' || $password2 === '') {
                    $errors[] = __('club.reset_password.errors.password_required');
                } elseif ($password !== $password2) {
                    $errors[] = __('club.reset_password.errors.password_mismatch');
                } elseif (!PasswordPolicy::accepts($password)) {
                    $errors[] = $this->passwordPolicyError();
                } else {
                    try {
                        if (
                            $this->passwordResetRepository()->consume(
                                hash('sha256', $token),
                                password_hash($password, PASSWORD_DEFAULT)
                            )
                        ) {
                            return $this->redirect('/club_login.php');
                        }

                        $valid = false;
                        $errors[] = __('club.reset_password.errors.invalid_token');
                    } catch (\Throwable $exception) {
                        $this->reportFailure('club.password_reset_failed', $exception, $request);
                        $errors[] = __('club.reset_password.errors.reset_failed');
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
            'email' => $email,
        ]);
    }

    private function passwordResetRepository(): PasswordResetRepository
    {
        return $this->passwordResetRepository ??= new DatabasePasswordResetRepository(Database::connection());
    }

    private function passwordPolicyError(): string
    {
        return __('errors.password_too_short', [
            'minimum' => (string) PasswordPolicy::MINIMUM_LENGTH,
        ]);
    }
}
