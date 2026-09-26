<?php

include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../helpers/content_updates.php');

if (!function_exists('hg_maps_admin_fetch_all')) {
    function hg_maps_admin_fetch_all(mysqli $link): array
    {
        $maps = [];
        $result = $link->query(
            "SELECT id,name,slug,center_lat,center_lng,default_zoom,min_zoom,max_zoom,
                    bounds_sw_lat,bounds_sw_lng,bounds_ne_lat,bounds_ne_lng,default_tile,
                    created_at,updated_at
             FROM dim_maps ORDER BY name"
        );
        if ($result) {
            while ($row = $result->fetch_assoc()) $maps[] = $row;
            $result->close();
        }

        $cats = [];
        $result = $link->query(
            "SELECT id,name,slug,color_hex,icon,sort_order,created_at,updated_at
             FROM dim_map_categories ORDER BY sort_order, name"
        );
        if ($result) {
            while ($row = $result->fetch_assoc()) $cats[] = $row;
            $result->close();
        }

        $pois = [];
        $result = $link->query(
            "SELECT p.id, p.name, p.description, p.map_id, m.name AS map_name,
                    p.category_id, c.name AS category_name,
                    p.thumbnail, p.latitude, p.longitude,
                    p.created_at, p.updated_at
             FROM fact_map_pois p
             JOIN dim_maps m ON m.id=p.map_id
             JOIN dim_map_categories c ON c.id=p.category_id
             ORDER BY p.id DESC"
        );
        if ($result) {
            while ($row = $result->fetch_assoc()) $pois[] = $row;
            $result->close();
        }

        $areas = [];
        $result = $link->query(
            "SELECT a.id, a.name, a.map_id, m.name AS map_name,
                    a.description, a.color_hex,
                    a.geometry, a.created_at, a.updated_at
             FROM fact_map_areas a
             JOIN dim_maps m ON m.id=a.map_id
             ORDER BY a.id DESC"
        );
        if ($result) {
            while ($row = $result->fetch_assoc()) $areas[] = $row;
            $result->close();
        }

        return ['maps' => $maps, 'cats' => $cats, 'pois' => $pois, 'areas' => $areas];
    }
}

