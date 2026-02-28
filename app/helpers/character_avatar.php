<?php

if (!function_exists('hg_character_avatar_fallback_by_gender')) {
    function hg_character_avatar_fallback_by_gender($gender): string
    {
        $g = strtolower(trim((string)$gender));
        if (in_array($g, ['m', 'male', 'h', 'hombre', 'masculino', 'man', '1'], true)) {
            return '/img/ui/avatar/avatar_nadie_1.png';
        }
        if (in_array($g, ['f', 'female', 'mujer', 'femenino', 'woman', '2'], true)) {
            return '/img/ui/avatar/avatar_nadie_2.png';
        }
        return '/img/ui/avatar/avatar_nadie_3.png';
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

