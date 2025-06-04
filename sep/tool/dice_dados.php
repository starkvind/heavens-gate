<center>

<h3> Tira-dados </h3>

<form action="sep/tool/dice_dados_result.php" method="post"/> 

<div class="datox">
    <div class="klax1"> Nombre: </div>
    <div class="klax2">
        <input type="text" name="nombre" size="20" maxlength="20"/>
    </div>
    <div class="klax1"> Dados: </div>
    <div class="klax2">
        <select name="dados">

<option value="1">1</option>
<option value="2">2</option>
<option value="3">3</option>
<option value="4">4</option>
<option value="5">5</option>
<option value="6">6</option>
<option value="7">7</option>
<option value="8">8</option>
<option value="9">9</option>
<option value="10">10</option>
<option value="11">11</option>
<option value="12">12</option>
<option value="13">13</option>
<option value="14">14</option>
<option value="15">15</option>

</select>
    </div>
    <div class="klax1"> Dificultad: </div>
    <div class="klax2">
        <select name="dificultad">

<option value="2">2</option>
<option value="3">3</option>
<option value="4">4</option>
<option value="5">5</option>
<option value="6">6</option>
<option value="7">7</option>
<option value="8">8</option>
<option value="9">9</option>
<option value="10">10</option>

</select>
    </div>
</div>

<br/>

<center> <input class="boton1" type="submit" value="¡Adelante!"/> </center>

<br/>

<hr width="80%"/>

<h3> &Uacute;ltimas tiradas </h3>

<table class="tablax">

<?php

$pageSect = "Tira-dados"; // PARA CAMBIAR EL TITULO A LA PAGINA

/* include("heroes.php"); */

mysql_select_db($bdd, $link);
$consulta ="SELECT id, fecha, frase1, frase2, frase3, frase4 FROM tiradax ORDER BY id DESC LIMIT 0 , 5";

$IdConsulta = mysql_query($consulta, $link);
$NFilas = mysql_num_rows($IdConsulta);
for($i=0;$i<$NFilas;$i++) {
$ResultQuery = mysql_fetch_array($IdConsulta);

print("<tr><td width='25%' class='klax1'>Tirada Nº</td><td width='25%'  class='klax2'>".$ResultQuery["id"]."</td><td width='25%' class='klax1'>Fecha</td><td width='25%' class='klax2'>".$ResultQuery["fecha"]."</td></tr>");

$frase2 = $ResultQuery["frase2"];

$tags_libres = "<b><u><br/>";

$frase1 = $ResultQuery["frase1"];
$frase1 = strip_tags($frase1,$tags_libres); 
$frase3 = $ResultQuery["frase3"];
$frase3 = strip_tags($frase3,$tags_libres); 
$frase4 = $ResultQuery["frase4"];
$frase4 = strip_tags($frase4,$tags_libres); 

print("
	<tr>
		<td colspan='4' class='klax2'>
			$frase1 
			$frase2
			$frase3<br/>
			$frase4
		</td>
	</tr>");


/* Foreach ($frase2 as $clave=>$valor)
{
   echo " $valor";
} */ 

print("<tr><td colspan='4'>&nbsp;</td></tr>");

}

?>


</table>

</center>

