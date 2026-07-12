<?php

if (!function_exists('hg_character_avatar_fallback_by_gender')) {
    function hg_character_avatar_fallback_by_gender($gender): string
    {
        $g = strtolower(trim((string)$gender));
        if (in_array($g, ['m', 'male', 'h', 'hombre', 'masculino', 'man', '1'], true)) {
            return '/img/ui/avatar/avatar_nadie_1.webp';
        }
        if (in_array($g, ['f', 'female', 'mujer', 'femenino', 'woman', '2'], true)) {
            return '/img/ui/avatar/avatar_nadie_2.webp';
        }
        return '/img/ui/avatar/avatar_nadie_3.webp';
    }
}

if (!function_exists('hg_character_kind_column')) {
    function hg_character_kind_column(mysqli $link, string $table = 'fact_characters'): string
    {
        static $cache = [];
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '') {
            return '';
        }
        if (isset($cache[$table])) {
            return $cache[$table];
        }
        foreach (['character_kind', 'kind'] as $candidate) {
            $rs = mysqli_query($link, "SHOW COLUMNS FROM `$table` LIKE '$candidate'");
            if ($rs && mysqli_num_rows($rs) > 0) {
                mysqli_free_result($rs);
                $cache[$table] = $candidate;
                return $candidate;
            }
            if ($rs) {
                mysqli_free_result($rs);
            }
        }
        $cache[$table] = '';
        return '';
    }
}

if (!function_exists('hg_character_kind_select')) {
    function hg_character_kind_select(mysqli $link, string $alias = '', string $table = 'fact_characters'): string
    {
        $col = hg_character_kind_column($link, $table);
        if ($col === '') {
            return "''";
        }
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        if ($alias !== '') {
            return "`$alias`.`$col`";
        }
        return "`$col`";
    }
}

if (!function_exists('hg_character_kind_normalize')) {
    function hg_character_kind_normalize($kind): string
    {
        $raw = strtolower(trim((string)$kind));
        if ($raw === 'mon' || $raw === 'monster' || $raw === 'monstruo') {
            return 'mon';
        }
        if ($raw === 'pnj' || $raw === 'npc' || $raw === 'nosheet' || $raw === 'no-sheet') {
            return 'pnj';
        }
        return 'pj';
    }
}

if (!function_exists('hg_character_state_symbol')) {
    function hg_character_state_symbol($status): string
    {
        $raw = trim((string)$status);
        $rawDec = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $rawLow = strtolower($raw);
        $rawDecLow = strtolower($rawDec);
        $haystack = $rawLow . ' ' . $rawDecLow;

        // Robust against accents/mojibake: match semantic fragments.
        if (strpos($haystack, 'por aparecer') !== false) {
            return '(&#64;)';
        }
        if (strpos($haystack, 'paradero') !== false) {
            return '(&#63;)';
        }
        if (strpos($haystack, 'cadav') !== false || strpos($haystack, 'cadáv') !== false) {
            return '(&#8224;)';
        }
        return '';
    }
}

if (!function_exists('hg_character_kind_from_row')) {
    function hg_character_kind_from_row(array $row): string
    {
        return (string)($row['character_kind'] ?? $row['kind'] ?? $row['tipo'] ?? '');
    }
}

if (!function_exists('hg_render_character_avatar_tile')) {
    function hg_render_character_avatar_tile(array $data): void
    {
        $href = (string)($data['href'] ?? '#');
        $characterId = (int)($data['character_id'] ?? $data['id'] ?? 0);
        $name = (string)($data['name'] ?? '');
        $alias = (string)($data['alias'] ?? '');
        $title = (string)($data['title'] ?? $name);
        $status = (string)($data['status'] ?? '');
        $kind = hg_character_kind_normalize($data['character_kind'] ?? $data['kind'] ?? '');
        $targetBlank = !empty($data['target_blank']);

        $avatarUrl = trim((string)($data['avatar_url'] ?? ''));
        if ($avatarUrl === '') {
            $avatarUrl = hg_character_avatar_url(
                (string)($data['image_url'] ?? ''),
                (string)($data['gender'] ?? '')
            );
        }

        $label = trim($alias) !== '' ? $alias : $name;
        $symbol = hg_character_state_symbol($status);
        $labelHtml = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        if ($symbol !== '') {
            $labelHtml .= ' ' . $symbol;
        }

        $targetAttr = $targetBlank ? " target='_blank'" : '';
        $linkClasses = "hg-avatar-link hg-avatar-link--{$kind}";
        $tooltipAttrs = '';
        if ($characterId > 0) {
            $linkClasses .= ' hg-tooltip';
            $tooltipAttrs = " data-tip='character' data-id='" . $characterId . "'";
        }
        echo "<a class='" . htmlspecialchars($linkClasses, ENT_QUOTES, 'UTF-8') . "' href='" . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . "'{$tooltipAttrs}{$targetAttr}>";
        echo "<div class='marcoFotoBio marcoFotoBio--{$kind}'>";
        echo "<div class='textoDentroFotoBio textoDentroFotoBio--{$kind}'>{$labelHtml}</div>";
        echo "<div class='dentroFotoBio'><img class='fotoBioList' src='" . htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') . "' alt='" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "'></div>";
        echo "</div>";
        echo "</a>";
    }
}

