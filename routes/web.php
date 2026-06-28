<?php

declare(strict_types=1);

use App\Controller\ClubAreaController;
use App\Controller\ClubController;
use App\Controller\HomeController;
use App\Controller\EventController;
use App\Core\Request;
use App\Core\Response;
use App\Core\HttpException;

return static function (App\Core\Router $router): void {
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/index.php', [HomeController::class, 'index']);
    $router->get('/about', [HomeController::class, 'about']);

    $router->get('/club_register.php', [ClubController::class, 'register']);
    $router->post('/club_register.php', [ClubController::class, 'register']);

    $router->get('/club_login.php', [ClubController::class, 'login']);
    $router->post('/club_login.php', [ClubController::class, 'login']);

    $router->get('/club_forgot_password.php', [ClubController::class, 'forgotPassword']);
    $router->post('/club_forgot_password.php', [ClubController::class, 'forgotPassword']);

    $router->get('/club_reset_password.php', [ClubController::class, 'resetPassword']);
    $router->post('/club_reset_password.php', [ClubController::class, 'resetPassword']);

    $router->get('/clubs.php', [ClubController::class, 'list']);

    $router->post('/club_logout.php', [ClubController::class, 'logout']);

    $router->get('/club_area.php', [ClubAreaController::class, 'index']);
    $router->post('/club_area.php', [ClubAreaController::class, 'index']);
    $router->post('/club_delete_athlete.php', [ClubAreaController::class, 'deleteAthlete']);

    // Events managed by MVC
    $router->get('/events.php', [EventController::class, 'index']);
    $router->get('/event_details.php', [EventController::class, 'show']);
    $router->get('/event_register.php', [EventController::class, 'register']);
    $router->post('/event_register.php', [EventController::class, 'register']);

    // Admin MVC routes
    $router->get('/admin_login.php', [\App\Controller\AdminController::class, 'login']);
    $router->post('/admin_login.php', [\App\Controller\AdminController::class, 'login']);
    $router->get('/admin.php', [\App\Controller\AdminController::class, 'dashboard']);
    $router->get('/admin_manage_clubs.php', [\App\Controller\AdminController::class, 'manageClubs']);
    $router->post('/admin_delete_club.php', [\App\Controller\AdminController::class, 'deleteClub']);
    $router->get('/admin_add_event.php', [\App\Controller\AdminController::class, 'addEvent']);
    $router->post('/admin_add_event.php', [\App\Controller\AdminController::class, 'addEvent']);
    $router->get('/admin_manage_events.php', [\App\Controller\AdminController::class, 'manageEvents']);
    $router->post('/admin_delete_event.php', [\App\Controller\AdminController::class, 'deleteEvent']);
    $router->get('/admin_edit_club.php', [\App\Controller\AdminController::class, 'editClub']);
    $router->post('/admin_edit_club.php', [\App\Controller\AdminController::class, 'editClub']);
    $router->get('/admin_edit_event.php', [\App\Controller\AdminController::class, 'editEvent']);
    $router->post('/admin_edit_event.php', [\App\Controller\AdminController::class, 'editEvent']);

    $router->post('/admin_logout.php', [\App\Controller\AdminController::class, 'logout']);

    $router->get('/language/switch', [App\Controller\LanguageController::class, 'switch']);
};
