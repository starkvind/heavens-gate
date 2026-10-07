<?php

require_once(__DIR__ . '/../characters/detail_queries.php');
require_once(__DIR__ . '/../chapters/queries.php');
require_once(__DIR__ . '/../documents/queries.php');
require_once(__DIR__ . '/../inventory/queries.php');

if (!function_exists('hg_admin_exports_plain')) {
    function hg_admin_exports_plain($value): string
    {
        $text = (string)$value;
        $text = preg_replace('/<\\s*br\\s*\\/?\\s*>/i', "\n", $text);
        $text = str_replace(['</p>', '</div>', '</li>', '</fieldset>', '</legend>', '</tr>'], "\n", $text);
        $text = str_replace('<li>', '- ', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\\r\\n|\\r/u", "\n", $text);
        $text = preg_replace("/[ \\t]+\\n/u", "\n", $text);
        $text = preg_replace("/\\n{3,}/u", "\n\n", $text);
        return trim((string)$text);
    }
}

if (!function_exists('hg_admin_exports_section')) {
    function hg_admin_exports_section(array &$sections, string $title, array $lines): void
    {
        $clean = [];
        foreach ($lines as $line) {
            $line = hg_admin_exports_plain($line);
            if ($line !== '') $clean[] = $line;
        }
        if (!$clean) return;
        $sections[] = implode("\n", [
            str_repeat('=', 12),
            $title,
            str_repeat('=', 12),
            implode("\n", $clean),
        ]);
    }
}

if (!function_exists('hg_admin_exports_normalize_ids')) {
    function hg_admin_exports_normalize_ids($values): array
    {
        if (!is_array($values)) return [];
        $ids = [];
        foreach ($values as $value) {
            $id = (int)$value;
            if ($id > 0) $ids[$id] = $id;
        }
        return array_values($ids);
    }
}

if (!function_exists('hg_admin_exports_chronicles')) {
    function hg_admin_exports_chronicles(mysqli $link): array
    {
        $result = mysqli_query(
            $link,
            "SELECT id, name, pretty_id
             FROM dim_chronicles
             ORDER BY name ASC, id ASC"
        );
        if (!$result) return [];
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'pretty_id' => (string)($row['pretty_id'] ?? ''),
            ];
        }
        mysqli_free_result($result);
        return $rows;
    }
}

if (!function_exists('hg_admin_exports_filter_sql')) {
    function hg_admin_exports_filter_sql(string $column, array $chronicleIds, bool $includeUnscoped): string
    {
        $ids = hg_admin_exports_normalize_ids($chronicleIds);
        $parts = [];
        if ($ids) $parts[] = $column . ' IN (' . implode(',', $ids) . ')';
        if ($includeUnscoped) $parts[] = '(' . $column . ' IS NULL OR ' . $column . ' = 0)';
        return $parts ? '(' . implode(' OR ', $parts) . ')' : '0=1';
    }
}

if (!function_exists('hg_admin_exports_document_filter_sql')) {
    function hg_admin_exports_document_filter_sql(array $chronicleIds, bool $includeUnscoped): string
    {
        $ids = hg_admin_exports_normalize_ids($chronicleIds);
        $parts = [];
        if ($ids) {
            $parts[] = "EXISTS (
                SELECT 1
                FROM bridge_characters_docs bcd
                JOIN fact_characters fc ON fc.id = bcd.character_id
                WHERE bcd.doc_id = d.id
                  AND fc.chronicle_id IN (" . implode(',', $ids) . ")
            )";
        }
        if ($includeUnscoped) {
            $parts[] = "NOT EXISTS (
                SELECT 1 FROM bridge_characters_docs bcd0 WHERE bcd0.doc_id = d.id
            )";
            $parts[] = "EXISTS (
                SELECT 1
                FROM bridge_characters_docs bcdu
                JOIN fact_characters fcu ON fcu.id = bcdu.character_id
                WHERE bcdu.doc_id = d.id
                  AND (fcu.chronicle_id IS NULL OR fcu.chronicle_id = 0)
            )";
        }
        return $parts ? '(' . implode(' OR ', $parts) . ')' : '0=1';
    }
}

