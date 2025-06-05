<?php 

session_start();

$usuario = $_SESSION['usuario'];

// Borramos la variable
unset ( $_SESSION['usuario'] ); {
// Borramos toda la sesion
session_destroy();

}

?>

<html>

<head>

<title>Heaven's Gate</title>
 
<link href="style.css" rel="stylesheet" type="text/css">

</head>

<body>

<div class="cuerpo">

<center>

<br>

Adi&oacute;s <?php echo $usuario; ?>

<br><br>

<a href="../../index.php"> Regresar </a>

</center>

</div>

</body>

</html>