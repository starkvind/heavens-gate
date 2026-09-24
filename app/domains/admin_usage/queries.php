<?php
// Aggregate, privacy-minimal Admin section usage telemetry.
// Stores only section/day counters. No IP, user agent or personal identity.

if (!function_exists('hg_admin_usage_normalize_section')) {
    function hg_admin_usage_normalize_section(string $section): string
    {
        $section = strtolower(trim($section));
        if ($section === '') {
            return 'admin_main';
        }
        if (!preg_match('/^[a-z0-9_]{1,100}$/', $section)) {
            return '';
        }
        return $section;
    }
}

if (!function_exists('hg_admin_usage_record')) {
    function hg_admin_usage_record(mysqli $link, string $section): bool
    {
        $section = hg_admin_usage_normalize_section($section);
        if ($section === '') {
            return false;
        }

        $sql = "INSERT INTO fact_admin_section_usage_daily
                    (section_key, access_date, view_count, first_seen_at, last_seen_at)
                VALUES (?, CURDATE(), 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    view_count = view_count + 1,
                    last_seen_at = NOW()";

        try {
            $st = $link->prepare($sql);
            if (!$st) {
                return false;
            }
            $st->bind_param('s', $section);
            $ok = $st->execute();
            $st->close();
            return $ok;
        } catch (mysqli_sql_exception $e) {
            // Migration may not be installed yet. Telemetry must never break Admin.
            return false;
        }
    }
}

if (!function_exists('hg_admin_usage_report')) {
    function hg_admin_usage_report(mysqli $link, int $windowDays = 30): array
    {
        $windowDays = max(1, min(3650, $windowDays));
        $cutoff = date('Y-m-d', strtotime('-' . ($windowDays - 1) . ' days'));

        $out = [
            'ready' => false,
            'window_days' => $windowDays,
            'cutoff' => $cutoff,
            'overview' => [
                'total_views' => 0,
                'window_views' => 0,
                'sections_used' => 0,
                'active_days' => 0,
                'first_seen_at' => null,
                'last_seen_at' => null,
            ],
            'rows' => [],
            'daily' => [],
            'error' => '',
        ];

        try {
            $overviewSql = "SELECT
                    COALESCE(SUM(view_count), 0) AS total_views,
                    COALESCE(SUM(CASE WHEN access_date >= ? THEN view_count ELSE 0 END), 0) AS window_views,
                    COUNT(DISTINCT section_key) AS sections_used,
                    COUNT(DISTINCT access_date) AS active_days,
                    MIN(first_seen_at) AS first_seen_at,
                    MAX(last_seen_at) AS last_seen_at
                FROM fact_admin_section_usage_daily";
            $st = $link->prepare($overviewSql);
            if (!$st) {
                throw new RuntimeException($link->error);
            }
            $st->bind_param('s', $cutoff);
            $st->execute();
            $rs = $st->get_result();
            $row = $rs ? $rs->fetch_assoc() : null;
            $st->close();
            if ($row) {
                $out['overview'] = [
                    'total_views' => (int)($row['total_views'] ?? 0),
                    'window_views' => (int)($row['window_views'] ?? 0),
                    'sections_used' => (int)($row['sections_used'] ?? 0),
                    'active_days' => (int)($row['active_days'] ?? 0),
                    'first_seen_at' => $row['first_seen_at'] ?? null,
                    'last_seen_at' => $row['last_seen_at'] ?? null,
                ];
            }

            $rowsSql = "SELECT
                    section_key,
                    COALESCE(SUM(view_count), 0) AS total_views,
                    COALESCE(SUM(CASE WHEN access_date >= ? THEN view_count ELSE 0 END), 0) AS window_views,
                    COUNT(*) AS active_days,
                    COUNT(DISTINCT CASE WHEN access_date >= ? THEN access_date END) AS window_active_days,
                    MIN(first_seen_at) AS first_seen_at,
                    MAX(last_seen_at) AS last_seen_at
                FROM fact_admin_section_usage_daily
                GROUP BY section_key
                ORDER BY total_views DESC, last_seen_at DESC, section_key ASC";
            $st = $link->prepare($rowsSql);
            if (!$st) {
                throw new RuntimeException($link->error);
            }
            $st->bind_param('ss', $cutoff, $cutoff);
            $st->execute();
            $rs = $st->get_result();
            if ($rs) {
                while ($row = $rs->fetch_assoc()) {
                    $out['rows'][] = [
                        'section_key' => (string)($row['section_key'] ?? ''),
                        'total_views' => (int)($row['total_views'] ?? 0),
                        'window_views' => (int)($row['window_views'] ?? 0),
                        'active_days' => (int)($row['active_days'] ?? 0),
                        'window_active_days' => (int)($row['window_active_days'] ?? 0),
                        'first_seen_at' => $row['first_seen_at'] ?? null,
                        'last_seen_at' => $row['last_seen_at'] ?? null,
                    ];
                }
            }
            $st->close();

            $dailySql = "SELECT access_date, SUM(view_count) AS views
                FROM fact_admin_section_usage_daily
                WHERE access_date >= ?
                GROUP BY access_date
                ORDER BY access_date DESC";
            $st = $link->prepare($dailySql);
            if (!$st) {
                throw new RuntimeException($link->error);
            }
            $st->bind_param('s', $cutoff);
            $st->execute();
            $rs = $st->get_result();
            if ($rs) {
                while ($row = $rs->fetch_assoc()) {
                    $out['daily'][] = [
                        'access_date' => (string)($row['access_date'] ?? ''),
                        'views' => (int)($row['views'] ?? 0),
                    ];
                }
            }
            $st->close();

            $out['ready'] = true;
            return $out;
        } catch (Throwable $e) {
            $out['error'] = $e->getMessage();
            return $out;
        }
    }
}
