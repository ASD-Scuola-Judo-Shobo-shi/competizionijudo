<?php

declare(strict_types=1);

namespace App\Presentation;

final class Navigation
{
    private const COMPETITION_PATHS = [
        '/events.php',
        '/event_details.php',
        '/event_entries.php',
        '/event_register.php',
    ];
    private const CLUB_PATHS = [
        '/club_register.php',
        '/club_login.php',
        '/club_forgot_password.php',
        '/club_reset_password.php',
        '/club_area.php',
        '/clubs.php',
    ];
    private const ADMIN_PATHS = [
        '/admin_login.php',
        '/admin.php',
        '/admin_manage_clubs.php',
        '/admin_manage_events.php',
        '/admin_add_event.php',
        '/admin_edit_club.php',
        '/admin_edit_event.php',
        '/admin_logout.php',
    ];

    /** @return array<string, mixed> */
    public static function context(
        string $currentPath,
        string $clubView,
        bool $isAdmin,
        bool $isLoggedIn
    ): array {
        return [
            'currentPath' => $currentPath,
            'clubView' => $clubView,
            'homeActive' => in_array($currentPath, ['/', '/index.php'], true),
            'competitionsActive' => in_array($currentPath, self::COMPETITION_PATHS, true),
            'clubsActive' => in_array($currentPath, self::CLUB_PATHS, true),
            'adminActive' => in_array($currentPath, self::ADMIN_PATHS, true),
            'clubUrl' => $isLoggedIn ? '/club_area.php' : '/club_login.php',
            'submenuItems' => self::submenu($currentPath, $isAdmin, $isLoggedIn),
        ];
    }

    /** @return list<array{label: string, url: string, paths: list<string>, method?: 'post', query?: array<string, list<string>>}> */
    public static function submenu(string $currentPath, bool $isAdmin, bool $isLoggedIn): array
    {
        $showSubmenu = in_array($currentPath, self::COMPETITION_PATHS, true)
            || in_array($currentPath, self::CLUB_PATHS, true)
            || in_array($currentPath, self::ADMIN_PATHS, true);
        if (!$showSubmenu) {
            return [];
        }

        if (in_array($currentPath, self::COMPETITION_PATHS, true)) {
            $items = [
                ['label' => translate('events.submenu.showcase'), 'url' => '/events.php', 'paths' => ['/events.php']],
                ['label' => translate('events.submenu.details'), 'url' => '/event_details.php', 'paths' => ['/event_details.php']],
            ];
            if ($isAdmin || $isLoggedIn) {
                $items[] = ['label' => translate('events.submenu.entries'), 'url' => '/event_entries.php', 'paths' => ['/event_entries.php']];
            }
            $items[] = ['label' => translate('events.submenu.registration'), 'url' => '/event_register.php', 'paths' => ['/event_register.php']];

            return $items;
        }

        if (in_array($currentPath, self::ADMIN_PATHS, true)) {
            if (!$isAdmin) {
                return [['label' => translate('nav.login'), 'url' => '/admin_login.php', 'paths' => ['/admin_login.php']]];
            }

            return [
                ['label' => translate('admin.submenu.manage_clubs'), 'url' => '/admin_manage_clubs.php', 'paths' => ['/admin_manage_clubs.php', '/admin_edit_club.php']],
                ['label' => translate('admin.submenu.manage_events'), 'url' => '/admin_manage_events.php', 'paths' => ['/admin_manage_events.php']],
                ['label' => translate('admin.submenu.add_event'), 'url' => '/admin_add_event.php', 'paths' => ['/admin_add_event.php']],
                ['label' => translate('admin.submenu.logout'), 'url' => '/admin_logout.php', 'paths' => ['/admin_logout.php'], 'method' => 'post'],
            ];
        }

        $items = [
            ['label' => translate('club.list'), 'url' => '/clubs.php', 'paths' => ['/clubs.php']],
        ];
        if (!$isLoggedIn) {
            $items[] = ['label' => translate('nav.login'), 'url' => '/club_login.php', 'paths' => ['/club_login.php']];

            return $items;
        }

        $items[] = ['label' => translate('club.area.submenu.manage'), 'url' => '/club_area.php', 'paths' => ['/club_area.php'], 'query' => ['view' => ['', 'list']]];
        $items[] = ['label' => translate('club.area.submenu.add'), 'url' => '/club_area.php?view=add', 'paths' => ['/club_area.php'], 'query' => ['view' => ['add']]];
        $items[] = ['label' => translate('club.area.submenu.logout'), 'url' => '/club_logout.php', 'paths' => ['/club_logout.php'], 'method' => 'post'];

        return $items;
    }
}
