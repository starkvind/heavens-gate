<?php
require_once __DIR__ . '/../../app/domains/api/content_forum.php';
$valid = ['smf_messages','smf.smf_messages'];
foreach ($valid as $table) {
    $sql = hg_content_api_forum_messages_sql($table, false);
    if (!str_contains($sql,'FROM '.$table.' sm')
        || !str_contains($sql,'ftv.is_active = 1')
        || !str_contains($sql,'sm.approved = 1')
        || !str_contains($sql,'sm.id_topic = ?')
        || !str_contains($sql,'LIMIT ? OFFSET ?')) {
        fwrite(STDERR, 'FAIL: forum source/approval gate ' . $table . PHP_EOL);
        exit(1);
    }
    $searchSql = hg_content_api_forum_messages_sql($table, true);
    if (!str_contains($searchSql,'sm.body LIKE ? OR sm.subject LIKE ?')) {
        fwrite(STDERR,'FAIL: forum topic text search'.PHP_EOL);
        exit(1);
    }
}
try {
    hg_content_api_forum_messages_sql('smf_messages UNION SELECT * FROM users', false);
    fwrite(STDERR, 'FAIL: unexpected SQL identifier accepted'.PHP_EOL);
    exit(1);
} catch (InvalidArgumentException $e) {}
echo "PASS: forum SQL table allowlist, approved-post gate and parameterized pagination/search\n";
