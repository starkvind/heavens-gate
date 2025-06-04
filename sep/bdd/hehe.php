<?php

session_start();

$usuario = $_SESSION['usuario'];
$password = $_SESSION['password'];

if ($usuario =='') {

$valido="no";

} else {

include("libreria.php");

mysql_select_db($bdd, $link);
$consulta ="SELECT usuario, password FROM users WHERE usuario LIKE '$usuario';";
$query = mysql_query ($consulta, $link);

$da = mysql_fetch_array($query);

$usuario1 = $da['usuario'];
$pass1 = $da['password'];

if ($usuario==$usuario1 AND $password==$pass1)
{
$valido="si";
}
else
{
$valido="no";
}

}

?>