<?php

declare(strict_types=1);

namespace App\Model;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    /** @var list<array{sql: string, time: float}> */
    private static array $queries = [];

    public static function connection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $host = env('DB_HOST', '127.0.0.1');
        $name = env('DB_NAME');
        $user = env('DB_USER', 'root');
        $pass = env('DB_PASS', '');

        if ($name === null) {
            throw new \RuntimeException('Missing required environment variable: DB_NAME');
        }

        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name);
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        self::$pdo = $pdo;

        return self::$pdo;
    }

    /**
     * Record a query execution for profiling.
     */
    public static function recordQuery(string $sql, float $durationMs): void
    {
        self::$queries[] = [
            'sql' => $sql,
            'time' => $durationMs,
        ];
    }

    /**
     * Get all recorded queries.
     * @return list<array{sql: string, time: float}>
     */
    public static function getQueries(): array
    {
        return self::$queries;
    }

    /**
     * Render query profiling output if debug mode is enabled.
     * Outputs a small HTML table with slow queries highlighted.
     */
    public static function renderProfiler(): string
    {
        if (self::$queries === [] || !config('app.debug', false)) {
            return '';
        }

        $total = 0.0;
        $slow = 0;
        $html = '<div style="background:#f8f9fa;border:1px solid #dee2e6;border-radius:8px;padding:12px;margin:20px 0;font-size:13px;font-family:monospace;max-height:400px;overflow:auto;">';
        $html .= '<strong style="color:#495057;">DB Profiler (' . count(self::$queries) . ' queries)</strong><table style="width:100%;border-collapse:collapse;margin-top:8px;">';
        $html .= '<thead><tr style="border-bottom:2px solid #dee2e6;"><th style="padding:4px 8px;text-align:left;">#</th><th style="padding:4px 8px;text-align:left;">SQL</th><th style="padding:4px 8px;text-align:right;">Time (ms)</th></tr></thead><tbody>';

        foreach (self::$queries as $i => $q) {
            $total += $q['time'];
            $isSlow = $q['time'] > 100;
            if ($isSlow) {
                $slow++;
            }
            $style = $isSlow ? ' style="background:#fff3cd;color:#856404;"' : '';
            $html .= '<tr' . $style . '><td style="padding:3px 8px;">' . ($i + 1) . '</td>';
            $html .= '<td style="padding:3px 8px;max-width:600px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . e($q['sql']) . '</td>';
            $html .= '<td style="padding:3px 8px;text-align:right;">' . number_format($q['time'], 1) . '</td></tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<p style="margin:8px 0 0;color:#495057;"><strong>Total:</strong> ' . number_format($total, 1) . ' ms';
        if ($slow > 0) {
            $html .= ' | <span style="color:#856404;"><strong>Slow queries (>100ms):</strong> ' . $slow . '</span>';
        }
        $html .= '</p></div>';

        return $html;
    }
}
