<?php

include_once(__DIR__ . '/../../helpers/pretty.php');

if (!function_exists('hg_rules_admin_maneuver_protected_pretty_ids')) {
    function hg_rules_admin_maneuver_protected_pretty_ids(): array
    {
        return ['patada', 'punetazo', 'placaje', 'presa', 'barrido'];
    }
}

if (!function_exists('hg_rules_admin_maneuver_is_protected')) {
    function hg_rules_admin_maneuver_is_protected(array $row): bool
    {
        $pretty = trim((string)($row['pretty_id'] ?? ''));
        return $pretty !== '' && in_array($pretty, hg_rules_admin_maneuver_protected_pretty_ids(), true);
    }
}

if (!function_exists('hg_rules_admin_maneuver_options')) {
    function hg_rules_admin_maneuver_options(mysqli $link): array
    {
        $systems = [];
        if ($rs = $link->query('SELECT id, name FROM dim_systems ORDER BY sort_order, name')) {
            while ($row = $rs->fetch_assoc()) $systems[] = $row;
            $rs->free();
        }

        $bibliographies = [];
        if ($rs = $link->query('SELECT id, name FROM dim_bibliographies ORDER BY name')) {
            while ($row = $rs->fetch_assoc()) $bibliographies[] = $row;
            $rs->free();
        }

        return compact('systems', 'bibliographies');
    }
}

if (!function_exists('hg_rules_admin_maneuver_system_name')) {
    function hg_rules_admin_maneuver_system_name(mysqli $link, int $systemId): string
    {
        if ($systemId <= 0) return '';
        $st = $link->prepare('SELECT name FROM dim_systems WHERE id = ? LIMIT 1');
        if (!$st) return '';
        $st->bind_param('i', $systemId);
        $st->execute();
        $rs = $st->get_result();
        $row = $rs ? $rs->fetch_assoc() : null;
        $st->close();
        return trim((string)($row['name'] ?? ''));
    }
}

if (!function_exists('hg_rules_admin_maneuver_rows')) {
    function hg_rules_admin_maneuver_rows(mysqli $link): array
    {
        $rows = [];
        $sql = "SELECT m.id, m.pretty_id, m.name, m.user, m.roll, m.system_id,
                       COALESCE(NULLIF(s.name, ''), m.system_name) AS system_name,
                       m.bibliography_id
                FROM fact_combat_maneuvers m
                LEFT JOIN dim_systems s ON s.id = m.system_id
                ORDER BY system_name, m.name";
        if ($rs = $link->query($sql)) {
            while ($row = $rs->fetch_assoc()) $rows[] = $row;
            $rs->free();
        }
        return $rows;
    }
}

if (!function_exists('hg_rules_admin_maneuver_fetch')) {
    function hg_rules_admin_maneuver_fetch(mysqli $link, int $id): ?array
    {
        if ($id <= 0) return null;
        $st = $link->prepare(
            "SELECT m.*, COALESCE(NULLIF(s.name, ''), m.system_name) AS canonical_system_name,
                    COALESCE(b.name, '') AS bibliography_name
             FROM fact_combat_maneuvers m
             LEFT JOIN dim_systems s ON s.id = m.system_id
             LEFT JOIN dim_bibliographies b ON b.id = m.bibliography_id
             WHERE m.id = ? LIMIT 1"
        );
        if (!$st) return null;
        $st->bind_param('i', $id);
        $st->execute();
        $rs = $st->get_result();
        $row = $rs ? $rs->fetch_assoc() : null;
        $st->close();
        return $row ?: null;
    }
}

if (!function_exists('hg_rules_admin_maneuver_normalize_payload')) {
    function hg_rules_admin_maneuver_normalize_payload(mysqli $link, array $data): array
    {
        $systemId = max(0, (int)($data['system_id'] ?? 0));
        return [
            'name' => trim((string)($data['name'] ?? '')),
            'image_url' => trim((string)($data['image_url'] ?? '')),
            'text' => trim((string)($data['text'] ?? '')),
            'user' => trim((string)($data['user'] ?? '')),
            'roll' => trim((string)($data['roll'] ?? '')),
            'difficulty' => trim((string)($data['difficulty'] ?? '')),
            'damage' => trim((string)($data['damage'] ?? '')),
            'actions' => trim((string)($data['actions'] ?? '')),
            'system_id' => $systemId,
            'system_name' => hg_rules_admin_maneuver_system_name($link, $systemId),
            'bibliography_id' => max(0, (int)($data['bibliography_id'] ?? 0)),
        ];
    }
}

