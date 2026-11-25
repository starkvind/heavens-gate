<?php
/**
 * Read-only dice records referenced by an approved post in an editorially
 * enabled forum topic. A bare dice ID never grants Content API visibility.
 */
require_once __DIR__ . '/content_forum.php';
require_once __DIR__ . '/../dice/queries.php';

function hg_content_api_roll_reference_pattern(int $rollId): string
{
    // POSIX bracket expressions avoid driver-dependent escaping of literal [].
    return '[[]hg_tirada[]][[:space:]]*' . $rollId . '[[:space:]]*[[]/hg_tirada[]]';
}

function hg_content_api_roll_message_has_marker(string $body, int $rollId): bool
{
    return $rollId > 0 && preg_match('~\\[hg_tirada\\]\\s*' . $rollId . '\\s*\\[/hg_tirada\\]~i', $body) === 1;
}

function hg_content_api_roll_reference_sql(string $table): string
{
    if (!in_array($table, ['smf_messages', 'smf.smf_messages'], true)) {
        throw new InvalidArgumentException('Unexpected SMF table');
    }
    return 'SELECT sm.id_msg AS message_id, sm.id_topic AS topic_id, sm.poster_name,'
        . ' sm.id_member, sm.poster_time, sm.body'
        . ' FROM ' . $table . ' sm'
        . ' INNER JOIN fact_tools_topic_viewer ftv ON ftv.topic_id = sm.id_topic AND ftv.is_active = 1'
        . ' WHERE sm.approved = 1 AND sm.body REGEXP ?'
        . ' ORDER BY sm.poster_time ASC, sm.id_msg ASC LIMIT 10';
}

function hg_content_api_roll_visible_references(mysqli $link, int $rollId): ?array
{
    $table = hg_content_api_forum_table($link);
    if ($table === null) return null;
    $stmt = $link->prepare(hg_content_api_roll_reference_sql($table));
    if (!$stmt) return null;
    $pattern = hg_content_api_roll_reference_pattern($rollId);
    $stmt->bind_param('s', $pattern);
    try {
        if (!$stmt->execute()) return null;
        $result = $stmt->get_result();
        if (!$result) return null;
        $references = [];
        while ($row = $result->fetch_assoc()) {
            // Confirm literal BBCode membership; a fuzzy SQL match is not enough.
            if (!hg_content_api_roll_message_has_marker((string)$row['body'], $rollId)) continue;
            $references[] = [
                'message_id' => (int)$row['message_id'],
                'topic_id' => (int)$row['topic_id'],
                'poster_name' => (string)$row['poster_name'],
                'member_id' => (int)$row['id_member'],
                'posted_at' => (int)$row['poster_time'],
            ];
        }
        $result->free();
        return $references;
    } finally {
        $stmt->close();
    }
}

function hg_content_api_roll_project(array $row, array $references): array
{
    $raw = $row['roll_results'] ?? null;
    $dice = null;
    if (is_string($raw) && trim($raw) !== '') {
        $parts = explode(',', $raw);
        $parsed = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (!ctype_digit($part) || (int)$part < 1 || (int)$part > 10) {
                $parsed = [];
                break;
            }
            $parsed[] = (int)$part;
        }
        if ($parsed !== []) $dice = $parsed;
    }
    $successes = isset($row['successes']) ? (int)$row['successes'] : null;
    $botch = isset($row['botch']) ? ((int)$row['botch'] === 1) : null;
    $willpower = isset($row['willpower_spent']) ? ((int)$row['willpower_spent'] === 1) : null;
    $outcome = ($botch === null || $successes === null)
        ? null : ($botch ? 'pifia' : ($successes > 0 ? 'exito' : 'fallo'));
    $first = $references[0];
    return [
        'id' => (int)$row['id'],
        'message_id' => $first['message_id'],
        'topic_id' => $first['topic_id'],
        'forum_author' => $first['poster_name'],
        'forum_member_id' => $first['member_id'],
        'posted_at' => $first['posted_at'],
        'forum_references' => $references,
        'name' => isset($row['name']) ? (string)$row['name'] : null,
        'roll_name' => isset($row['roll_name']) ? (string)$row['roll_name'] : null,
        'character_id' => null,
        'system' => null,
        'rolled_at' => isset($row['rolled_at']) ? (string)$row['rolled_at'] : null,
        'dice_pool' => isset($row['dice_pool']) ? (int)$row['dice_pool'] : null,
        'difficulty' => isset($row['difficulty']) ? (int)$row['difficulty'] : null,
        'roll_results' => $dice,
        'successes' => $successes,
        'botch' => $botch,
        'outcome' => $outcome,
        'ones_rolled' => $dice === null ? null : count(array_filter($dice, static fn(int $die): bool => $die === 1)),
        'ones_rule' => 'single_penalty_if_any_one',
        'willpower_spent' => $willpower,
        'other_modifiers' => null,
        'special_circumstances' => null,
    ];
}

function hg_content_api_roll_handle(mysqli $link, int $rollId, callable $send): void
{
    if ($rollId <= 0) {
        $send(['ok' => false, 'error' => 'invalid_roll_id'], 400);
        return;
    }
    try {
        // Authorization comes from the same active-topic and approved-post
        // gates as resource=forum. Do not read the roll until authorized.
        $references = hg_content_api_roll_visible_references($link, $rollId);
        if ($references === null) {
            $send(['ok' => false, 'error' => 'forum_unavailable'], 503);
            return;
        }
        if (!$references) {
            $send(['ok' => false, 'error' => 'not_found'], 404);
            return;
        }
        $row = hg_dice_fetch_roll($link, $rollId);
        if (!$row) {
            $send(['ok' => false, 'error' => 'not_found'], 404);
            return;
        }
        // Never serialize the original row: it includes the roller's IP.
        $send(['ok' => true, 'resource' => 'tiradas', 'item' => hg_content_api_roll_project($row, $references)]);
    } catch (mysqli_sql_exception $e) {
        error_log('content_api.rolls: ' . $e->getMessage());
        $send(['ok' => false, 'error' => 'query_unavailable'], 503);
    }
}
