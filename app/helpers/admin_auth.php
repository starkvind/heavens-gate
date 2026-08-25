<?php
// Shared admin session/auth helpers.

if (!function_exists('hg_admin_is_https')) {
    function hg_admin_is_https(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';
        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';

        return ($https !== '' && strtolower((string)$https) !== 'off')
            || strtolower((string)$forwardedProto) === 'https';
    }
}

if (!function_exists('hg_admin_session_start')) {
    function hg_admin_session_start(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        if (headers_sent()) {
            return false;
        }

        ini_set('session.use_strict_mode', '1');
        $params = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => (int)($params['lifetime'] ?? 0),
            'path' => (string)($params['path'] ?? '/'),
            'domain' => (string)($params['domain'] ?? ''),
            'secure' => hg_admin_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        return session_start();
    }
}

if (!function_exists('hg_admin_is_authenticated')) {
    function hg_admin_is_authenticated(): bool
    {
        if (!hg_admin_session_start()) {
            return false;
        }

        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    }
}

if (!function_exists('hg_admin_mark_authenticated')) {
    function hg_admin_mark_authenticated(): void
    {
        if (!hg_admin_session_start()) {
            return;
        }

        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_logged_in_at'] = time();
    }
}

if (!function_exists('hg_admin_redirect')) {
    function hg_admin_redirect(string $path = '/talim'): void
    {
        if (!headers_sent()) {
            header('Location: ' . $path);
        }
        exit;
    }
}

if (!function_exists('hg_admin_login_return_path')) {
    /**
     * Devuelve una sección administrativa válida como destino tras el login.
     * Solo se permiten rutas locales que el panel realmente sabe cargar.
     */
    function hg_admin_login_return_path(?string $candidate = null): string
    {
        $candidate = trim((string)($candidate ?? ($_SERVER['REQUEST_URI'] ?? '')));
        if ($candidate === '' || strpbrk($candidate, "\r\n") !== false || $candidate[0] !== '/') {
            return '/talim';
        }

        $path = (string)(parse_url($candidate, PHP_URL_PATH) ?? '');
        $query = (string)(parse_url($candidate, PHP_URL_QUERY) ?? '');
        if ($path !== '/talim' || $query === '') {
            return '/talim';
        }

        parse_str($query, $params);
        $section = $params['s'] ?? null;
        if (!is_string($section)) {
            return '/talim';
        }

        $allowedSections = [
            'admin_pjs', 'admin_characters', 'admin_avatar_mass', 'admin_characters_worlds',
            'admin_character_deaths', 'admin_characters_clone', 'admin_sim_character_talk',
            'admin_sim_browser', 'admin_groups', 'admin_organizations', 'admin_temp',
            'admin_seasons', 'admin_season_order', 'admin_season_order_schema', 'admin_epis',
            'admin_chapters', 'admin_pois', 'admin_players', 'admin_chronicles', 'admin_realities',
            'admin_bso', 'admin_bso_link', 'admin_timelines', 'admin_birthdays_quick',
            'admin_gallery', 'admin_plots', 'admin_parties', 'admin_powers', 'admin_gift_image_mass',
            'admin_game_cards', 'admin_game_cards_seed', 'admin_docs', 'admin_external_links',
            'admin_character_links', 'admin_doc_links', 'admin_topic_viewer', 'admin_bridges',
            'admin_items', 'admin_menu', 'admin_relations', 'admin_news', 'admin_systems',
            'admin_forms', 'admin_maneuvers', 'admin_system_details', 'admin_systems_extra_details',
            'admin_systems_energy', 'admin_trait_sets', 'admin_traits', 'admin_actions',
            'admin_merits_flaws', 'admin_character_conditions', 'admin_character_conditions_bridge',
            'admin_characters_conditions_brige', 'admin_character_misc_bridge',
            'admin_character_affiliations_canonical', 'admin_systems_resources', 'admin_resources',
            'admin_resources_migration', 'admin_inspect_db', 'admin_schema_hardening_audit',
            'admin_mentions_help', 'admin_org_chart_schema',
        ];

        if (!in_array($section, $allowedSections, true) || isset($params['ajax'])) {
            return '/talim';
        }

        return '/talim?' . $query;
    }
}
if (!function_exists('hg_admin_logout')) {
    function hg_admin_logout(string $redirectPath = '/talim'): void
    {
        hg_admin_session_start();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => (string)($params['path'] ?? '/'),
                'domain' => (string)($params['domain'] ?? ''),
                'secure' => (bool)($params['secure'] ?? false),
                'httponly' => (bool)($params['httponly'] ?? true),
                'samesite' => 'Lax',
            ]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        hg_admin_redirect($redirectPath);
    }
}
