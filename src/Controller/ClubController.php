<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Model\Affiliation;
use App\Model\Club;
use App\Model\ClubRegistrationConfirmation;
use App\Model\Database;
use App\Model\SardinianLocation;
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
        $confirmationLink = null;
        $formData = [
            'name' => '',
            'federal_code' => '',
            'email' => '',
            'phone' => '',
            'address_line' => '',
            'postal_code' => '',
            'city' => '',
            'province' => '',
            'contact_first_name' => '',
            'contact_last_name' => '',
            'affiliation' => [],
            'athlete_data_rights_declaration' => false,
        ];

        if ($request->method() === 'POST') {
            validate_csrf((string) $request->post('csrf_token'));
            $formData = [
                'name' => trim((string) $request->post('name')),
                'federal_code' => trim((string) $request->post('federal_code')),
                'email' => Club::normalizeEmail((string) $request->post('email')),
                'phone' => trim((string) $request->post('phone')),
                'address_line' => trim((string) $request->post('address_line')),
                'postal_code' => trim((string) $request->post('postal_code')),
                'city' => trim((string) $request->post('city')),
                'province' => trim((string) $request->post('province')),
                'contact_first_name' => trim((string) $request->post('contact_first_name')),
                'contact_last_name' => trim((string) $request->post('contact_last_name')),
                'affiliation' => Affiliation::selected($request->post('affiliation')),
                'athlete_data_rights_declaration' => $request->post('athlete_data_rights_declaration') === '1',
            ];
            $password = (string) $request->post('password');
            $password2 = (string) $request->post('password2');

            foreach (
                ClubInputValidator::registrationErrors(
                    $formData['name'],
                    $formData['federal_code'],
                    $formData['email'],
                    $formData['phone'],
                    $formData['address_line'],
                    $formData['postal_code'],
                    $formData['province'],
                    $formData['city'],
                    $formData['athlete_data_rights_declaration']
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
                    if (Club::findByName($formData['name']) !== null) {
                        $errors[] = __('club.register.errors.club_exists');
                    } else {
                        $token = ClubRegistrationConfirmation::issue([
                            'federal_code' => $formData['federal_code'],
                            'name' => $formData['name'],
                            'email' => $formData['email'],
                            'phone' => $formData['phone'],
                            'address_line' => $formData['address_line'],
                            'postal_code' => $formData['postal_code'],
                            'city' => $formData['city'],
                            'province' => $formData['province'],
                            'contact_first_name' => $formData['contact_first_name'],
                            'contact_last_name' => $formData['contact_last_name'],
                            'affiliation' => Affiliation::encode($formData['affiliation']),
                            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        ]);
                        $confirmationUrl = sprintf(
                            '%s/club_confirm_registration.php?token=%s',
                            rtrim((string) env('APP_URL', 'http://localhost:8080'), '/'),
                            $token
                        );
                        if ($this->canExposeResetLink()) {
                            $confirmationLink = $confirmationUrl;
                        } else {
                            $this->passwordResetMailer()->sendRegistrationConfirmationLink(
                                $formData['email'],
                                $confirmationUrl
                            );
                        }

                        $success = __('club.register.confirmation_sent');
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
            'title' => __('club.register.title'),
            'errors' => $errors,
            'success' => $success,
            'confirmation_link' => $confirmationLink,
            'formData' => $formData,
            'sardinianLocations' => SardinianLocation::all(),
            'sardinianPostalCodes' => SardinianLocation::postalCodes(),
            'affiliationOptions' => Affiliation::options(),
        ]);
    }

    public function confirmRegistration(Request $request): Response
    {
        $token = (string) $request->query('token', '');
        $success = false;
        $error = null;

        if ($token === '') {
            $error = __('club.confirm_registration.invalid_token');
        } else {
            try {
                $success = ClubRegistrationConfirmation::confirm($token);
                if (!$success) {
                    $error = __('club.confirm_registration.invalid_token');
                }
            } catch (\Throwable $exception) {
                $this->reportFailure('club.registration_confirmation_failed', $exception, $request);
                $error = __('club.confirm_registration.failed');
            }
        }

        return $this->view('club/confirm_registration', [
            'title' => __('club.confirm_registration.title'),
            'success' => $success,
            'error' => $error,
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
                            Session::authenticateClub($club->id);

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
            'title' => __('club.login.title'),
            'errors' => $errors,
        ]);
    }

    public function list(Request $request): Response
    {
        $page = max(1, (int) $request->query('page', '1'));
        $pagination = paginate(Club::count(), $page, 50);
        $clubs = Club::page($pagination['per_page'], $pagination['offset']);

        return $this->view('club/list', [
            'title' => __('club.list'),
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
            'title' => __('club.forgot_password.title'),
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
            'title' => __('club.reset_password.title'),
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