if (!function_exists('hg_admin_exports_count')) {
    function hg_admin_exports_count(mysqli $link, string $kind, array $chronicleIds, bool $includeUnscoped): int
    {
        switch ($kind) {
            case 'characters':
                $where = hg_admin_exports_filter_sql('c.chronicle_id', $chronicleIds, $includeUnscoped);
                $sql = "SELECT COUNT(*) AS total FROM fact_characters c WHERE {$where}";
                break;
            case 'seasons':
                $where = hg_admin_exports_filter_sql('s.chronicle_id', $chronicleIds, $includeUnscoped);
                $sql = "SELECT COUNT(*) AS total FROM dim_seasons s WHERE {$where}";
                break;
            case 'chapters':
                $where = hg_admin_exports_filter_sql('s.chronicle_id', $chronicleIds, $includeUnscoped);
                $sql = "SELECT COUNT(*) AS total
                        FROM dim_chapters c
                        LEFT JOIN dim_seasons s ON s.id = c.season_id
                        WHERE {$where}";
                break;
            case 'documents':
                $where = hg_admin_exports_document_filter_sql($chronicleIds, $includeUnscoped);
                $sql = "SELECT COUNT(*) AS total FROM fact_docs d WHERE {$where}";
                break;
            case 'inventory':
                $sql = "SELECT COUNT(*) AS total FROM fact_items";
                break;
            default:
                return 0;
        }

        $result = mysqli_query($link, $sql);
        if (!$result) return 0;
        $row = mysqli_fetch_assoc($result) ?: [];
        mysqli_free_result($result);
        return (int)($row['total'] ?? 0);
    }
}

if (!function_exists('hg_admin_exports_batch_limit')) {
    function hg_admin_exports_batch_limit(string $kind): int
    {
        if ($kind === 'characters') return 3;
        if ($kind === 'inventory') return 20;
        return 10;
    }
}

if (!function_exists('hg_admin_exports_next_ids')) {
    function hg_admin_exports_next_ids(
        mysqli $link,
        string $kind,
        array $chronicleIds,
        bool $includeUnscoped,
        int $afterId
    ): array {
        $afterId = max(0, $afterId);
        $limit = hg_admin_exports_batch_limit($kind);

        switch ($kind) {
            case 'characters':
                $where = hg_admin_exports_filter_sql('c.chronicle_id', $chronicleIds, $includeUnscoped);
                $sql = "SELECT c.id
                        FROM fact_characters c
                        WHERE c.id > {$afterId} AND {$where}
                        ORDER BY c.id ASC LIMIT {$limit}";
                break;
            case 'seasons':
                $where = hg_admin_exports_filter_sql('s.chronicle_id', $chronicleIds, $includeUnscoped);
                $sql = "SELECT s.id
                        FROM dim_seasons s
                        WHERE s.id > {$afterId} AND {$where}
                        ORDER BY s.id ASC LIMIT {$limit}";
                break;
            case 'chapters':
                $where = hg_admin_exports_filter_sql('s.chronicle_id', $chronicleIds, $includeUnscoped);
                $sql = "SELECT c.id
                        FROM dim_chapters c
                        LEFT JOIN dim_seasons s ON s.id = c.season_id
                        WHERE c.id > {$afterId} AND {$where}
                        ORDER BY c.id ASC LIMIT {$limit}";
                break;
            case 'documents':
                $where = hg_admin_exports_document_filter_sql($chronicleIds, $includeUnscoped);
                $sql = "SELECT d.id
                        FROM fact_docs d
                        WHERE d.id > {$afterId} AND {$where}
                        ORDER BY d.id ASC LIMIT {$limit}";
                break;
            case 'inventory':
                $sql = "SELECT i.id
                        FROM fact_items i
                        WHERE i.id > {$afterId}
                        ORDER BY i.id ASC LIMIT {$limit}";
                break;
            default:
                return [];
        }

        $result = mysqli_query($link, $sql);
        if (!$result) return [];
        $ids = [];
        while ($row = mysqli_fetch_assoc($result)) $ids[] = (int)$row['id'];
        mysqli_free_result($result);
        return $ids;
    }
}

