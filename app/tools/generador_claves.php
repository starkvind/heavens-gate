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
    <style>
        body { font-family: Verdana; background-color: #05014E; color: #fff; padding: 20px; }
        input[type=text], textarea { width: 100%; padding: 8px; background-color: #000066; color: #fff; border: 1px solid #000099; }
        input[type=submit] { padding: 10px; background-color: #000066; color: cyan; border: 1px solid #000099; cursor: pointer; }
        input[type=submit]:hover { background-color: #000088; }
        code { background-color: #000055; padding: 4px; display: block; margin-top: 10px; }
        .container { max-width: 600px; margin: auto; background-color: #000033; padding: 20px; border-radius: 8px; }
    </style>
</head>
<body>
<div class="container">
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
