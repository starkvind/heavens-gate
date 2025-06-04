<?php

$usuario = $_POST['usuario'];
$password = $_POST['password'];

if ($usuario =='') {

header("Location: ../check.php");

} else {

session_start(); 

if ( isset ( $_SESSION['usuario'] ) ) {
 // Si existe

header("Location: gestion.php");

} else {
 // Si no existe

$_SESSION['usuario'] = $usuario;
$_SESSION['password'] = $password;

header("Location: gestion.php");

}

}

?> 








