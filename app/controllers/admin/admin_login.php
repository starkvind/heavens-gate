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

    <style>
        .admin-login form { display: inline-block; padding: 20px; background: #336699; border: 1px solid #ccc; border-radius: 6px; }
        .admin-login input[type="password"] {
            padding: 6px;
            font-size: 16px;
            width: 200px;
        }
        .admin-login button {
            padding: 6px 12px;
            font-size: 16px;
            margin-top: 10px;
            cursor: pointer;
        }
        .admin-login .error { color: red; margin-bottom: 10px; }
    </style>

<div class="admin-login">
    <h2>&#128274; Acceso restringido</h2>

    <?php if (!empty($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <div style="clear:both;text-align: center;">
    	<form method="post" style="text-align:center;">
    		<label>Introduce la contrase&ntilde;a:</label><br><br>
    		<input type="password" name="admin_pass" required><br><br>
    		<button type="submit">Entrar</button>
    	</form>
    </div>
</div>