if (!function_exists('hg_admin_exports_event_date')) {
    function hg_admin_exports_event_date(?string $dateValue, ?string $precision, ?string $note): string
    {
        $precision = trim((string)$precision);
        $dateValue = trim((string)$dateValue);
        $note = trim((string)$note);
        if ($precision === 'unknown') return $note !== '' ? $note : 'Desconocido';
        if ($dateValue === '' || $dateValue === '0000-00-00') return $note !== '' ? $note : 'Desconocido';
        if (!preg_match('/^(\\d{4})-(\\d{2})-(\\d{2})/', $dateValue, $m)) return $note !== '' ? $note : $dateValue;
        $year = (int)$m[1];
        $month = (int)$m[2];
        $day = (int)$m[3];
        if ($year < 1 || !checkdate($month, $day, $year)) return $note !== '' ? $note : $dateValue;
        if ($precision === 'year') $base = sprintf('%04d', $year);
        elseif ($precision === 'month') $base = sprintf('%02d/%04d', $month, $year);
        elseif ($precision === 'approx') $base = 'Aprox. ' . sprintf('%02d/%02d/%04d', $day, $month, $year);
        else $base = sprintf('%02d/%02d/%04d', $day, $month, $year);
        return $note !== '' ? $base . ' (' . $note . ')' : $base;
    }
}

