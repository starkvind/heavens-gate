<?php

if (!function_exists('hg_soundtracks_fetch_for_object')) {
    function hg_soundtracks_fetch_for_object(mysqli $link, string $objectType, int $objectId): ?array
    {
        if ($objectId <= 0 || !in_array($objectType, ['personaje', 'temporada', 'episodio'], true)) {
            return [];
        }

        $stmt = mysqli_prepare(
            $link,
            "SELECT bs.context_title, bs.title AS titulo_real, bs.artist, bs.youtube_url AS enlace
             FROM bridge_soundtrack_links br
             JOIN dim_soundtracks bs ON bs.id = br.soundtrack_id
             WHERE br.object_type = ? AND br.object_id = ?
             ORDER BY bs.added_at DESC"
        );
        if (!$stmt) {
            return null;
        }

        mysqli_stmt_bind_param($stmt, 'si', $objectType, $objectId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return null;
        }

        $result = mysqli_stmt_get_result($stmt);
        if (!$result) {
            mysqli_stmt_close($stmt);
            return null;
        }

        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_free_result($result);
        mysqli_stmt_close($stmt);

        return $rows;
    }
}