if (!function_exists('hg_maps_admin_save_map')) {
    function hg_maps_admin_save_map(mysqli $link, array $v): int
    {
        $id = (int)($v['id'] ?? 0);
        if ($id > 0) {
            $stmt = $link->prepare(
                "UPDATE dim_maps
                 SET name=?, slug=?, center_lat=?, center_lng=?, default_zoom=?, min_zoom=?, max_zoom=?,
                     bounds_sw_lat=?, bounds_sw_lng=?, bounds_ne_lat=?, bounds_ne_lng=?, default_tile=?
                 WHERE id=?"
            );
            $stmt->bind_param(
                'ssddiiiddddsi',
                $v['name'], $v['slug'], $v['center_lat'], $v['center_lng'], $v['default_zoom'],
                $v['min_zoom'], $v['max_zoom'], $v['bounds_sw_lat'], $v['bounds_sw_lng'],
                $v['bounds_ne_lat'], $v['bounds_ne_lng'], $v['default_tile'], $id
            );
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $link->prepare(
                "INSERT INTO dim_maps
                 (name,slug,center_lat,center_lng,default_zoom,min_zoom,max_zoom,
                  bounds_sw_lat,bounds_sw_lng,bounds_ne_lat,bounds_ne_lng,default_tile)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $stmt->bind_param(
                'ssddiiidddds',
                $v['name'], $v['slug'], $v['center_lat'], $v['center_lng'], $v['default_zoom'],
                $v['min_zoom'], $v['max_zoom'], $v['bounds_sw_lat'], $v['bounds_sw_lng'],
                $v['bounds_ne_lat'], $v['bounds_ne_lng'], $v['default_tile']
            );
            $stmt->execute();
            $id = (int)$stmt->insert_id;
            $stmt->close();
        }

        hg_update_pretty_id_if_exists($link, 'dim_maps', $id, (string)$v['name']);
        hg_content_touch_table($link, 'dim_maps', $id);
        return $id;
    }
}

if (!function_exists('hg_maps_admin_delete_map')) {
    function hg_maps_admin_delete_map(mysqli $link, int $id): void
    {
        $stmt = $link->prepare("DELETE FROM dim_maps WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('hg_maps_admin_save_category')) {
    function hg_maps_admin_save_category(mysqli $link, array $v): int
    {
        $id = (int)($v['id'] ?? 0);
        if ($id > 0) {
            $stmt = $link->prepare(
                "UPDATE dim_map_categories SET name=?, slug=?, color_hex=?, icon=?, sort_order=? WHERE id=?"
            );
            $stmt->bind_param('ssssii', $v['name'], $v['slug'], $v['color_hex'], $v['icon'], $v['sort_order'], $id);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $link->prepare(
                "INSERT INTO dim_map_categories (name,slug,color_hex,icon,sort_order) VALUES (?,?,?,?,?)"
            );
            $stmt->bind_param('ssssi', $v['name'], $v['slug'], $v['color_hex'], $v['icon'], $v['sort_order']);
            $stmt->execute();
            $id = (int)$stmt->insert_id;
            $stmt->close();
        }

        hg_update_pretty_id_if_exists($link, 'dim_map_categories', $id, (string)$v['name']);
        return $id;
    }
}

if (!function_exists('hg_maps_admin_category_usage_count')) {
    function hg_maps_admin_category_usage_count(mysqli $link, int $id): int
    {
        $total = 0;
        $stmt = $link->prepare("SELECT COUNT(*) AS n FROM fact_map_pois WHERE category_id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $total += (int)(($result ? $result->fetch_assoc()['n'] ?? 0 : 0));
        $stmt->close();

        if (hg_table_has_column($link, 'fact_map_areas', 'category_id')) {
            $stmt = $link->prepare("SELECT COUNT(*) AS n FROM fact_map_areas WHERE category_id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $total += (int)(($result ? $result->fetch_assoc()['n'] ?? 0 : 0));
            $stmt->close();
        }

        return $total;
    }
}

if (!function_exists('hg_maps_admin_delete_category')) {
    function hg_maps_admin_delete_category(mysqli $link, int $id): void
    {
        $stmt = $link->prepare("DELETE FROM dim_map_categories WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('hg_maps_admin_save_poi')) {
    function hg_maps_admin_save_poi(mysqli $link, array $v): int
    {
        $id = (int)($v['id'] ?? 0);
        if ($id > 0) {
            $stmt = $link->prepare(
                "UPDATE fact_map_pois
                 SET name=?, map_id=?, category_id=?, description=?, thumbnail=?, latitude=?, longitude=?
                 WHERE id=?"
            );
            $stmt->bind_param(
                'siissddi',
                $v['name'], $v['map_id'], $v['category_id'], $v['description'], $v['thumbnail'],
                $v['latitude'], $v['longitude'], $id
            );
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $link->prepare(
                "INSERT INTO fact_map_pois
                 (name,map_id,category_id,description,thumbnail,latitude,longitude)
                 VALUES (?,?,?,?,?,?,?)"
            );
            $stmt->bind_param(
                'siissdd',
                $v['name'], $v['map_id'], $v['category_id'], $v['description'], $v['thumbnail'],
                $v['latitude'], $v['longitude']
            );
            $stmt->execute();
            $id = (int)$stmt->insert_id;
            $stmt->close();
        }

        hg_update_pretty_id_if_exists($link, 'fact_map_pois', $id, (string)$v['name']);
        hg_content_touch_table($link, 'dim_maps', (int)$v['map_id']);
        return $id;
    }
}

if (!function_exists('hg_maps_admin_delete_poi')) {
    function hg_maps_admin_delete_poi(mysqli $link, int $id): void
    {
        $stmt = $link->prepare("DELETE FROM fact_map_pois WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('hg_maps_admin_save_area')) {
    function hg_maps_admin_save_area(mysqli $link, array $v): int
    {
        $id = (int)($v['id'] ?? 0);
        if ($id > 0) {
            $stmt = $link->prepare(
                "UPDATE fact_map_areas
                 SET name=?, map_id=?, description=?, color_hex=?, geometry=?
                 WHERE id=?"
            );
            $stmt->bind_param(
                'sisssi',
                $v['name'], $v['map_id'], $v['description'], $v['color_hex'], $v['geometry'], $id
            );
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $link->prepare(
                "INSERT INTO fact_map_areas
                 (name,map_id,description,color_hex,geometry)
                 VALUES (?,?,?,?,?)"
            );
            $stmt->bind_param(
                'sisss',
                $v['name'], $v['map_id'], $v['description'], $v['color_hex'], $v['geometry']
            );
            $stmt->execute();
            $id = (int)$stmt->insert_id;
            $stmt->close();
        }

        hg_update_pretty_id_if_exists($link, 'fact_map_areas', $id, (string)$v['name']);
        hg_content_touch_table($link, 'dim_maps', (int)$v['map_id']);
        return $id;
    }
}

if (!function_exists('hg_maps_admin_delete_area')) {
    function hg_maps_admin_delete_area(mysqli $link, int $id): void
    {
        $stmt = $link->prepare("DELETE FROM fact_map_areas WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
}
