<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Core\Request;
use App\Core\AuthContext;
use App\Localization;
use App\Model\Club;

final class LayoutContext
{
    /** @return array<string, mixed> */
    public static function build(Request $request, ?Club $candidateClub = null): array
    {
        $sessionClubId = AuthContext::clubId();
        $authenticatedClub = null;
        $loggedInClubId = null;
        if (is_numeric($sessionClubId) && (int) $sessionClubId > 0) {
            $clubId = (int) $sessionClubId;
            $authenticatedClub = $candidateClub?->id === $clubId
                ? $candidateClub
                : Club::findForLayoutById($clubId);
            if ($authenticatedClub !== null) {
                $loggedInClubId = $clubId;
            }
        }

        $isLoggedIn = $authenticatedClub !== null;
        $isAdmin = AuthContext::isAdministrator();
        $currentPath = $request->path();
        $clubView = (string) $request->query('view', '');

        return array_merge([
            'appName' => (string) config('app.name'),
            'locale' => Localization::getLocale(),
            'isLoggedIn' => $isLoggedIn,
            'isAdmin' => $isAdmin,
            'loggedInClubId' => $loggedInClubId,
            'clubEmail' => $authenticatedClub?->email,
            'privacyControllerName' => (string) config('privacy.controller_name'),
            'privacyControllerAddress' => (string) config('privacy.controller_address'),
            'privacyControllerFiscalCode' => (string) config('privacy.controller_fiscal_code'),
        ], Navigation::context($currentPath, $clubView, $isAdmin, $isLoggedIn));
    }
}
