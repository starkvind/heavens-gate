-- Heaven's Gate — Auditoría editorial Gaia0
-- SOLO LECTURA. Compatible con MariaDB 10.5 / DBeaver.
-- Objetivo: obtener la fotografía ACTUAL del dump/BDD tras añadir Gaia1-B.
-- No modifica ningún dato.

SET @gaia0 := (
    SELECT id
    FROM dim_realities
    WHERE pretty_id = 'gaia-zero' OR name = 'Gaia0'
    ORDER BY (pretty_id = 'gaia-zero') DESC, id ASC
    LIMIT 1
);

SET @aullidos := (
    SELECT id
    FROM dim_chronicles
    WHERE pretty_id = 'aullidos-en-el-norte'
       OR name IN ('Aullidos en el Norte', 'Partida Original')
    ORDER BY (pretty_id = 'aullidos-en-el-norte') DESC, id ASC
    LIMIT 1
);

-- 1. Realidades actuales: debe aparecer ya Gaia1-B / Gaia1β.
SELECT
    id,
    pretty_id,
    name,
    description,
    sort_order,
    is_active
FROM dim_realities
ORDER BY sort_order, id;

-- 2. Estado general de contenido editorial.
SELECT 'personajes_total' AS metrica, COUNT(*) AS valor FROM fact_characters
UNION ALL
SELECT 'biografias_vacias', COUNT(*) FROM fact_characters
 WHERE TRIM(COALESCE(info_text, '')) = ''
UNION ALL
SELECT 'capitulos_total', COUNT(*) FROM dim_chapters
UNION ALL
SELECT 'capitulos_sin_sinopsis', COUNT(*) FROM dim_chapters
 WHERE TRIM(COALESCE(synopsis, '')) = ''
UNION ALL
SELECT 'capitulos_sin_eventos', COUNT(*)
FROM dim_chapters c
LEFT JOIN bridge_timeline_events_chapters b ON b.chapter_id = c.id
WHERE b.event_id IS NULL
UNION ALL
SELECT 'eventos_total', COUNT(*) FROM fact_timeline_events
UNION ALL
SELECT 'eventos_sin_realidad', COUNT(*)
FROM fact_timeline_events e
LEFT JOIN bridge_timeline_events_realities br ON br.event_id = e.id
WHERE br.event_id IS NULL;

-- 3. Biografías públicas vacías.
SELECT
    c.id,
    c.pretty_id,
    c.name,
    c.alias,
    c.garou_name,
    ch.name AS cronica,
    r.name AS realidad,
    c.character_kind,
    c.status,
    c.updated_at
FROM fact_characters c
LEFT JOIN dim_chronicles ch ON ch.id = c.chronicle_id
LEFT JOIN dim_realities r ON r.id = c.reality_id
WHERE TRIM(COALESCE(c.info_text, '')) = ''
ORDER BY ch.name, r.name, c.name;

-- 4. Episodios sin sinopsis.
SELECT
    c.id,
    c.pretty_id,
    s.name AS temporada,
    s.season_number,
    s.season_kind,
    c.chapter_number,
    c.name AS episodio,
    c.played_date
FROM dim_chapters c
LEFT JOIN dim_seasons s ON s.id = c.season_id
WHERE TRIM(COALESCE(c.synopsis, '')) = ''
ORDER BY COALESCE(s.sort_order, 999999), c.chapter_number, c.id;

-- 5. Episodios sin ningún evento asociado.
SELECT
    c.id,
    c.pretty_id,
    s.name AS temporada,
    s.season_number,
    c.chapter_number,
    c.name AS episodio,
    COUNT(b.event_id) AS eventos
FROM dim_chapters c
LEFT JOIN dim_seasons s ON s.id = c.season_id
LEFT JOIN bridge_timeline_events_chapters b ON b.chapter_id = c.id
GROUP BY c.id, c.pretty_id, s.name, s.season_number, c.chapter_number, c.name
HAVING COUNT(b.event_id) = 0
ORDER BY COALESCE(s.season_number, 999999), c.chapter_number, c.id;

-- 6. Eventos que no tienen ninguna realidad explícita.
SELECT
    e.id,
    e.pretty_id,
    e.event_date,
    e.date_precision,
    e.date_note,
    e.title,
    COALESCE(t.name, 'Evento') AS tipo,
    GROUP_CONCAT(DISTINCT ch.name ORDER BY ch.name SEPARATOR ' | ') AS cronicas
FROM fact_timeline_events e
LEFT JOIN dim_timeline_events_types t ON t.id = e.event_type_id
LEFT JOIN bridge_timeline_events_realities br ON br.event_id = e.id
LEFT JOIN bridge_timeline_events_chronicles bc ON bc.event_id = e.id
LEFT JOIN dim_chronicles ch ON ch.id = bc.chronicle_id
WHERE br.event_id IS NULL
GROUP BY e.id, e.pretty_id, e.event_date, e.date_precision, e.date_note, e.title, t.name
ORDER BY e.event_date, e.id;

