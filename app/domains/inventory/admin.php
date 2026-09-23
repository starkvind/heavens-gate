<?php
// Admin data ownership for inventory editors.

function hg_inventory_admin_fetch_all(mysqli $link, string $sql): array {
    $rows = [];
    if ($rs = $link->query($sql)) {
        while ($row = $rs->fetch_assoc()) $rows[] = $row;
        $rs->free();
    }
    return $rows;
}

function hg_inventory_admin_item_options(mysqli $link): array {
    return [
        'types' => hg_inventory_admin_fetch_all($link, 'SELECT id, name FROM dim_item_types ORDER BY name ASC'),
        'origins' => hg_inventory_admin_fetch_all($link, 'SELECT id, name FROM dim_bibliographies ORDER BY name ASC'),
    ];
}

function hg_inventory_admin_item_delete(mysqli $link, int $id): array {
    if ($id <= 0) return ['ok'=>false,'error'=>'invalid_id','errno'=>0];
    try {
        $st = $link->prepare('DELETE FROM fact_items WHERE id = ?');
        if (!$st) return ['ok'=>false,'error'=>$link->error,'errno'=>(int)$link->errno];
        $st->bind_param('i', $id);
        $ok = $st->execute();
        $error = $st->error;
        $errno = $st->errno;
        $st->close();
        return ['ok'=>$ok,'error'=>$error,'errno'=>$errno];
    } catch (mysqli_sql_exception $e) {
        return ['ok'=>false,'error'=>$e->getMessage(),'errno'=>(int)$e->getCode()];
    }
}

function hg_inventory_admin_item_save(mysqli $link, int $id, array $d): array {
    try {
        if ($id > 0) {
            $st = $link->prepare('UPDATE fact_items
                SET name=?, item_type_id=?, skill_name=?, level=?, gnosis=?, rating=?, bonus=?, damage_type=?, metal=?, strength_req=?, dexterity_req=?, image_url=?, description=?, bibliography_id=NULLIF(?, 0)
                WHERE id=?');
            if (!$st) return ['ok'=>false,'error'=>$link->error,'errno'=>(int)$link->errno,'id'=>$id];
            $st->bind_param(
                'sisiiiisiiissii',
                $d['name'], $d['item_type_id'], $d['skill_name'], $d['level'], $d['gnosis'], $d['rating'], $d['bonus'],
                $d['damage_type'], $d['metal'], $d['strength_req'], $d['dexterity_req'], $d['image_url'],
                $d['description'], $d['bibliography_id'], $id
            );
            $ok = $st->execute();
            $error = $st->error;
            $errno = $st->errno;
            $st->close();
            return ['ok'=>$ok,'error'=>$error,'errno'=>$errno,'id'=>$id];
        }

        $st = $link->prepare('INSERT INTO fact_items
            (name, item_type_id, skill_name, level, gnosis, rating, bonus, damage_type, metal, strength_req, dexterity_req, image_url, description, bibliography_id)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NULLIF(?, 0))');
        if (!$st) return ['ok'=>false,'error'=>$link->error,'errno'=>(int)$link->errno,'id'=>0];
        $st->bind_param(
            'sisiiiisiiissi',
            $d['name'], $d['item_type_id'], $d['skill_name'], $d['level'], $d['gnosis'], $d['rating'], $d['bonus'],
            $d['damage_type'], $d['metal'], $d['strength_req'], $d['dexterity_req'], $d['image_url'],
            $d['description'], $d['bibliography_id']
        );
        $ok = $st->execute();
        $error = $st->error;
        $errno = $st->errno;
        $newId = $ok ? (int)$link->insert_id : 0;
        $st->close();
        return ['ok'=>$ok,'error'=>$error,'errno'=>$errno,'id'=>$newId];
    } catch (mysqli_sql_exception $e) {
        return ['ok'=>false,'error'=>$e->getMessage(),'errno'=>(int)$e->getCode(),'id'=>$id];
    }
}

function hg_inventory_admin_item_fetch(mysqli $link, int $id): ?array {
    if ($id <= 0) return null;
    $st = $link->prepare('SELECT * FROM fact_items WHERE id=?');
    if (!$st) return null;
    $st->bind_param('i', $id);
    $st->execute();
    $rs = $st->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $st->close();
    return $row ?: null;
}

function hg_inventory_admin_item_rows(mysqli $link): array {
    return hg_inventory_admin_fetch_all($link, 'SELECT id, name, item_type_id, bibliography_id FROM fact_items ORDER BY name ASC');
}

function hg_inventory_admin_item_rows_full(mysqli $link): array {
    return hg_inventory_admin_fetch_all($link, 'SELECT * FROM fact_items ORDER BY name ASC');
}
