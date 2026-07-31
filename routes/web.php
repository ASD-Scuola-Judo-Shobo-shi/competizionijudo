<?php

declare(strict_types=1);

use App\Controller\ClubAreaController;
use App\Controller\ClubController;
use App\Controller\AboutController;
use App\Controller\HealthController;
use App\Controller\EventController;
use App\Controller\MigrationWebhookController;
use App\Core\Request;
use App\Core\Response;
use App\Core\HttpException;
use App\Core\AuthContext;

return static function (App\Core\Router $router): void {
    $router->get('/', fn() => new Response('', 302, ['Location' => base_url('/events')]));
    $router->get('/index.php', [AboutController::class, 'index']);
    $router->get('/about', [AboutController::class, 'about']);
    $router->get('/privacy', [AboutController::class, 'privacy']);
    $router->get('/health', [HealthController::class, 'show']);
    $router->post('/migrations', [MigrationWebhookController::class, 'run']);

    $router->get('/clubs/register', [ClubController::class, 'register']);
    $router->post('/clubs/register', [ClubController::class, 'register']);
    $router->get('/clubs/confirm-registration', [ClubController::class, 'confirmRegistration']);

    $router->get('/clubs/login', [ClubController::class, 'login']);
    $router->post('/clubs/login', [ClubController::class, 'login']);

    $router->get('/clubs/forgot-password', [ClubController::class, 'forgotPassword']);
    $router->post('/clubs/forgot-password', [ClubController::class, 'forgotPassword']);

    $router->get('/clubs/reset-password', [ClubController::class, 'resetPassword']);
    $router->post('/clubs/reset-password', [ClubController::class, 'resetPassword']);

    $router->get('/clubs', [ClubController::class, 'list']);

    $router->post('/clubs/logout', [ClubController::class, 'logout'], AuthContext::AUTHENTICATED);

    $router->get('/clubs/area', [ClubAreaController::class, 'index'], AuthContext::CLUB);
    $router->post('/clubs/area', [ClubAreaController::class, 'index'], AuthContext::CLUB);
    $router->post('/clubs/delete-athlete', [ClubAreaController::class, 'deleteAthlete'], AuthContext::CLUB);
    $router->get('/clubs/athletes-export', [ClubAreaController::class, 'exportAthletes'], AuthContext::CLUB);
    $router->post('/clubs/athletes-import', [ClubAreaController::class, 'importAthletes'], AuthContext::CLUB);

    // Events managed by MVC
    $router->get('/events', [EventController::class, 'index']);
    $router->get('/events/details', [EventController::class, 'details']);
    $router->get('/events/entries', [EventController::class, 'entries']);
    $router->get('/events/register', [EventController::class, 'register'], AuthContext::CLUB);
    $router->post('/events/register', [EventController::class, 'register'], AuthContext::CLUB);

    // Admin MVC routes
    $router->get('/admin/login', [\App\Controller\AdminController::class, 'login']);
    $router->post('/admin/login', [\App\Controller\AdminController::class, 'login']);
    $router->get('/admin', [\App\Controller\AdminController::class, 'dashboard'], AuthContext::ADMINISTRATOR);
    $router->get('/admin/clubs', [\App\Controller\AdminController::class, 'manageClubs'], AuthContext::ADMINISTRATOR);
    $router->get('/admin/clubs/athletes', [\App\Controller\AdminController::class, 'clubAthletes'], AuthContext::ADMINISTRATOR);
    $router->get('/admin/clubs/athletes/export', [\App\Controller\AdminController::class, 'exportClubAthletes'], AuthContext::ADMINISTRATOR);
    $router->post('/admin/clubs/delete', [\App\Controller\AdminController::class, 'deleteClub'], AuthContext::ADMINISTRATOR);
    $router->get('/admin/events/add', [\App\Controller\AdminController::class, 'addEvent'], AuthContext::ADMINISTRATOR);
    $router->post('/admin/events/add', [\App\Controller\AdminController::class, 'addEvent'], AuthContext::ADMINISTRATOR);
    $router->get('/admin/events', [\App\Controller\AdminController::class, 'manageEvents'], AuthContext::ADMINISTRATOR);
    $router->get('/admin/events/export', [\App\Controller\AdminController::class, 'exportEventEntries'], AuthContext::ADMINISTRATOR);
    $router->post('/admin/events/delete', [\App\Controller\AdminController::class, 'deleteEvent'], AuthContext::ADMINISTRATOR);
    $router->get('/admin/clubs/edit', [\App\Controller\AdminController::class, 'editClub'], AuthContext::ADMINISTRATOR);
    $router->post('/admin/clubs/edit', [\App\Controller\AdminController::class, 'editClub'], AuthContext::ADMINISTRATOR);
    $router->get('/admin/events/edit', [\App\Controller\AdminController::class, 'editEvent'], AuthContext::ADMINISTRATOR);
    $router->post('/admin/events/edit', [\App\Controller\AdminController::class, 'editEvent'], AuthContext::ADMINISTRATOR);

    $router->post('/admin/logout', [\App\Controller\AdminController::class, 'logout'], AuthContext::AUTHENTICATED);

    $router->get('/language/switch', [App\Controller\LanguageController::class, 'switch']);
};
