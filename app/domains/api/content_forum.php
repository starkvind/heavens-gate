<?php
/**
 * Read-only, editorially approved SMF posts for the Content API.
 *
 * The forum reader supports two trusted table locations. Detect the live one
 * before preparing SQL: under strict MySQLi, preparing a nonexistent table
 * throws rather than returning false, so a prepare-then-fallback never works.
 */
function hg_content_api_forum_table(mysqli $link): ?string
{
    require_once __DIR__ . '/../../helpers/schema_introspection.php';
    if (hg_table_exists($link, 'smf_messages')) return 'smf_messages';
    $stmt = $link->prepare(
        "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'smf' AND TABLE_NAME = 'smf_messages' LIMIT 1"
    );
    if (!$stmt) return null;
    $stmt->execute();
    $result = $stmt->get_result();
    $found = $result && $result->num_rows > 0;
    $stmt->close();
    return $found ? 'smf.smf_messages' : null;
}

/** The table identifier is selected internally from two fixed names, never from request input. */
function hg_content_api_forum_messages_sql(string $table, bool $search): string
{
    if (!in_array($table, ['smf_messages', 'smf.smf_messages'], true)) {
        throw new InvalidArgumentException('Unexpected SMF table');
    }
    return 'SELECT sm.id_msg AS id, sm.id_topic AS topic_id, sm.subject, sm.poster_name, sm.poster_time, sm.body, sm.id_member'
        . ' FROM ' . $table . ' sm'
        . ' INNER JOIN fact_tools_topic_viewer ftv ON ftv.topic_id = sm.id_topic AND ftv.is_active = 1'
        . ' WHERE sm.id_topic = ? AND sm.approved = 1'
        . ($search ? ' AND (sm.body LIKE ? OR sm.subject LIKE ?)' : '')
        . ' ORDER BY sm.poster_time ASC, sm.id_msg ASC LIMIT ? OFFSET ?';
}

function hg_content_api_forum_fetch(mysqli $link, int $topicId, int $limit, int $offset, string $q): ?array
{
    $table = hg_content_api_forum_table($link);
    if ($table === null) return null;
    $stmt = $link->prepare(hg_content_api_forum_messages_sql($table, $q !== ''));
    if (!$stmt) return null;
    if ($q !== '') {
        $term = '%' . $q . '%';
        $stmt->bind_param('issii', $topicId, $term, $term, $limit, $offset);
    } else {
        $stmt->bind_param('iii', $topicId, $limit, $offset);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) { $stmt->close(); return null; }
    $items = [];
    while ($row = $result->fetch_assoc()) $items[] = $row;
    $result->free();
    $stmt->close();
    return $items;
}
