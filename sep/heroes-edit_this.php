<?php
//$link = mysql_connect("localhost", "root", "1nfl4m3s");
//$bdd = "heavensgate";
//$link = mysql_connect("mysql.hostinger.es", "u807926597_mav", "b4nk41");
//$link = mysql_connect("localhost", "id7111966_starko", "b4nk41");
//if(!$link) { die(mysql_error()); }
//$bdd = "id7111966_hg";
//$bdd = "u807926597_hg";

$host = "HOST";
$user = "USER";
$pass = "PWD";
$bdd = "BDD";

$link = mysqli_connect($host, $user, $pass, $bdd);
if (mysqli_connect_errno())
  {
  echo "Failed to connect to MariaDB: " . mysqli_connect_error();
  }
?>