<br/>

<fieldset>
	<legend>Informaci&oacute;n</legend>

<center>
	<a href="javascript:MostrarOcultar('texto1');" id="enlace1">Mostrar informaci&oacute;n</a>
</center>

	<div class="ocultable" id="texto1">

		<p><strong>Selecci&oacute;n de combatientes</strong>:</p>
		<p>Debes elegir dos participantes (P1 y P2) para habilitar la configuraci&oacute;n avanzada. 
			El sistema acepta selecci&oacute;n manual o aleatoria por cada hueco.</p>
		<p>Si ya hay dos seleccionados y eliges un tercer personaje, se reemplaza autom&aacute;ticamente 
			primero P1 y en el siguiente clic P2 (alternando).</p>

		<p><strong>Forma</strong>:</p>
		<p>Define la forma en la que luchar&aacute; cada combatiente. Las formas disponibles dependen de la especie 
			del personaje.</p>

		<p><strong>Arma y protector</strong>:</p>
		<p>Puedes dejar ambos en vac&iacute;o o elegir equipamiento del personaje. En armas se indica entre 
			par&eacute;ntesis la habilidad principal usada por el simulador.</p>

		<p><strong>Leyenda de armas</strong>:</p>

		<ul>
			<li>Pelea - (P)</li>
			<li>Cuerpo a Cuerpo - (C)</li>
			<li>Tiro con Arco - (T)</li>
			<li>Arrojar o Atletismo - (A)</li>
			<li>Armas de Fuego - (F)</li>
			<li>Informatica - (I)</li>
		</ul>

		<p><strong>Opciones de combate</strong>:</p>
		<ul>
			<li><strong>Turnos</strong>: l&iacute;mite m&aacute;ximo de rondas. <code>5</code></li>
			<li><strong>Vitalidad</strong>: vida inicial para ambos combatientes. <code>7</code></li>
			<li><strong>Heridas</strong>: activa o ignora penalizadores por da&ntilde;o. <code>Sí</code></li>
			<li><strong>Curaci&oacute;n</strong>: ninguna, ambos, solo P1 o solo P2. <code>No</code></li>
			<li><strong>Combate</strong>: modo Normal o Umbral.</li>
			<li><strong>Tono narrativo</strong>: Aleatorio, Serio, &Eacute;pico, Brutal o Ir&oacute;nico.</li>
			<li><strong>Mensajes ambientales</strong>: activa/desactiva eventos de ambientaci&oacute;n.</li>
			<li><strong>Rubberbanding</strong>: activa/desactiva ajustes de equilibrio durante el combate.</li>
		</ul>

		<p><strong>Aleatorizar</strong>:</p>
		<ul>
			<li><strong>Armamento</strong>, <strong>Protecci&oacute;n</strong>, <strong>Formas</strong>: 
			asignaci&oacute;n aleatoria de equipo y forma.</li>
			<li><strong>Turnos</strong> y <strong>Vitalidad</strong>: valores aleatorios al iniciar.</li>
		</ul>

		<p><strong>Resultado del combate</strong>:</p>
		<p>Al finalizar se calcula vencedor, vitalidad restante y resumen final. Tambi&eacute;n se aplican 
			mensajes de victoria/empate y frases contextuales del simulador si est&aacute;n configuradas.</p>

		<p><strong>Esquema de combate (resumen)</strong>:</p>

		<ol>
			<li>Se calculan iniciativa y par&aacute;metros de ataque/defensa seg&uacute;n modo y configuraci&oacute;n.</li>
			<li>Se resuelven turnos de ataque/esquiva y tiradas de da&ntilde;o/resistencia.</li>
			<li>La vitalidad se reduce hasta victoria, derrota o empate por l&iacute;mite de turnos.</li>
			<li>Se construye un resumen final con estado del combate y eventos narrativos.</li>
		</ol>

	<p><strong>Notas del simulador</strong>:</p>

		<ul>

		<li>Todas las tiradas tienen la dificultad est&aacute;ndar de <strong>6</strong>.</li>
		<li>El c&aacute;lculo de da&ntilde;o y resistencia depende del arma/protector, la forma y el modo de combate.</li>
		<li>El resultado puede variar por azar, configuraci&oacute;n y eventos narrativos.</li>
		<li>Las opciones visibles en pantalla son las que aplica esta versi&oacute;n del simulador.</li>
		<li><strong>No se aplican todas las reglas del sistema.</strong> Limitaciones actuales:
			<ul>
				<li>No se puede cambiar de forma en mitad del combate.</li>
				<li>La regeneraci&oacute;n se aplica aunque se sufra da&ntilde;o agravado.</li>
				<li>No se distinguen tipos de da&ntilde;o: todos se tratan igual.</li>
				<li>No se contemplan dones ni poderes especiales; es combate f&iacute;sico 1 vs 1.</li>
			</ul>
		</li>

		</ul>

	</div>
</fieldset>
