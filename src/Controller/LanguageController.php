<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Localization;

final class LanguageController extends Controller
{
    public function switch(Request $request): Response
    {
        $locale = strtolower((string) $request->query('locale', 'it'));
        if (!in_array($locale, ['it', 'en'], true)) {
            $locale = 'it';
        }

        Session::set('locale', $locale);
        Localization::setLocale($locale);

        return $this->redirect($this->safeReferer($request));
    }

    private function safeReferer(Request $request): string
    {
        $referer = trim((string) $request->server('HTTP_REFERER', ''));
        if ($referer === '' || preg_match('/[\r\n]/', $referer) === 1) {
            return '/';
        }

        $parts = parse_url($referer);
        if (!is_array($parts)) {
            return '/';
        }

        if (isset($parts['host'])) {
            $requestHost = parse_url(
                '//' . (string) $request->server('HTTP_HOST', ''),
                PHP_URL_HOST
            );
            if (
                !is_string($requestHost)
                || strtolower($parts['host']) !== strtolower($requestHost)
            ) {
                return '/';
            }
        } elseif (!str_starts_with($referer, '/') || str_starts_with($referer, '//')) {
            return '/';
        }

        $path = '/' . ltrim((string) ($parts['path'] ?? '/'), '/');
        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?' . $parts['query'];
        }

        return $path;
    }
}
