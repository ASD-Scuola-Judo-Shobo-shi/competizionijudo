<?php

declare(strict_types=1);

namespace App\Controller;

use App\Localization;

final class LanguageController
{
    public function switch(): void
    {
        $validLocales = ['it', 'en'];

        $locale = strtolower((string) ($_GET['locale'] ?? 'it'));

        if (!in_array($locale, $validLocales, true)) {
            $locale = 'it';
        }

        $_SESSION['locale'] = $locale;
        Localization::setLocale($locale);

        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        header('Location: ' . $referer);
        exit;
    }
}
