<?php

declare(strict_types=1);

use App\Controller\ClubAreaController;
use App\Controller\ClubController;
use App\Controller\HomeController;
use App\Controller\HealthController;
use App\Controller\EventController;
use App\Controller\MigrationWebhookController;
use App\Core\Request;
use App\Core\Response;
use App\Core\HttpException;
use App\Core\AuthContext;

return static function (App\Core\Router $router): void {
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/index.php', [HomeController::class, 'index']);
    $router->get('/about', [HomeController::class, 'about']);
    $router->get('/privacy', [HomeController::class, 'privacy']);
    $router->get('/health', [HealthController::class, 'show']);
    $router->post('/migrations', [MigrationWebhookController::class, 'run']);

    $router->get('/club_register.php', [ClubController::class, 'register']);
    $router->post('/club_register.php', [ClubController::class, 'register']);

    $router->get('/club_login.php', [ClubController::class, 'login']);
    $router->post('/club_login.php', [ClubController::class, 'login']);

    $router->get('/club_forgot_password.php', [ClubController::class, 'forgotPassword']);
    $router->post('/club_forgot_password.php', [ClubController::class, 'forgotPassword']);

    $router->get('/club_reset_password.php', [ClubController::class, 'resetPassword']);
    $router->post('/club_reset_password.php', [ClubController::class, 'resetPassword']);

    $router->get('/clubs.php', [ClubController::class, 'list']);

    $router->post('/club_logout.php', [ClubController::class, 'logout'], AuthContext::AUTHENTICATED);

    $router->get('/club_area.php', [ClubAreaController::class, 'index'], AuthContext::CLUB);
    $router->post('/club_area.php', [ClubAreaController::class, 'index'], AuthContext::CLUB);
    $router->post('/club_delete_athlete.php', [ClubAreaController::class, 'deleteAthlete'], AuthContext::CLUB);
    $router->get('/club_athletes_export.csv', [ClubAreaController::class, 'exportAthletes'], AuthContext::CLUB);
    $router->post('/club_athletes_import.php', [ClubAreaController::class, 'importAthletes'], AuthContext::CLUB);

    // Events managed by MVC
    $router->get('/events.php', [EventController::class, 'index']);
    $router->get('/event_details.php', [EventController::class, 'show']);
    $router->get('/event_entries.php', [EventController::class, 'entries'], AuthContext::AUTHENTICATED);
    $router->get('/event_register.php', [EventController::class, 'register'], AuthContext::CLUB);
    $router->post('/event_register.php', [EventController::class, 'register'], AuthContext::CLUB);

    // Admin MVC routes
    $router->get('/admin_login.php', [\App\Controller\AdminController::class, 'login']);
    $router->post('/admin_login.php', [\App\Controller\AdminController::class, 'login']);
    $router->get('/admin.php', [\App\Controller\AdminController::class, 'dashboard'], AuthContext::ADMINISTRATOR);
    $router->get('/admin_manage_clubs.php', [\App\Controller\AdminController::class, 'manageClubs'], AuthContext::ADMINISTRATOR);
    $router->post('/admin_delete_club.php', [\App\Controller\AdminController::class, 'deleteClub'], AuthContext::ADMINISTRATOR);
    $router->get('/admin_add_event.php', [\App\Controller\AdminController::class, 'addEvent'], AuthContext::ADMINISTRATOR);
    $router->post('/admin_add_event.php', [\App\Controller\AdminController::class, 'addEvent'], AuthContext::ADMINISTRATOR);
    $router->get('/admin_manage_events.php', [\App\Controller\AdminController::class, 'manageEvents'], AuthContext::ADMINISTRATOR);
    $router->post('/admin_delete_event.php', [\App\Controller\AdminController::class, 'deleteEvent'], AuthContext::ADMINISTRATOR);
    $router->get('/admin_edit_club.php', [\App\Controller\AdminController::class, 'editClub'], AuthContext::ADMINISTRATOR);
    $router->post('/admin_edit_club.php', [\App\Controller\AdminController::class, 'editClub'], AuthContext::ADMINISTRATOR);
    $router->get('/admin_edit_event.php', [\App\Controller\AdminController::class, 'editEvent'], AuthContext::ADMINISTRATOR);
    $router->post('/admin_edit_event.php', [\App\Controller\AdminController::class, 'editEvent'], AuthContext::ADMINISTRATOR);

    $router->post('/admin_logout.php', [\App\Controller\AdminController::class, 'logout'], AuthContext::AUTHENTICATED);

    $router->get('/language/switch', [App\Controller\LanguageController::class, 'switch']);
};
