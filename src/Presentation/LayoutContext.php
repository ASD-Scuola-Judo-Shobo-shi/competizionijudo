<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Core\Request;
use App\Core\Session;
use App\Localization;
use App\Model\Club;

final class LayoutContext
{
    /** @return array<string, mixed> */
    public static function build(Request $request, ?Club $candidateClub = null): array
    {
        $sessionClubId = Session::get('club_id');
        $authenticatedClub = null;
        if (is_numeric($sessionClubId) && (int) $sessionClubId > 0) {
            $clubId = (int) $sessionClubId;
            $authenticatedClub = $candidateClub?->id === $clubId
                ? $candidateClub
                : Club::findById($clubId);
        }

        $isLoggedIn = $authenticatedClub !== null;
        $isAdmin = !empty(Session::get('is_admin'));
        $currentPath = $request->path();
        $clubView = (string) $request->query('view', '');

        return array_merge([
            'appName' => (string) config('app.name'),
            'locale' => Localization::getLocale(),
            'isLoggedIn' => $isLoggedIn,
            'isAdmin' => $isAdmin,
            'clubEmail' => $authenticatedClub?->email,
            'privacyControllerName' => (string) config('privacy.controller_name'),
            'privacyControllerAddress' => (string) config('privacy.controller_address'),
            'privacyControllerFiscalCode' => (string) config('privacy.controller_fiscal_code'),
        ], Navigation::context($currentPath, $clubView, $isAdmin, $isLoggedIn));
    }
}