if (!function_exists('hg_rules_admin_maneuver_create')) {
    function hg_rules_admin_maneuver_create(mysqli $link, array $data): array
    {
        $d = hg_rules_admin_maneuver_normalize_payload($link, $data);
        if ($d['name'] === '') return ['ok' => false, 'id' => 0, 'message' => 'El nombre es obligatorio.'];

        $expectedPretty = hg_pretty_expected_slug('fact_combat_maneuvers', $d['name']);
        if ($expectedPretty !== '') {
            $check = $link->prepare('SELECT id FROM fact_combat_maneuvers WHERE pretty_id = ? LIMIT 1');
            if ($check) {
                $check->bind_param('s', $expectedPretty);
                $check->execute();
                $exists = $check->get_result()->fetch_assoc();
                $check->close();
                if ($exists) {
                    return ['ok' => false, 'id' => 0, 'message' => 'Ya existe una maniobra con ese identificador URL.'];
                }
            }
        }

        $st = $link->prepare(
            "INSERT INTO fact_combat_maneuvers
                (image_url, name, text, user, roll, difficulty, damage, actions, system_name, system_id, bibliography_id)
             VALUES
                (NULLIF(?, ''), ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0))"
        );
        if (!$st) return ['ok' => false, 'id' => 0, 'message' => $link->error];

        $st->bind_param(
            'sssssssssii',
            $d['image_url'], $d['name'], $d['text'], $d['user'], $d['roll'],
            $d['difficulty'], $d['damage'], $d['actions'], $d['system_name'],
            $d['system_id'], $d['bibliography_id']
        );
        $ok = $st->execute();
        $error = $st->error;
        $id = $ok ? (int)$link->insert_id : 0;
        $st->close();

        if (!$ok || $id <= 0) return ['ok' => false, 'id' => 0, 'message' => $error !== '' ? $error : 'No se pudo crear la maniobra.'];

        hg_update_pretty_id_if_exists($link, 'fact_combat_maneuvers', $id, $d['name']);
        hg_content_touch_table($link, 'fact_combat_maneuvers', $id);
        return ['ok' => true, 'id' => $id, 'message' => 'Maniobra creada.'];
    }
}

if (!function_exists('hg_rules_admin_maneuver_update')) {
    function hg_rules_admin_maneuver_update(mysqli $link, int $id, array $data): array
    {
        if ($id <= 0) return ['ok' => false, 'message' => 'Maniobra inválida.'];
        $current = hg_rules_admin_maneuver_fetch($link, $id);
        if (!$current) return ['ok' => false, 'message' => 'La maniobra no existe.'];

        $d = hg_rules_admin_maneuver_normalize_payload($link, $data);
        if ($d['name'] === '') return ['ok' => false, 'message' => 'El nombre es obligatorio.'];

        $st = $link->prepare(
            "UPDATE fact_combat_maneuvers
             SET image_url = NULLIF(?, ''),
                 name = ?, text = ?, user = ?, roll = ?, difficulty = ?, damage = ?, actions = ?,
                 system_name = ?, system_id = NULLIF(?, 0), bibliography_id = NULLIF(?, 0), updated_at = NOW()
             WHERE id = ?"
        );
        if (!$st) return ['ok' => false, 'message' => $link->error];

        $st->bind_param(
            'sssssssssiii',
            $d['image_url'], $d['name'], $d['text'], $d['user'], $d['roll'],
            $d['difficulty'], $d['damage'], $d['actions'], $d['system_name'],
            $d['system_id'], $d['bibliography_id'], $id
        );
        $ok = $st->execute();
        $error = $st->error;
        $st->close();
        if (!$ok) return ['ok' => false, 'message' => $error !== '' ? $error : 'No se pudo guardar la maniobra.'];

        // Maneuver pretty_id is stable after creation. Baseline assignment helpers use
        // five historical slugs as machine identities, so renaming must not rewrite it.
        hg_content_touch_table($link, 'fact_combat_maneuvers', $id);
        return ['ok' => true, 'message' => 'Maniobra guardada.'];
    }
}

if (!function_exists('hg_rules_admin_maneuver_delete')) {
    function hg_rules_admin_maneuver_delete(mysqli $link, int $id): array
    {
        if ($id <= 0) return ['ok' => false, 'message' => 'Maniobra inválida.'];
        $row = hg_rules_admin_maneuver_fetch($link, $id);
        if (!$row) return ['ok' => false, 'message' => 'La maniobra no existe.'];
        if (hg_rules_admin_maneuver_is_protected($row)) {
            return ['ok' => false, 'message' => 'Esta maniobra es una de las cinco maniobras base usadas al crear sistemas y no se puede borrar desde el administrador.'];
        }

        $st = $link->prepare('DELETE FROM fact_combat_maneuvers WHERE id = ?');
        if (!$st) return ['ok' => false, 'message' => $link->error];
        $st->bind_param('i', $id);
        $ok = $st->execute();
        $error = $st->error;
        $affected = $st->affected_rows;
        $st->close();

        if (!$ok || $affected < 1) {
            return ['ok' => false, 'message' => $error !== '' ? $error : 'No se pudo borrar la maniobra.'];
        }
        return ['ok' => true, 'message' => 'Maniobra borrada. Sus enlaces de sistema y Forma se han limpiado por FK CASCADE.'];
    }
}
