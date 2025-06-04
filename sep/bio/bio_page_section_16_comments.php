<?php

/* COMENTARIOS */
$queryComments = "SELECT * FROM koment WHERE idpj = ? ORDER BY id DESC";
$stmt = $link->prepare($queryComments);
$stmt->bind_param('s', $idGetData);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($resultQueryComments = $result->fetch_assoc()) {
        $nickComment = htmlspecialchars($resultQueryComments["nick"]);
        $textComment = htmlspecialchars($resultQueryComments["mensaje"]);
        echo "<fieldset class='bioCommentText'>";
        echo "<legend>$nickComment:</legend>";
        echo $textComment;
        echo "</fieldset>";
    }
} else {
    /*echo "<fieldset class='bioCommentText'>Esta biografía no tiene mensajes. Puedes agregar uno utilizando el formulario más abajo.</fieldset>";*/
    echo "<fieldset class='bioCommentText'>Esta biografía no tiene mensajes.</fieldset>";
}

$stmt->close();

/* Generación de valores aleatorios */
$value1 = rand(1, 9);
$value2 = rand(1, 9);
$value3 = $value1 + $value2;

$numeros = [
    1 => "Uno", 2 => "Dos", 3 => "Tres",
    4 => "Cuatro", 5 => "Cinco", 6 => "Seis",
    7 => "Siete", 8 => "Ocho", 9 => "Nueve"
];

$value1_c = $numeros[$value1];
$value2_c = $numeros[$value2];

/* Formulario para agregar nuevos mensajes */
/*
echo "<form action='mensaje.php' method='post'>"; 
echo "<div class='bioCommentUpperForm'>";
echo "Nick: <input type='text' name='dumbass' size='20' maxlength='20' />";
echo "&nbsp;&nbsp;¿$value1_c + $value2_c?: <input type='text' name='checky1' size='5' maxlength='2' />";
echo "</div>";
echo "<div class='bioCommentBottomForm'>";
echo "	<textarea rows='5' cols='70' name='shitty' wrap='physical' onKeyDown='textCounter(this.form.shitty,this.form.remLen,200);'
			onKeyUp='textCounter(this.form.shitty,this.form.remLen,200);'></textarea><br/><br/>
		Car&aacute;cteres restantes: <input readonly style='border:0px;background: none;' type='text' name='remLen' size='3' maxlength='3' value='200' />
	";
echo "<div style='text-align:right;'>
        <input type='hidden' value='" . htmlspecialchars($idGetData) . "' name='fucky'/>
        <input type='hidden' value='" . htmlspecialchars($value3) . "' name='checky2'/>
        <input type='submit' class='boton1' value='Publicar' />
      </div>";
echo "</div>";
echo "</form>";

*/

?>