if (!function_exists('hg_admin_exports_character')) {
    function hg_admin_exports_character(mysqli $link, int $characterId): string
    {
        $detail = hg_characters_fetch_detail_row($link, $characterId);
        $row = $detail['row'] ?? null;
        $deathTable = $detail['death_table'] ?? null;
        if (!is_array($row)) return '';

        $sections = [];
        $kind = strtolower(trim((string)($row['character_kind'] ?? $row['kind'] ?? '')));
        $isMonster = in_array($kind, ['mon', 'monster'], true);
        $hasSheet = in_array($kind, ['pj', 'mon', 'monster'], true);
        $systemId = (int)($row['system_id'] ?? 0);

        $birth = hg_characters_fetch_birth_event($link, $characterId);
        $birthLabel = hg_admin_exports_event_date(
            (string)($birth['event_date'] ?? ''),
            (string)($birth['date_precision'] ?? 'unknown'),
            (string)($birth['date_note'] ?? '')
        );

        $meta = ['ID: ' . $characterId, 'Nombre: ' . (string)($row['name'] ?? '')];
        if (trim((string)($row['pretty_id'] ?? '')) !== '') $meta[] = 'Pretty ID: ' . $row['pretty_id'];
        if (trim((string)($row['alias'] ?? '')) !== '') $meta[] = 'Alias: ' . $row['alias'];
        if (trim((string)($row['garou_name'] ?? '')) !== '') $meta[] = 'Nombre Garou: ' . $row['garou_name'];
        $meta[] = 'Fecha de nacimiento: ' . $birthLabel;
        $status = trim((string)($row['status_label'] ?? $row['status'] ?? ''));
        if ($status !== '') $meta[] = 'Estado: ' . $status;
        $death = trim((string)($row['death_description'] ?? ''));
        $deathDate = trim((string)($row['death_date'] ?? ''));
        if ($death !== '') $meta[] = 'Muerte: ' . $death . ($deathDate !== '' && $deathDate !== '1000-01-01' ? ' (' . $deathDate . ')' : '');
        if (trim((string)($row['concept'] ?? '')) !== '') $meta[] = 'Concepto: ' . $row['concept'];
        hg_admin_exports_section($sections, 'DATOS DEL PERSONAJE', $meta);

        $info = [];
        if (trim((string)($row['info_text'] ?? '')) !== '') $info[] = $row['info_text'];
        if (trim((string)($row['notes'] ?? '')) !== '') $info[] = "Notas internas (admin):\n" . $row['notes'];
        hg_admin_exports_section($sections, 'INFORMACION', $info);

        $details = [];
        $labels = hg_characters_fetch_system_detail_labels($link, $systemId);
        $label = static function (string $key, string $fallback) use ($labels): string {
            $value = trim((string)($labels[$key] ?? ''));
            return $value !== '' ? $value : $fallback;
        };

        foreach ([
            [$label('label_breed', 'Raza'), 'breed_name'],
            [$label('label_auspice', 'Auspicio'), 'auspice_name'],
            [$label('label_tribe', 'Tribu'), 'tribe_name'],
            ['Tótem', 'totem_name'],
            ['Naturaleza', 'nature_name'],
            ['Conducta', 'demeanor_name'],
        ] as $pair) {
            $value = trim((string)($row[$pair[1]] ?? ''));
            if ($value !== '') $details[] = $pair[0] . ': ' . $value;
        }

        foreach (hg_characters_fetch_misc_systems($link, $characterId) as $misc) {
            $name = trim((string)($misc['name'] ?? ''));
            if ($name === '') continue;
            $miscKind = trim((string)($misc['kind'] ?? '')) ?: 'Misc';
            $details[] = $miscKind . ': ' . $name;
        }

        $aff = hg_characters_fetch_primary_affiliations($link, $characterId);
        $groupId = (int)($aff['group_id'] ?? 0);
        if ($groupId > 0) {
            $group = hg_characters_fetch_lookup($link, 'dim_groups', $groupId);
            if ($group) $details[] = $label('label_pack', 'Manada') . ': ' . (string)$group['name'];
        }
        $orgId = (int)($aff['organization_id'] ?? 0);
        if ($orgId > 0) {
            $org = hg_characters_fetch_lookup($link, 'dim_organizations', $orgId);
            if ($org) $details[] = $label('label_clan', 'Clan') . ': ' . (string)$org['name'];
        }
        if (trim((string)($row['player_name'] ?? '')) !== '') $details[] = 'Jugador: ' . $row['player_name'];
        if (trim((string)($row['chronicle_name'] ?? '')) !== '') $details[] = 'Crónica: ' . $row['chronicle_name'];
        if (trim((string)($row['system_name'] ?? $row['system_label'] ?? '')) !== '') $details[] = 'Sistema: ' . ($row['system_name'] ?? $row['system_label']);
        if (trim((string)($row['rank'] ?? '')) !== '') $details[] = 'Rango: ' . $row['rank'];
        hg_admin_exports_section($sections, 'DETALLES DE HOJA', $details);

        if ($hasSheet) {
            $traits = [];
            foreach (['Atributos', 'Talentos', 'Técnicas', 'Conocimientos', 'Trasfondos'] as $traitKind) {
                $rows = hg_characters_fetch_traits_for_system_type($link, $characterId, $systemId, $traitKind, $isMonster);
                $lines = [];
                foreach ($rows as $trait) {
                    $name = trim((string)($trait['name'] ?? ''));
                    $value = (int)($trait['value'] ?? 0);
                    if ($name === '' || ($traitKind === 'Trasfondos' && $value <= 0)) continue;
                    $lines[] = $name . ': ' . $value;
                }
                if ($lines) $traits[$traitKind] = $lines;
            }
            hg_admin_exports_section($sections, 'ATRIBUTOS', $traits['Atributos'] ?? []);

            $skills = [];
            foreach (['Talentos', 'Técnicas', 'Conocimientos'] as $traitKind) {
                if (empty($traits[$traitKind])) continue;
                $skills[] = '[' . $traitKind . ']';
                foreach ($traits[$traitKind] as $line) $skills[] = $line;
                $skills[] = '';
            }
            hg_admin_exports_section($sections, 'HABILIDADES', $skills);
            hg_admin_exports_section($sections, 'TRASFONDOS', $traits['Trasfondos'] ?? []);

            if (!$isMonster) {
                $merits = [];
                foreach (hg_characters_fetch_merits_flaws($link, $characterId) as $merit) {
                    $name = trim((string)($merit['name'] ?? ''));
                    if ($name === '') continue;
                    $line = $name;
                    $mk = trim((string)($merit['kind'] ?? ''));
                    if ($mk !== '') $line .= ' [' . $mk . ']';
                    $level = $merit['level'] ?? $merit['cost'] ?? null;
                    if ($level !== null) $line .= ': ' . (int)$level;
                    $merits[] = $line;
                }
                hg_admin_exports_section($sections, 'MERITOS Y DEFECTOS', $merits);
            }

            $resources = [];
            $resourceRows = hg_characters_fetch_resources($link, $characterId, $systemId);
            foreach (($resourceRows['renombre'] ?? []) as $res) {
                $resources[] = '[Renombre] ' . ($res['name'] ?? '') . ': P ' . (int)($res['perm'] ?? 0) . ' / T ' . (int)($res['temp'] ?? 0);
            }
            foreach (($resourceRows['estado'] ?? []) as $res) {
                $resources[] = '[Estado] ' . ($res['name'] ?? '') . ': ' . (int)($res['temp'] ?? 0) . '/' . (int)($res['perm'] ?? 0);
            }
            if (!$isMonster) {
                foreach (($resourceRows['exp'] ?? []) as $res) {
                    $resources[] = '[Experiencia] ' . ($res['name'] ?? '') . ': ' . (int)($res['temp'] ?? 0) . '/' . (int)($res['perm'] ?? 0) . ' PX';
                }
            }
            hg_admin_exports_section($sections, 'RECURSOS', $resources);

            $conditions = [];
            foreach (hg_characters_fetch_conditions($link, $characterId) as $condition) {
                $line = trim((string)($condition['name'] ?? ''));
                if ($line === '') continue;
                $location = trim((string)($condition['condition_location'] ?? ''));
                $instanceNo = (int)($condition['instance_no'] ?? 1);
                if ($location !== '') $line .= ' (' . $location . ')';
                elseif ($instanceNo > 1) $line .= ' #' . $instanceNo;
                $category = trim((string)($condition['category'] ?? ''));
                if ($category !== '') $line .= ' [' . $category . ']';
                $conditions[] = $line;
            }
            hg_admin_exports_section($sections, 'CONDICIONES', $conditions);

            $powers = [];
            $powerRows = hg_characters_fetch_powers($link, $characterId);
            foreach (['dones' => 'Dones', 'disciplinas' => 'Disciplinas', 'rituales' => 'Rituales'] as $powerKey => $powerLabel) {
                if (empty($powerRows[$powerKey])) continue;
                $powers[] = '[' . $powerLabel . ']';
                foreach ($powerRows[$powerKey] as $power) {
                    $line = trim((string)($power['name'] ?? ''));
                    if ($line === '') continue;
                    if (($power['level'] ?? null) !== null) $line .= ': ' . (int)$power['level'];
                    $powers[] = $line;
                }
                $powers[] = '';
            }
            hg_admin_exports_section($sections, 'PODERES', $powers);

            $items = [];
            foreach (hg_characters_fetch_items($link, $characterId) as $item) {
                $name = trim((string)($item['name'] ?? ''));
                if ($name === '') continue;
                $type = trim((string)($item['item_type_name'] ?? ''));
                $items[] = ($type !== '' ? '[' . $type . '] ' : '') . $name;
            }
            hg_admin_exports_section($sections, 'INVENTARIO', $items);
        }

        $relations = [];
        foreach (hg_characters_fetch_relations($link, $characterId) as $rel) {
            $name = trim((string)($rel['name'] ?? ''));
            $type = trim((string)($rel['relation_type'] ?? '')) ?: 'Relación';
            if ($name === '') continue;
            $dir = ((string)($rel['direction'] ?? '') === 'incoming') ? 'recibe de' : 'hacia';
            $relations[] = $type . ' [' . $dir . ']: ' . $name;
        }
        foreach (hg_characters_fetch_kills($link, $characterId, $deathTable) as $kill) {
            $victim = trim((string)($kill['victim_name'] ?? ''));
            if ($victim === '') continue;
            $extra = trim((string)($kill['death_date'] ?? ''));
            if ($extra === '') $extra = trim((string)($kill['event_date'] ?? ''));
            $relations[] = 'Muerte causada: ' . $victim . ($extra !== '' ? ' (' . $extra . ')' : '');
        }
        hg_admin_exports_section($sections, 'RELACIONES', $relations);

        $participation = [];
        foreach (hg_characters_fetch_chapter_participation($link, $characterId) as $part) {
            $bits = [];
            if (trim((string)($part['temporada_name'] ?? '')) !== '') $bits[] = $part['temporada_name'];
            if (trim((string)($part['name'] ?? '')) !== '') $bits[] = $part['name'];
            if (trim((string)($part['played_date'] ?? '')) !== '') $bits[] = $part['played_date'];
            if ($bits) $participation[] = implode(' | ', $bits);
        }
        $eventCount = hg_characters_count_timeline_events($link, $characterId);
        if ($eventCount > 0) $participation[] = 'Eventos de timeline vinculados: ' . $eventCount;
        hg_admin_exports_section($sections, 'PARTICIPACION', $participation);

        $docs = [];
        foreach (hg_characters_fetch_docs($link, $characterId) as $doc) {
            $title = trim((string)($doc['title'] ?? ''));
            if ($title === '') continue;
            $prefix = trim((string)($doc['section_name'] ?? ''));
            $relLabel = trim((string)($doc['relation_label'] ?? ''));
            $line = ($prefix !== '' ? '[' . $prefix . '] ' : '') . $title;
            if ($relLabel !== '') $line .= ' - ' . $relLabel;
            $docs[] = $line;
        }
        foreach (hg_characters_fetch_external_links($link, $characterId) as $ext) {
            $title = trim((string)($ext['title'] ?? ''));
            $url = trim((string)($ext['url'] ?? ''));
            if ($title === '' && $url === '') continue;
            $line = $title !== '' ? $title : $url;
            $ek = trim((string)($ext['kind'] ?? ''));
            if ($ek !== '') $line = '[' . $ek . '] ' . $line;
            if ($url !== '' && $url !== $title) $line .= ' - ' . $url;
            $docs[] = $line;
        }
        hg_admin_exports_section($sections, 'DOCUMENTACION Y ENLACES', $docs);

        return trim(implode("\n\n", $sections));
    }
}

