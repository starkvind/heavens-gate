<br/>

<fieldset>

<legend>Informaci&oacute;n</legend>

<center>
	<a href="javascript:MostrarOcultar('texto1');" id="enlace1">Mostrar informaci&oacute;n</a>
</center>

<div class="ocultable" id="texto1">

<b>Selecci&oacute;n de combatientes</b>:<br/>
Debes elegir dos participantes (P1 y P2) para habilitar la configuraci&oacute;n avanzada. El sistema acepta selecci&oacute;n manual o aleatoria por cada hueco.<br/>
Si ya hay dos seleccionados y eliges un tercer personaje, se reemplaza autom&aacute;ticamente primero P1 y en el siguiente clic P2 (alternando).<br/><br/>

<b>Forma</b>:<br/>
Define la forma en la que luchar&aacute; cada combatiente. Las formas disponibles dependen de la especie del personaje.<br/><br/>

<b>Arma y protector</b>:<br/>
Puedes dejar ambos en vac&iacute;o o elegir equipamiento del personaje. En armas se indica entre par&eacute;ntesis la habilidad principal usada por el simulador.<br/><br/>

<b>Leyenda de armas</b>:

<ul>
	<li>Pelea - (P)</li>
	<li>Cuerpo a Cuerpo - (C)</li>
	<li>Tiro con Arco - (T)</li>
	<li>Arrojar o Atletismo - (A)</li>
	<li>Armas de Fuego - (F)</li>
	<li>Informatica - (I)</li>
</ul>


<b>Opciones de combate</b>:
<ul>
	<li><b>Turnos</b>: l&iacute;mite m&aacute;ximo de rondas.</li>
	<li><b>Vitalidad</b>: vida inicial para ambos combatientes.</li>
	<li><b>Heridas</b>: activa o ignora penalizadores por da&ntilde;o.</li>
	<li><b>Curaci&oacute;n</b>: ninguna, ambos, solo P1 o solo P2.</li>
	<li><b>Combate</b>: modo Normal o Umbral.</li>
	<li><b>Tono narrativo</b>: Aleatorio, Serio, &Eacute;pico, Brutal o Ir&oacute;nico.</li>
	<li><b>Mensajes ambientales</b>: activa/desactiva eventos de ambientaci&oacute;n.</li>
	<li><b>Rubberbanding</b>: activa/desactiva ajustes de equilibrio durante el combate.</li>
</ul>

<b>Aleatorizar</b>:
<ul>
	<li><b>Personajes</b>: selecciona P1/P2 al azar.</li>
	<li><b>Armamento</b>, <b>Protecci&oacute;n</b>, <b>Formas</b>: asignaci&oacute;n aleatoria de equipo y forma.</li>
	<li><b>Turnos</b> y <b>Vitalidad</b>: valores aleatorios al iniciar.</li>
</ul>

<b>Resultado del combate</b>:<br/>
Al finalizar se calcula vencedor, vitalidad restante y resumen final. Tambi&eacute;n se aplican mensajes de victoria/empate y frases contextuales del simulador si est&aacute;n configuradas.<br/>

<br/>

<b>Notas del simulador</b>:

<br/>

<ul>

<li>Todas las tiradas tienen la dificultad est&aacute;ndar de <b>6</b>.</li>
<li>El c&aacute;lculo de da&ntilde;o y resistencia depende del arma/protector, la forma y el modo de combate.</li>
<li>El resultado puede variar por azar, configuraci&oacute;n y eventos narrativos.</li>
<li>Las opciones visibles en pantalla son las que aplica esta versi&oacute;n del simulador.</li>

</ul>

<b>Esquema de combate (resumen)</b>:

<br/>

<ol>

<li>Se calculan iniciativa y par&aacute;metros de ataque/defensa seg&uacute;n modo y configuraci&oacute;n.</li>
<li>Se resuelven turnos de ataque/esquiva y tiradas de da&ntilde;o/resistencia.</li>
<li>La vitalidad se reduce hasta victoria, derrota o empate por l&iacute;mite de turnos.</li>
<li>Se construye un resumen final con estado del combate y eventos narrativos.</li>

</ol>

</div>

</fieldset>