-- 7. Gaia0: personajes que la BDD considera actualmente de esa realidad.
SELECT
    c.id,
    c.pretty_id,
    c.name,
    c.alias,
    c.garou_name,
    ch.name AS cronica,
    c.status,
    CASE
        WHEN TRIM(COALESCE(c.info_text, '')) = '' THEN 'BIO_VACIA'
        ELSE 'BIO_OK'
    END AS estado_bio
FROM fact_characters c
LEFT JOIN dim_chronicles ch ON ch.id = c.chronicle_id
WHERE c.reality_id = @gaia0
ORDER BY c.name;

-- 8. Gaia0: eventos actualmente enlazados.
SELECT
    e.id,
    e.pretty_id,
    e.event_date,
    e.date_precision,
    e.date_note,
    e.title,
    COALESCE(t.name, 'Evento') AS tipo,
    GROUP_CONCAT(DISTINCT ch.name ORDER BY ch.name SEPARATOR ' | ') AS cronicas
FROM bridge_timeline_events_realities br
JOIN fact_timeline_events e ON e.id = br.event_id
LEFT JOIN dim_timeline_events_types t ON t.id = e.event_type_id
LEFT JOIN bridge_timeline_events_chronicles bc ON bc.event_id = e.id
LEFT JOIN dim_chronicles ch ON ch.id = bc.chronicle_id
WHERE br.reality_id = @gaia0
GROUP BY e.id, e.pretty_id, e.event_date, e.date_precision, e.date_note, e.title, t.name
ORDER BY e.event_date, e.id;

-- 9. Eventos ya existentes en Aullidos/Partida Original entre 1998 y 2002.
-- Sirve para no insertar duplicados semánticos cuando migremos Gaia0.
SELECT
    e.id,
    e.pretty_id,
    e.event_date,
    e.date_precision,
    e.date_note,
    e.title,
    COALESCE(t.name, 'Evento') AS tipo,
    GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ' | ') AS realidades
FROM bridge_timeline_events_chronicles bc
JOIN fact_timeline_events e ON e.id = bc.event_id
LEFT JOIN dim_timeline_events_types t ON t.id = e.event_type_id
LEFT JOIN bridge_timeline_events_realities br ON br.event_id = e.id
LEFT JOIN dim_realities r ON r.id = br.reality_id
WHERE bc.chronicle_id = @aullidos
  AND e.event_date >= '1998-01-01'
  AND e.event_date < '2003-01-01'
GROUP BY e.id, e.pretty_id, e.event_date, e.date_precision, e.date_note, e.title, t.name
ORDER BY e.event_date, e.id;

-- 10. ¿Existe ya el Tapete en inventario?
SELECT
    id,
    pretty_id,
    name,
    description
FROM fact_items
WHERE LOWER(name) LIKE '%tapete%'
   OR LOWER(COALESCE(pretty_id, '')) LIKE '%tapete%'
ORDER BY name, id;

-- 11. ¿Cómo está representado Zarpas de Teluria en la BDD?
SELECT
    g.id,
    g.pretty_id,
    g.name,
    ch.name AS cronica,
    g.is_active,
    g.description
FROM dim_groups g
LEFT JOIN dim_chronicles ch ON ch.id = g.chronicle_id
WHERE LOWER(g.name) LIKE '%zarpas%teluria%'
   OR LOWER(COALESCE(g.pretty_id, '')) LIKE '%zarpas%teluria%'
ORDER BY g.id;

-- 12. pretty_id duplicados en hubs narrativos.
SELECT 'fact_characters' AS tabla, pretty_id, COUNT(*) AS repeticiones
FROM fact_characters
WHERE TRIM(COALESCE(pretty_id, '')) <> ''
GROUP BY pretty_id HAVING COUNT(*) > 1
UNION ALL
SELECT 'dim_chapters', pretty_id, COUNT(*)
FROM dim_chapters
WHERE TRIM(COALESCE(pretty_id, '')) <> ''
GROUP BY pretty_id HAVING COUNT(*) > 1
UNION ALL
SELECT 'fact_timeline_events', pretty_id, COUNT(*)
FROM fact_timeline_events
WHERE TRIM(COALESCE(pretty_id, '')) <> ''
GROUP BY pretty_id HAVING COUNT(*) > 1
ORDER BY tabla, pretty_id;

-- 13. Puentes huérfanos de timeline.
SELECT 'realidad_sin_evento' AS problema, COUNT(*) AS total
FROM bridge_timeline_events_realities b
LEFT JOIN fact_timeline_events e ON e.id = b.event_id
WHERE e.id IS NULL
UNION ALL
SELECT 'realidad_inexistente', COUNT(*)
FROM bridge_timeline_events_realities b
LEFT JOIN dim_realities r ON r.id = b.reality_id
WHERE r.id IS NULL
UNION ALL
SELECT 'capitulo_sin_evento', COUNT(*)
FROM bridge_timeline_events_chapters b
LEFT JOIN fact_timeline_events e ON e.id = b.event_id
WHERE e.id IS NULL
UNION ALL
SELECT 'capitulo_inexistente', COUNT(*)
FROM bridge_timeline_events_chapters b
LEFT JOIN dim_chapters c ON c.id = b.chapter_id
WHERE c.id IS NULL;
