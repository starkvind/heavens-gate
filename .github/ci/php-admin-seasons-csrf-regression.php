<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/helpers/admin_ajax.php';

function season_csrf_check(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$key = 'csrf_admin_seasons';
$_SESSION = [];
season_csrf_check(!hg_admin_csrf_valid('', $key), 'Missing token accepted');
season_csrf_check(!hg_admin_csrf_valid('unissued', $key), 'Unissued token accepted');
$_SESSION[$key] = 'valid-test-token';
season_csrf_check(!hg_admin_csrf_valid('', $key), 'Empty token accepted');
season_csrf_check(!hg_admin_csrf_valid('wrong', $key), 'Wrong token accepted');
season_csrf_check(hg_admin_csrf_valid('valid-test-token', $key), 'Valid token rejected');
season_csrf_check(!hg_admin_csrf_valid('valid-test-token', 'other_csrf'), 'Wrong scope accepted');

// Static integration contract: the shared guard must precede all CRUD calls.
$source = file_get_contents($root . '/app/controllers/admin/admin_seasons.php');
season_csrf_check(is_string($source), 'Season controller not found');
$guard = strpos($source, 'hg_admin_csrf_valid($submittedSeasonCsrf, $seasonCsrfKey)');
$branch = strpos($source, '} elseif ($isSeasonCrudPost) {');
season_csrf_check($guard !== false && $branch !== false && $guard < $branch, 'Missing CRUD guard');
season_csrf_check(str_contains($source, '!is_string($submittedSeasonCsrf)'), 'Malformed token not rejected');
season_csrf_check(str_contains($source, 'hg_admin_ensure_csrf_token($seasonCsrfKey)'), 'Missing session token');
foreach (['hg_seasons_admin_delete(', 'hg_seasons_admin_create(', 'hg_seasons_admin_update('] as $call) {
    $position = strpos($source, $call);
    season_csrf_check($position !== false && $position > $branch, 'Unguarded CRUD call: ' . $call);
}
foreach (['seasonForm', 'seasonDeleteForm'] as $form) {
    $pattern = '~<form\\b[^>]*id="' . $form . '"[^>]*>.*?</form>~s';
    season_csrf_check(preg_match($pattern, $source, $match) === 1, 'Missing form: ' . $form);
    season_csrf_check(str_contains($match[0], 'name="csrf" value="<?= h($seasonCsrf) ?>"'), 'Missing token in ' . $form);
}
echo "Season admin CSRF regression: OK\n";
