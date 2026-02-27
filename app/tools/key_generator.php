<?php
require_once __DIR__ . '/../helpers/security.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate']) && !empty($_POST['plaintext'])) {
        $plaintext = trim($_POST['plaintext']);
        $encrypted = encrypt_string($plaintext);
        $message = "Clave encriptada: <code>" . htmlspecialchars($encrypted) . "</code>";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Generador de Claves Encriptadas</title>
    <link rel="stylesheet" href="/assets/css/hg-tools.css">
</head>
<body class="hg-keygen-page">
<div class="hg-keygen-container">
    <h2>🔐 Generador de claves encriptadas</h2>
    <form method="post">
        <label for="plaintext">Texto plano a cifrar:</label>
        <input type="text" id="plaintext" name="plaintext" required>
        <br><br>
        <input type="submit" name="generate" value="Generar Clave Encriptada">
    </form>
    <br>
    <?php if (!empty($message)) echo $message; ?>
</div>
</body>
</html>
