<?php

// Capturamos las variables de manera segura usando filter_input para evitar inyecciones
$nick = filter_input(INPUT_POST, 'dumbass', FILTER_SANITIZE_STRING);
$fecha = date("Y-m-d");
$hora = date("H:i");
$mensaje = filter_input(INPUT_POST, 'shitty', FILTER_SANITIZE_STRING);
$id = filter_input(INPUT_POST, 'fucky', FILTER_SANITIZE_STRING);

$a = filter_input(INPUT_POST, 'checky1', FILTER_SANITIZE_STRING);
$b = filter_input(INPUT_POST, 'checky2', FILTER_SANITIZE_STRING);

// Comprobamos si el nick está vacío o si los valores no coinciden
if (empty($nick) || $a !== $b) {
    header("Location: index.php?p=muestrabio&b=$id#msg");
    exit();
} else {
    include("sep/heroes.php");

    // Conexión a la base de datos usando MySQLi
    $link = mysqli_connect("localhost", "usuario", "contraseña", "bdd");

    if (!$link) {
        die("Error de conexión: " . mysqli_connect_error());
    }

    // Chequeamos la IP para evitar abusos
    $stmt = mysqli_prepare($link, "SELECT ip, hora FROM koment WHERE idpj LIKE ? ORDER BY id DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $ip2, $hora2);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    $ip = $_SERVER['REMOTE_ADDR'];
    $hora1 = date("H:i");

    if ($ip != $ip2 && $hora1 != $hora2) {
        $stmt = mysqli_prepare($link, "INSERT INTO koment (idpj, nick, hora, fecha, mensaje, ip) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssssss', $id, $nick, $hora, $fecha, $mensaje, $ip);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        header("Location: index.php?p=muestrabio&b=$id#msg");
    } else {
        header("Location: index.php?p=muestrabio&b=$id#msg");
    }

    mysqli_close($link);
}
?>
