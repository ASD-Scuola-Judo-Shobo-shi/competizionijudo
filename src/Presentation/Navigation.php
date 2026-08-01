<?php

declare(strict_types=1);

namespace App\Presentation;

final class Navigation
{
    private const STATIC_PATHS = [
        '/',
        '/about',
        '/privacy',
    ];
    private const EVENT_PATHS = [
        '/events',
        '/events/details',
        '/events/entries',
        '/events/register',
    ];
    private const CLUB_PATHS = [
        '/clubs/register',
        '/clubs/login',
        '/clubs/forgot-password',
        '/clubs/reset-password',
        '/clubs/area',
        '/clubs',
    ];
    private const ADMIN_PATHS = [
        '/admin/login',
        '/admin',
        '/admin/clubs',
        '/admin/clubs/athletes',
        '/admin/clubs/athletes/export',
        '/admin/maintenance/athlete-duplicates',
        '/admin/events',
        '/admin/events/add',
        '/admin/clubs/edit',
        '/admin/events/edit',
        '/admin/logout',
    ];

    /** @return array<string, mixed> */
    public static function context(string $currentPath, string $clubView, bool $isAdmin, bool $isLoggedIn): array
    {
        return [
            'currentPath' => $currentPath,
            'clubView' => $clubView,
            'aboutActive' => in_array($currentPath, self::STATIC_PATHS, true),
            'eventsActive' => in_array($currentPath, self::EVENT_PATHS, true),
            'clubsActive' => in_array($currentPath, self::CLUB_PATHS, true),
            'adminActive' => in_array($currentPath, self::ADMIN_PATHS, true),
            'clubUrl' => base_url($isLoggedIn ? '/clubs/area' : '/clubs/login'),
            'submenuItems' => self::submenu($currentPath, $isAdmin, $isLoggedIn),
        ];
    }

    /** @return list<array{label: string, url: string, paths: list<string>, method?: 'post', query?: array<string, list<string>>}> */
    public static function submenu(string $currentPath, bool $isAdmin, bool $isLoggedIn): array
    {
        $showSubmenu = in_array($currentPath, self::EVENT_PATHS, true)
            || in_array($currentPath, self::CLUB_PATHS, true)
            || in_array($currentPath, self::ADMIN_PATHS, true);
        if (!$showSubmenu) {
            return [];
        }

        if (in_array($currentPath, self::EVENT_PATHS, true)) {
            $items = [
                ['label' => translate('events.submenu.list'), 'url' => base_url('/events'), 'paths' => ['/events']],
                ['label' => translate('events.submenu.details'), 'url' => base_url('/events/details'), 'paths' => ['/events/details']],
            ];
            // Entries link is now visible to all visitors
            $items[] = ['label' => translate('events.submenu.entries'), 'url' => base_url('/events/entries'), 'paths' => ['/events/entries']];
            $items[] = ['label' => translate('events.submenu.registration'), 'url' => base_url('/events/register'), 'paths' => ['/events/register']];

            return $items;
        }

        if (in_array($currentPath, self::ADMIN_PATHS, true)) {
            if (!$isAdmin) {
                return [['label' => translate('nav.login'), 'url' => base_url('/admin/login'), 'paths' => ['/admin/login']]];
            }

            $items = [
                [
                    'label' => translate('admin.submenu.manage_clubs'),
                    'url' => base_url('/admin/clubs'),
                    'paths' => ['/admin/clubs', '/admin/clubs/edit', '/admin/clubs/athletes'],
                ],
                ['label' => translate('admin.submenu.manage_events'), 'url' => base_url('/admin/events'), 'paths' => ['/admin/events']],
                ['label' => translate('admin.submenu.add_event'), 'url' => base_url('/admin/events/add'), 'paths' => ['/admin/events/add']],
            ];
            if (\App\Controller\AthleteMaintenanceController::enabled()) {
                $items[] = [
                    'label' => translate('admin.submenu.athlete_cleanup'),
                    'url' => base_url('/admin/maintenance/athlete-duplicates'),
                    'paths' => ['/admin/maintenance/athlete-duplicates'],
                ];
            }
            $items[] = [
                'label' => translate('admin.submenu.logout'),
                'url' => base_url('/admin/logout'),
                'paths' => ['/admin/logout'],
                'method' => 'post',
            ];

            return $items;
        }

        $items = [
            ['label' => translate('club.list.title'), 'url' => base_url('/clubs'), 'paths' => ['/clubs']],
        ];
        if (!$isLoggedIn) {
            $items[] = ['label' => translate('nav.login'), 'url' => base_url('/clubs/login'), 'paths' => ['/clubs/login']];
            $items[] = ['label' => translate('nav.register'), 'url' => base_url('/clubs/register'), 'paths' => ['/clubs/register']];
            return $items;
        }

        $items[] = ['label' => translate('club.area.submenu.manage'), 'url' => base_url('/clubs/area'), 'paths' => ['/clubs/area'], 'query' => ['view' => ['', 'list']]];
        $items[] = ['label' => translate('club.area.submenu.add'), 'url' => base_url('/clubs/area?view=add'), 'paths' => ['/clubs/area'], 'query' => ['view' => ['add']]];
        $items[] = ['label' => translate('club.area.submenu.logout'), 'url' => base_url('/clubs/logout'), 'paths' => ['/clubs/logout'], 'method' => 'post'];

        return $items;
    }
}
