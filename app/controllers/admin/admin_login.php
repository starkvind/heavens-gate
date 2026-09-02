<?php
// admin_login.php

include_once(__DIR__ . '/../../helpers/admin_auth.php');
hg_admin_session_start();
include 'admin_get_pwd.php';

$returnTo = hg_admin_login_return_path(
    isset($_POST['return_to']) ? (string)$_POST['return_to'] : null
);

// Si ya esta logueado, redirigir al panel
if (hg_admin_is_authenticated()) {
    hg_admin_redirect($returnTo);
}

$error = '';

if (!empty($adminPasswordLoadError)) {
    $error = 'Acceso administrativo no disponible.';
}

if ($error === '' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_pass'])) {
    $submittedPassword = (string)$_POST['admin_pass'];
    $storedPassword = is_string($adminPassword) ? $adminPassword : '';
    $loginOk = false;

    if ($storedPassword !== '') {
        $loginOk = password_verify($submittedPassword, $storedPassword);
        if ($loginOk && password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
            $rehash = password_hash($submittedPassword, PASSWORD_DEFAULT);
            if (is_string($rehash) && $rehash !== '') {
                hg_admin_store_password_value($link, $rehash);
            }
        }
    }

    if ($loginOk) {
        hg_admin_mark_authenticated();
        hg_admin_redirect($returnTo);
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
			<input type="hidden" name="return_to" value="<?php echo htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8'); ?>">
    		<label>Introduce la contrase&ntilde;a:</label><br><br>
    		<input type="password" name="admin_pass" autocomplete="current-password" required><br><br>
    		<button type="submit">Entrar</button>
    	</form>
    </div>
</div>

