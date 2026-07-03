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

        // Strip the environment prefix (e.g. /prod, /dev, /legacy) from the
        // referer path so that redirect() does not double it via base_url().
        $basePath = app_base_path();
        if ($basePath !== '' && str_starts_with($path, $basePath . '/')) {
            $path = substr($path, strlen($basePath));
        } elseif ($basePath !== '' && $path === $basePath) {
            $path = '/';
        }

        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?' . $parts['query'];
        }

        return $path;
    }
}
