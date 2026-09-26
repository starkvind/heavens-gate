<?php

include_once(__DIR__ . '/../../helpers/pretty.php');

if (!function_exists('hg_news_admin_delete')) {
    function hg_news_admin_delete(mysqli $link, int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'ID invalido para borrar.'];
        }

        $stmt = $link->prepare("DELETE FROM fact_admin_posts WHERE id = ?");
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Error al preparar DELETE: ' . $link->error];
        }

        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $error = $stmt->error;
        $stmt->close();

        return $ok
            ? ['ok' => true, 'message' => 'Noticia eliminada.']
            : ['ok' => false, 'message' => 'Error al borrar: ' . $error];
    }
}

if (!function_exists('hg_news_admin_save')) {
    function hg_news_admin_save(
        mysqli $link,
        int $id,
        string $author,
        string $title,
        string $message
    ): array {
        if ($id > 0) {
            $stmt = $link->prepare(
                "UPDATE fact_admin_posts SET author=?, title=?, message=?, posted_at=NOW() WHERE id=?"
            );
            if (!$stmt) {
                return ['handled' => false];
            }

            $stmt->bind_param('sssi', $author, $title, $message, $id);
            $stmt->execute();
            hg_update_pretty_id_if_exists($link, 'fact_admin_posts', $id, $title);
            $stmt->close();

            return ['handled' => true, 'ok' => true, 'message' => 'Noticia actualizada.'];
        }

        $stmt = $link->prepare(
            "INSERT INTO fact_admin_posts (author, title, message, posted_at) VALUES (?,?,?,NOW())"
        );
        if (!$stmt) {
            return ['handled' => false];
        }

        $stmt->bind_param('sss', $author, $title, $message);
        $stmt->execute();
        $newId = (int)$link->insert_id;
        hg_update_pretty_id_if_exists($link, 'fact_admin_posts', $newId, $title);
        $stmt->close();

        return ['handled' => true, 'ok' => true, 'message' => 'Noticia creada.'];
    }
}

if (!function_exists('hg_news_admin_fetch_rows')) {
    function hg_news_admin_fetch_rows(mysqli $link): ?array
    {
        $result = $link->query(
            "SELECT id, author, title, posted_at FROM fact_admin_posts ORDER BY id DESC"
        );
        if (!$result) {
            return null;
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->close();

        return $rows;
    }
}

if (!function_exists('hg_news_admin_fetch_rows_full')) {
    function hg_news_admin_fetch_rows_full(mysqli $link): ?array
    {
        $result = $link->query(
            "SELECT id, author, title, message FROM fact_admin_posts ORDER BY id DESC"
        );
        if (!$result) {
            return null;
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->close();

        return $rows;
    }
}
