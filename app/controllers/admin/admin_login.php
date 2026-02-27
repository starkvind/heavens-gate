<?php
// admin_login.php

include 'admin_get_pwd.php';

// Si ya esta logueado, redirigir al panel
if (isset($_SESSION['is_admin'])) {
    header("Location: /talim");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_pass'])) {
    if ($_POST['admin_pass'] === $adminPassword) {
        $_SESSION['is_admin'] = true;
        header("Location: /talim");
        exit;
    } else {
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
    		<input type="password" name="admin_pass" required><br><br>
    		<button type="submit">Entrar</button>
    	</form>
    </div>
</div>