if (!function_exists('hg_admin_exports_season_participants')) {
    function hg_admin_exports_season_participants(mysqli $link, int $seasonId): array
    {
        $stmt = mysqli_prepare(
            $link,
            "SELECT DISTINCT p.id, p.name
             FROM bridge_chapters_characters b
             JOIN dim_chapters c ON c.id = b.chapter_id
             JOIN fact_characters p ON p.id = b.character_id
             WHERE c.season_id = ? AND b.participation_role = 'player'
             ORDER BY p.name ASC"
        );
        if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, 'i', $seasonId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('hg_admin_exports_season')) {
    function hg_admin_exports_season(mysqli $link, int $seasonId): string
    {
        $row = hg_chapters_fetch_season_detail($link, $seasonId);
        if (!$row) return '';
        $sections = [];
        $kind = trim((string)($row['season_kind'] ?? 'temporada')) ?: 'temporada';
        $status = (int)($row['finished'] ?? 0);
        $statusText = $status === 1 ? 'Finalizada' : ($status === 2 ? 'Cancelada' : 'En curso');
        hg_admin_exports_section($sections, 'TEMPORADA', [
            'ID: ' . $seasonId,
            'Nombre: ' . ($row['name'] ?? ''),
            'Número: ' . (int)($row['season_number'] ?? 0),
            'Tipo: ' . $kind,
            'Crónica: ' . ($row['chronicle_name'] ?? ''),
            'Estado: ' . $statusText,
        ]);
        hg_admin_exports_section($sections, 'SINOPSIS', [(string)($row['description'] ?? '')]);

        $participants = [];
        foreach (hg_admin_exports_season_participants($link, $seasonId) as $p) {
            $participants[] = (string)$p['name'];
        }
        hg_admin_exports_section($sections, 'PROTAGONISTAS', $participants);

        $chapters = [];
        foreach (hg_chapters_fetch_season_chapters($link, $seasonId) as $chapter) {
            $chapters[] = sprintf(
                '%02d | %s',
                (int)($chapter['chapter_number'] ?? 0),
                (string)($chapter['name'] ?? '')
            );
        }
        hg_admin_exports_section($sections, 'EPISODIOS', $chapters);
        return trim(implode("\n\n", $sections));
    }
}

if (!function_exists('hg_admin_exports_chapter')) {
    function hg_admin_exports_chapter(mysqli $link, int $chapterId): string
    {
        $row = hg_chapters_fetch_chapter_detail($link, $chapterId);
        if (!$row) return '';
        $sections = [];
        hg_admin_exports_section($sections, 'EPISODIO', [
            'ID: ' . $chapterId,
            'Nombre: ' . ($row['name'] ?? ''),
            'Número: ' . (int)($row['chapter_number'] ?? 0),
            'Temporada: ' . ($row['season_name'] ?? ''),
            'Número de temporada: ' . (int)($row['season_number'] ?? 0),
            'Tipo de temporada: ' . ($row['season_kind'] ?? 'temporada'),
            'Fecha de juego: ' . ($row['played_date'] ?? ''),
        ]);
        hg_admin_exports_section($sections, 'RESUMEN', [(string)($row['synopsis'] ?? '')]);

        $participants = [];
        foreach (hg_chapters_fetch_chapter_participants($link, $chapterId) as $p) {
            $role = strtolower(trim((string)($p['participation_role'] ?? 'npc'))) === 'player' ? 'PJ' : 'PNJ';
            $participants[] = '[' . $role . '] ' . (string)($p['name'] ?? '');
        }
        hg_admin_exports_section($sections, 'PARTICIPANTES', $participants);

        $events = [];
        foreach (hg_chapters_fetch_chapter_events($link, $chapterId, 1000) as $event) {
            $line = trim((string)($event['title'] ?? 'Evento'));
            $date = trim((string)($event['event_date'] ?? ''));
            if ($date !== '') $line .= ' (' . $date . ')';
            $events[] = $line;
        }
        hg_admin_exports_section($sections, 'EVENTOS RELACIONADOS', $events);
        return trim(implode("\n\n", $sections));
    }
}

if (!function_exists('hg_admin_exports_document')) {
    function hg_admin_exports_document(mysqli $link, int $documentId): string
    {
        $row = hg_documents_fetch_mobile_detail($link, $documentId);
        if (!$row) return '';
        $sections = [];
        hg_admin_exports_section($sections, 'DOCUMENTO', [
            'ID: ' . $documentId,
            'Título: ' . ($row['title'] ?? ''),
            'Sección: ' . ($row['category'] ?? ''),
            'Origen: ' . ($row['origin'] ?? ''),
            'Fuente: ' . ($row['source'] ?? ''),
        ]);
        hg_admin_exports_section($sections, 'CONTENIDO', [(string)($row['content'] ?? '')]);

        $characters = [];
        foreach (hg_documents_fetch_characters($link, $documentId) as $character) {
            $characters[] = (string)($character['name'] ?? '');
        }
        hg_admin_exports_section($sections, 'PERSONAJES RELACIONADOS', $characters);
        return trim(implode("\n\n", $sections));
    }
}

if (!function_exists('hg_admin_exports_inventory')) {
    function hg_admin_exports_inventory(mysqli $link, int $itemId): string
    {
        $row = hg_inventory_fetch_item($link, $itemId);
        if (!$row) return '';
        $sections = [];
        $metal = (int)($row['metal'] ?? 0);
        $metalText = $metal === 1 ? 'Plata' : ($metal === 2 ? 'Oro' : '');
        $meta = [
            'ID: ' . $itemId,
            'Nombre: ' . ($row['name'] ?? ''),
            'Pretty ID: ' . ($row['pretty_id'] ?? ''),
            'Tipo: ' . ($row['item_type_name'] ?? ''),
            'Origen: ' . ($row['bibliography_name'] ?? ''),
        ];
        foreach ([
            'Habilidad' => $row['skill_name'] ?? '',
            'Nivel' => $row['level'] ?? 0,
            'Gnosis' => $row['gnosis'] ?? 0,
            'Valoración' => $row['rating'] ?? '',
            'Bonificación' => $row['bonus'] ?? 0,
            'Tipo de daño' => $row['damage_type'] ?? '',
            'Metal' => $metalText,
            'Fuerza mínima' => $row['strength_req'] ?? 0,
            'Penalización Destreza' => $row['dexterity_req'] ?? 0,
        ] as $label => $value) {
            if ((string)$value !== '' && (string)$value !== '0') $meta[] = $label . ': ' . $value;
        }
        hg_admin_exports_section($sections, 'OBJETO', $meta);
        hg_admin_exports_section($sections, 'DESCRIPCION', [(string)($row['description'] ?? '')]);

        $owners = [];
        foreach (hg_inventory_fetch_owners($link, $itemId) as $owner) {
            $owners[] = (string)($owner['name'] ?? '');
        }
        hg_admin_exports_section($sections, 'PORTADORES', $owners);
        return trim(implode("\n\n", $sections));
    }
}

if (!function_exists('hg_admin_exports_record')) {
    function hg_admin_exports_record(mysqli $link, string $kind, int $id): string
    {
        switch ($kind) {
            case 'characters': return hg_admin_exports_character($link, $id);
            case 'seasons': return hg_admin_exports_season($link, $id);
            case 'chapters': return hg_admin_exports_chapter($link, $id);
            case 'documents': return hg_admin_exports_document($link, $id);
            case 'inventory': return hg_admin_exports_inventory($link, $id);
            default: return '';
        }
    }
}

if (!function_exists('hg_admin_exports_chunk')) {
    function hg_admin_exports_chunk(
        mysqli $link,
        string $kind,
        array $chronicleIds,
        bool $includeUnscoped,
        int $afterId
    ): array {
        $ids = hg_admin_exports_next_ids($link, $kind, $chronicleIds, $includeUnscoped, $afterId);
        $blocks = [];
        foreach ($ids as $id) {
            $record = hg_admin_exports_record($link, $kind, $id);
            if ($record === '') continue;
            $blocks[] = str_repeat('#', 72) . "\n" . $record;
        }
        return [
            'text' => $blocks ? implode("\n\n", $blocks) . "\n\n" : '',
            'count' => count($ids),
            'next_cursor' => $ids ? max($ids) : $afterId,
            'done' => count($ids) < hg_admin_exports_batch_limit($kind),
        ];
    }
}