if (!function_exists('hg_character_avatar_url')) {
    function hg_character_avatar_url($imageUrl, $gender): string
    {
        $img = trim((string)$imageUrl);
        if ($img !== '' && strtolower($img) !== 'null') {
            if (strpos($img, '/public/') === 0) {
                return substr($img, 7);
            }
            return $img;
        }
        return hg_character_avatar_fallback_by_gender($gender);
    }
}

if (!function_exists('hg_character_avatar_variant_code')) {
    function hg_character_avatar_variant_code($variant): string
    {
        $raw = strtolower(trim((string)$variant));
        if ($raw === '') {
            return '';
        }
        $raw = preg_replace('/[^a-z0-9_-]/', '', $raw);
        if ($raw === null) {
            return '';
        }
        return substr($raw, 0, 50);
    }
}

if (!function_exists('hg_character_avatar_parse_ref')) {
    function hg_character_avatar_parse_ref($raw): array
    {
        $text = trim((string)$raw);
        if ($text === '') {
            return ['character_id' => 0, 'variant_code' => ''];
        }

        if (!preg_match('/^(-?\d+)(?::([a-z0-9_-]{1,50}))?$/i', $text, $m)) {
            return ['character_id' => 0, 'variant_code' => ''];
        }

        return [
            'character_id' => (int)$m[1],
            'variant_code' => hg_character_avatar_variant_code($m[2] ?? ''),
        ];
    }
}

if (!function_exists('hg_character_avatar_variants_table_exists')) {
    function hg_character_avatar_variants_table_exists(mysqli $link, bool $refresh = false): bool
    {
        static $cache = null;
        if (!$refresh && $cache !== null) {
            return $cache;
        }

        $cache = false;
        if ($st = $link->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'fact_character_avatar_variants'
        ")) {
            $st->execute();
            $st->bind_result($count);
            $st->fetch();
            $cache = ((int)$count > 0);
            $st->close();
        }

        return $cache;
    }
}

if (!function_exists('hg_character_avatar_variants_ensure_schema')) {
    function hg_character_avatar_variants_ensure_schema(mysqli $link): bool
    {
        static $attempted = false;
        if (hg_character_avatar_variants_table_exists($link, true)) {
            return true;
        }
        if ($attempted) {
            return false;
        }
        $attempted = true;

        $sql = "CREATE TABLE IF NOT EXISTS `fact_character_avatar_variants` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `character_id` int(10) unsigned NOT NULL,
            `variant_code` varchar(50) NOT NULL,
            `image_url` varchar(600) NOT NULL DEFAULT '',
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_character_variant` (`character_id`,`variant_code`),
            KEY `idx_fcav_variant_code` (`variant_code`),
            CONSTRAINT `fk_fcav_character` FOREIGN KEY (`character_id`) REFERENCES `fact_characters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $ok = @mysqli_query($link, $sql);
        if ($ok) {
            return hg_character_avatar_variants_table_exists($link, true);
        }
        return false;
    }
}

if (!function_exists('hg_character_avatar_variant_image_url')) {
    function hg_character_avatar_variant_image_url(mysqli $link, int $characterId, string $variantCode = ''): string
    {
        static $cache = [];

        $characterId = (int)$characterId;
        $variantCode = hg_character_avatar_variant_code($variantCode);
        if ($characterId <= 0 || $variantCode === '') {
            return '';
        }

        $cacheKey = $characterId . '|' . $variantCode;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $cache[$cacheKey] = '';
        if (!hg_character_avatar_variants_table_exists($link)) {
            return '';
        }

        if ($st = $link->prepare("
            SELECT image_url
            FROM fact_character_avatar_variants
            WHERE character_id = ?
              AND variant_code = ?
              AND is_active = 1
            LIMIT 1
        ")) {
            $st->bind_param('is', $characterId, $variantCode);
            if ($st->execute() && ($rs = $st->get_result())) {
                if ($row = $rs->fetch_assoc()) {
                    $cache[$cacheKey] = trim((string)($row['image_url'] ?? ''));
                }
            }
            $st->close();
        }

        return $cache[$cacheKey];
    }
}

if (!function_exists('hg_character_avatar_url_for_character')) {
    function hg_character_avatar_url_for_character(
        mysqli $link,
        int $characterId,
        $defaultImageUrl,
        $gender,
        string $variantCode = ''
    ): string {
        $variantUrl = hg_character_avatar_variant_image_url($link, $characterId, $variantCode);
        if ($variantUrl !== '') {
            return hg_character_avatar_url($variantUrl, $gender);
        }
        return hg_character_avatar_url($defaultImageUrl, $gender);
    }
}

