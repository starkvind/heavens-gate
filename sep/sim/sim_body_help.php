<br/>

<fieldset style="border: 1px solid #0000CC;">

<legend style="border: 1px solid #0000CC; padding: 3px; background-color:#000066">Informaci&oacute;n</legend>

<center>
	<a href="javascript:MostrarOcultar('texto1');" id="enlace1">Mostrar informaci&oacute;n</a>
</center>

<div class="ocultable" id="texto1">

<b>Arma y Protector</b>: <br/> Especifica si el combatiente va a utilizar o no un arma o un protector. Las bonificaciones que ofrecen se pueden comprobar en la secci&oacute;n <b>"Inventario"</b>.<br/>
Existen varios tipos de arma y cada una utiliza una habilidad diferente. Si no se lleva ning&uacute;n arma, la habilidad de <b>Pelea</b> es la que se usar&aacute;.<br/><br/>

<b>Leyenda</b>: 

<ul>
	<li>Pelea - (P)</li>
	<li>Cuerpo a Cuerpo - (C)</li>
	<li>Tiro con Arco - (T)</li>
	<li>Arrojar ó Atletismo - (A)</li>
	<li>Armas de Fuego - (F)</li>
	<li>Informática - (I)</li>
</ul>


<b>Forma</b>: <br/>Define la forma en la que el luchador combatir&aacute;. Cada una otorga diferentes atributos f&iacute;sicos. Las transformaciones disponibles variar&aacute;n de una especie cambiante a otra.<br/><br/>

<b>Turnos</b>:<br/> Establece el n&uacute;mero de turnos que se podr&aacute;n jugar para resolver el combate.<br/><br/>

<b>Vitalidad</b>:<br/> La cantidad de da&ntilde;o que podr&aacute;n recibir <b><u>cada uno de los dos</u></b> participantes en la pelea.<br/><br/>

<b>Tiradas</b>:<br/> Muestra o no las tiradas de ataque realizadas para resolver cada turno.<br/><br/>

<b>Ventaja</b>:<br/> Ofrece una ventaja en forma de m&aacute;s vitalidad al participante seleccionado.<br/>

<br/>

<b>Diferencias respecto al Sistema de Combate Original</b>:

<br/>

<ul>

<li>Todas las tiradas tienen la dificultad est&aacute;ndar de <b>6</b>.</li>
<li>El da&ntilde;o por <u>plata</u> o por <u>oro</u> no afecta en la forma de <b>Hom&iacute;nido</b>.</li>
<li>En forma <b>Hom&iacute;nido</b> es posible resistir el da&ntilde;o <i>Contundente</i> y <i>Letal</i>.</li>
<li>Equiparse un <b>Protector</b> garantiza el absorber da&ntilde;o <i>Agravado</i> o por <u>plata</u> u <u>oro</u>.</li>
<li>El uso de <b>Dones</b> que favorecen el combate a&uacute;n no est&aacute; implementado.</li>
<li>La utilizaci&oacute;n tanto de <b>Rabia</b> como de <b>Fuerza de Voluntad</b> no se ha implementado a&uacute;n.</li>

</ul>

<b>Esquema de Combate</b>:

<br/>

<ol>

<li>Se calcula iniciativa. La f&oacute;rmula es <b>Destreza</b> + <b>Astucia</b> + N&uacute;mero al azar entre 1 y 10.</li>
<li>El combatiente con <b><u>m&aacute;s</u></b> iniciativa ataca. <br/>El combatiente con <b><u>menos</u></b> iniciativa intentar&aacute; esquivar el ataque.</li>
<li>Se resolver&aacute;n las tiradas de <b>ataque</b> y <b>esquiva</b>. Si la tirada de ataque es superior a la de esquiva, se proceder&aacute; a calcular la tirada de da&ntilde;o. Sino, el ataque se considerar&aacute; fallido y comenzar&aacute; el turno de ataque del combatiente que ha esquivado.</li>
<li>El da&ntilde;o se calcula con la f&oacute;rmula: <br/><br/>
"<b>Bonus del Arma + &Eacute;xitos en el ataque</b>" (<u>Arma de Fuego/ a Distancia</u>)<br/>
"<b>Fuerza + Bonus del Arma + &Eacute;xitos en el ataque</b>" (<u>Cuerpo a Cuerpo</u> o manos desnudas)<br/><br/></li>
<li>Al resultado de la tirada de da&ntilde;o se le resta el resultado de tirar "<b>Resistencia + Bonus del Protector</b>". Si el rival no puede resistir el tipo de da&ntilde;o, solo se tirar&aacute; el Bonus del Protector.</li>
<li>Se provoca el da&ntilde;o al combatiente defensor. Si su vida est&aacute; igual o por debajo de 0, morir&aacute; y se habr&aacute; terminado el combate; de lo contrario, se repetir&aacute; el proceso, intercambiando atacante y defensor.</li>
<li>En caso de empate de iniciativa, los personajes atacar&aacute;n y esquivar&aacute;n a la vez.</li>

</ol>

</div>

</fieldset>