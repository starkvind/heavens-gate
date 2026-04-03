<?php
// admin_login.php

include_once(__DIR__ . '/../../helpers/admin_auth.php');
hg_admin_session_start();
include 'admin_get_pwd.php';

// Si ya esta logueado, redirigir al panel
if (hg_admin_is_authenticated()) {
    hg_admin_redirect('/talim');
}

$error = '';

if (!empty($adminPasswordLoadError)) {
    $error = (string)$adminPasswordLoadError;
}

if ($error === '' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_pass'])) {
    $submittedPassword = (string)$_POST['admin_pass'];
    $storedPassword = is_string($adminPassword) ? $adminPassword : '';
    $storedPasswordMode = (string)($adminPasswordMode ?? 'legacy');
    $loginOk = false;

    if ($storedPassword !== '') {
        if ($storedPasswordMode === 'hash') {
            $loginOk = password_verify($submittedPassword, $storedPassword);
            if ($loginOk && password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
                $rehash = password_hash($submittedPassword, PASSWORD_DEFAULT);
                if (is_string($rehash) && $rehash !== '') {
                    hg_admin_store_password_value($link, $rehash);
                }
            }
        } else {
            $loginOk = hash_equals($storedPassword, $submittedPassword);
            if ($loginOk) {
                $newHash = password_hash($submittedPassword, PASSWORD_DEFAULT);
                if (is_string($newHash) && $newHash !== '') {
                    hg_admin_store_password_value($link, $newHash);
                }
            }
        }
    }

    if ($loginOk) {
        hg_admin_mark_authenticated();
        hg_admin_redirect('/talim');
    } else {
        usleep(250000);
        $error = "Contrase&ntilde;a incorrecta.";
    }
}
?>
<link rel="stylesheet" href="/assets/css/hg-admin.css">

<div class="admin-login">
    <h2>&#128274; Acceso restringido</h2>

    <?php if (!empty($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <div class="adm-login-wrap">
    	<form method="post" class="adm-text-center">
    		<label>Introduce la contrase&ntilde;a:</label><br><br>
    		<input type="password" name="admin_pass" autocomplete="current-password" required><br><br>
    		<button type="submit">Entrar</button>
    	</form>
    </div>
</div>

