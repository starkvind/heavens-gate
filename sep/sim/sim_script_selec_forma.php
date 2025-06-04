<script type="text/javascript">
function selectAsociado1(){
var pj1
pj1 = document.simulador.pj1[document.simulador.pj1.selectedIndex].value
if (pj1 != 0) {
mis_subsecc=eval("secc_sub" + pj1)
num_seccisub = mis_subsecc.length
document.simulador.forma1.length = num_seccisub
for(i=0;i<num_seccisub;i++){
document.simulador.forma1.options[i].value=mis_subsecc[i]
document.simulador.forma1.options[i].text=mis_subsecc[i]
}
}else{
document.simulador.forma1.length = 1
document.simulador.forma1.options[0].value = "Homínido"
document.simulador.forma1.options[0].text = "Homínido"
}
document.simulador.forma1.options[0].selected = true
}


function selectAsociado2(){
var pj2
pj2 = document.simulador.pj2[document.simulador.pj2.selectedIndex].value
if (pj2 != 0) {
mis_subsecc=eval("secc_sub" + pj2)
num_seccisub = mis_subsecc.length
document.simulador.forma2.length = num_seccisub
for(i=0;i<num_seccisub;i++){
document.simulador.forma2.options[i].value=mis_subsecc[i]
document.simulador.forma2.options[i].text=mis_subsecc[i]
}
}else{
document.simulador.forma2.length = 1
document.simulador.forma2.options[0].value = "Homínido"
document.simulador.forma2.options[0].text = "Homínido"
}
document.simulador.forma2.options[0].selected = true
} 
</script>

<?php

echo "<script type='text/javascript'>";
$result = mysql_query("SELECT * FROM pjs1 WHERE kes LIKE 'pj'");
while($row = mysql_fetch_array($result)) {
echo "var secc_sub".$row[id]."= new Array('Homínido'";
$result2 = mysql_query("SELECT forma FROM nuevo_formas WHERE raza = '$row[fera]'");
while($row2 = mysql_fetch_array($result2)) {
echo ", '$row2[forma]'";
}
echo ")\n";
}
echo "</script>";

?>