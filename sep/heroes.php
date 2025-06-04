<?php
//$link = mysql_connect("localhost", "root", "1nfl4m3s");
//$bdd = "heavensgate";
//$link = mysql_connect("mysql.hostinger.es", "u807926597_mav", "b4nk41");
//$link = mysql_connect("localhost", "id7111966_starko", "b4nk41");
//if(!$link) { die(mysql_error()); }
//$bdd = "id7111966_hg";
//$bdd = "u807926597_hg";


$link=mysqli_connect("localhost","starko_remote","b4n4n4","u807926597_hg");
if (mysqli_connect_errno())
  {
  echo "Failed to connect to MariaDB: " . mysqli_connect_error();
  }
?>