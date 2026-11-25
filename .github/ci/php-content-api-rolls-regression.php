<?php
require_once __DIR__ . '/../../app/domains/api/content_rolls.php';

function roll_check(string $label, bool $ok): void
{
    if (!$ok) {
        fwrite(STDERR, 'FAIL: ' . $label . PHP_EOL);
        exit(1);
    }
    echo 'PASS: ' . $label . PHP_EOL;
}

foreach (['smf_messages', 'smf.smf_messages'] as $table) {
    $sql = hg_content_api_roll_reference_sql($table);
    roll_check('forum publication and approval gate ' . $table,
        str_contains($sql, 'FROM ' . $table . ' sm')
        && str_contains($sql, 'ftv.is_active = 1')
        && str_contains($sql, 'sm.approved = 1')
        && str_contains($sql, 'sm.body REGEXP ?')
        && str_contains($sql, 'LIMIT 10'));
}
try {
    hg_content_api_roll_reference_sql('smf_messages; DROP TABLE users');
    roll_check('table injection rejected', false);
} catch (InvalidArgumentException $e) {
    roll_check('table injection rejected', true);
}

roll_check('roll 46 canonical marker',
    hg_content_api_roll_message_has_marker('Uso Sentir al Wyrm. [hg_tirada]46[/hg_tirada]', 46));
roll_check('whitespace and case accepted',
    hg_content_api_roll_message_has_marker('[HG_TIRADA] 46 [/HG_TIRADA]', 46));
roll_check('different ID cannot authorize',
    !hg_content_api_roll_message_has_marker('[hg_tirada]146[/hg_tirada]', 46));
roll_check('broken marker cannot authorize',
    !hg_content_api_roll_message_has_marker('[hg_tirada]46[/hg_avatar]', 46));
roll_check('database pattern for ID 46',
    hg_content_api_roll_reference_pattern(46) === '[[]hg_tirada[]][[:space:]]*46[[:space:]]*[[]/hg_tirada[]]');

[$manyDice, $manySuccesses, $manyBotch] = hg_dice_roll_d10_pool(4, 6, [10, 9, 8, 1]);
roll_check('multiple successes after a one',
    $manyDice === [10,9,8,1] && $manySuccesses === 2 && !$manyBotch);

$ref = ['message_id'=>1111,'topic_id'=>77,'poster_name'=>'Denebia','member_id'=>7,'posted_at'=>1795604948];
[$dice, $successes, $botch] = hg_dice_roll_d10_pool(4, 6, [8, 7, 1, 1]);
roll_check('existing rules subtract at most one success for any ones', $successes === 1 && !$botch);
$base = ['id'=>46,'name'=>'PJ de ejemplo','roll_name'=>'Sentir al Wyrm',
    'dice_pool'=>4,'difficulty'=>6,'roll_results'=>implode(',', $dice),
    'successes'=>$successes,'botch'=>(int)$botch,'willpower_spent'=>0,
    'rolled_at'=>'2026-11-25 12:00:00','ip'=>'private-ip-must-not-leak'];
$project = hg_content_api_roll_project($base, [$ref]);
roll_check('real reference fields remain bound to approved forum post',
    $project['id'] === 46 && $project['message_id'] === 1111
    && $project['topic_id'] === 77 && $project['forum_author'] === 'Denebia');
roll_check('dice outcomes are persisted values, never recomputed on read',
    $project['roll_results'] === [8,7,1,1] && $project['successes'] === 1
    && $project['ones_rolled'] === 2 && $project['outcome'] === 'exito');
roll_check('IP cannot leak in API projection',
    !array_key_exists('ip', $project) && !str_contains(json_encode($project), 'private-ip-must-not-leak'));
roll_check('unknown association data is null', $project['character_id'] === null
    && $project['special_circumstances'] === null && $project['system'] === null);

[$failedDice, $failedSuccesses, $failedBotch] = hg_dice_roll_d10_pool(3, 6, [2,3,4]);
$failed = hg_content_api_roll_project(array_replace($base, [
    'roll_results'=>implode(',', $failedDice),'successes'=>$failedSuccesses,
    'botch'=>(int)$failedBotch
]), [$ref]);
roll_check('failed roll', $failed['outcome'] === 'fallo' && $failed['successes'] === 0);
[$botchedDice, $botchedSuccesses, $isBotch] = hg_dice_roll_d10_pool(3, 6, [1,2,3]);
$botched = hg_content_api_roll_project(array_replace($base, [
    'roll_results'=>implode(',', $botchedDice),'successes'=>$botchedSuccesses,
    'botch'=>(int)$isBotch
]), [$ref]);
roll_check('botch', $botched['outcome'] === 'pifia' && $botched['botch'] === true);
$willpower = hg_content_api_roll_project(array_replace($base, [
    'successes'=>2,'willpower_spent'=>1
]), [$ref]);
roll_check('willpower flag and stored bonus preserved', $willpower['successes'] === 2
    && $willpower['willpower_spent'] === true);
$missing = hg_content_api_roll_project(array_replace($base, [
    'roll_results'=>null,'successes'=>null,'botch'=>null
]), [$ref]);
roll_check('missing data stays null', $missing['roll_results'] === null
    && $missing['successes'] === null && $missing['outcome'] === null);
