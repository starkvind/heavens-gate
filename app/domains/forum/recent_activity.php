<?php
/**
 * Lightweight, public-only forum activity for the home page.
 *
 * The topic viewer's active topic registry is the editorial allowlist.
 * Unlike the full reader, this query fetches only approved posts.
 */

if (!function_exists('hg_forum_recent_excerpt')) {
    function hg_forum_recent_excerpt(string $body, int $length = 190): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);

        // Quotes repeat earlier posts; spoilers must not appear on the public home.
        // Track nested quote/spoiler blocks rather than stopping at the first closing tag.
        $pieces = preg_split('/(\[\/?(?:quote|spoiler)(?:[^\]]*)\])/i', $body, -1, PREG_SPLIT_DELIM_CAPTURE);
        $hiddenDepth = 0;
        $visible = '';
        foreach ($pieces ?: [] as $piece) {
            if (preg_match('/^\[(\/)?(?:quote|spoiler)(?:[^\]]*)\]$/i', $piece, $match)) {
                if (($match[1] ?? '') === '/') {
                    $hiddenDepth = max(0, $hiddenDepth - 1);
                } else {
                    $hiddenDepth++;
                }
                continue;
            }
            if ($hiddenDepth === 0) {
                $visible .= $piece . ' ';
            }
        }

        // Keep text inside hg_avatar dialogue, but discard embeds and formatting.
        $visible = preg_replace('/\[(img|youtube|code|hg_tirada)[^\]]*\].*?\[\/\1\]/is', ' ', $visible) ?? '';
        $visible = preg_replace('/\[\/?[a-z][a-z0-9_]*(?:[^\]]*)\]/i', ' ', $visible) ?? '';
        $visible = strip_tags(html_entity_decode($visible, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $visible = trim(preg_replace('/\s+/u', ' ', $visible) ?? '');

        if ($visible === '') {
            return 'Lee la intervención completa en el lector del foro.';
        }

        $length = max(80, min(400, $length));
        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($visible, 0, $length, '…', 'UTF-8');
        }
        return strlen($visible) > $length
            ? substr($visible, 0, $length - 3) . '...'
            : $visible;
    }
}

if (!function_exists('hg_forum_recent_activity')) {
    function hg_forum_recent_activity(mysqli $link, int $limit = 4): array
    {
        require_once __DIR__ . '/../../helpers/schema_introspection.php';

        if (!hg_table_exists($link, 'fact_tools_topic_viewer')) {
            return [];
        }

        // Match the reader: SMF may live in the current schema or in smf.
        $messagesTable = hg_table_exists($link, 'smf_messages')
            ? 'smf_messages'
            : 'smf.smf_messages';
        $limit = max(1, min(4, $limit));

        $sql = "SELECT
                    sm.id_msg AS message_id,
                    sm.id_topic AS topic_id,
                    sm.poster_name,
                    sm.poster_time,
                    sm.body,
                    COALESCE(NULLIF(ftv.topic_name, ''), NULLIF(sm.subject, ''), 'Partida por foro') AS topic_title
                FROM {$messagesTable} sm
                INNER JOIN fact_tools_topic_viewer ftv
                    ON ftv.topic_id = sm.id_topic AND ftv.is_active = 1
                WHERE sm.approved = 1
                ORDER BY sm.poster_time DESC, sm.id_msg DESC
                LIMIT {$limit}";

        try {
            $stmt = $link->prepare($sql);
            if (!$stmt) {
                return [];
            }
            if (!$stmt->execute()) {
                $stmt->close();
                return [];
            }
            $result = $stmt->get_result();
            $items = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $topicId = (int)($row['topic_id'] ?? 0);
                    $messageId = (int)($row['message_id'] ?? 0);
                    if ($topicId <= 0 || $messageId <= 0) {
                        continue;
                    }
                    $items[] = [
                        'author' => trim((string)($row['poster_name'] ?? '')) ?: 'Desconocido',
                        'topic_title' => (string)($row['topic_title'] ?? 'Partida por foro'),
                        'poster_time' => (int)($row['poster_time'] ?? 0),
                        'excerpt' => hg_forum_recent_excerpt((string)($row['body'] ?? '')),
                        'href' => '/tools/forum-topic-viewer?id_topic=' . $topicId . '#msg-' . $messageId,
                    ];
                }
                $result->free();
            }
            $stmt->close();
            return $items;
        } catch (mysqli_sql_exception $exception) {
            // Forum access is optional: never break the home if SMF is unavailable.
            error_log('home.forum_activity: ' . $exception->getMessage());
            return [];
        }
    }
}
