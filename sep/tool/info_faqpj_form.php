<?php

$secfaq = $_GET['sk'];

/* <input type='hidden' name='sk' value='1'>
<input type='submit' value='Siguiente ->' class='boton1'>
<input type='hidden' name='p' value='faqpj'>

*/

include("sep/main/main_nav_bar.php");	// Barra Navegación

?>


<h2>C&oacute;mo crear tu primer personaje</h2>

<br/><br/>

<span align="justify">

<form action='index.php?p=faqpj' method='get'>

Se bienvenid@ a &eacute;sta secci&oacute;n, d&oacute;nde explicaremos paso a paso c&oacute;mo crear tu primer personaje para <b>Hombe Lobo: El Apocalipsis</b>.

<br/><br/>

De primeras, deber&aacute;s elegir el nombre y el apellido de tu personaje. Se recomienda que sea lo m&aacute;s realista posible, nada de poner <i>Machacador Oscuro</i> o <i>Master H.P.</i>. La seriedad con que te lo tomes influenciar&aacute; en el trato que puedas recibir por parte del <b>Narrador de Juego</b>.

<br/><br/>
<center>

Nombre: <input type='text' name='nombre' size='15' maxlength='14'><br/>
Apellido: <input type='text' name='apellido' size='15' maxlength='14'>

</center>
<br/>

A continuaci&oacute;n elige la raza de tu personaje. Los Hombres Lobo se dividen en tres razas b&aacute;sicamente: <b>Hom&iacute;nidos, Lupus y Metis</b>.<br/><br/>
<a href="javascript:MostrarOcultar('texto1');" id="enlace1">Tipos de Raza</a>

<div class="ocultable" id="texto1">

<br/>

<ul>

<li><b>Hom&iacute;nido</b>: Garous nacidos entre los hombres, posiblemente parientes de otro Hombre Lobo. Son los que menos est&aacute;n en contacto con la naturaleza.<br/><b>Dones iniciales</b>: Maestro del Fuego, Olor a Hombre, Persuasi&oacute;n<br/><b>Gnosis inicial</b>: 1</li>

<br/>
<li><b>Metis</b>: &Eacute;sta raza ocupa el escal&oacute;n m&aacute;s bajo de la sociedad Garou. Al ser obscenidades provenientes de dos Garou (algo que est&aacute; prohibido), se les tiene un desprecio universal.
Adem&aacute;s, nacen con un tipo de deformidad, ya sea una tara mental o alg&uacute;n defecto f&iacute;sico. <br/>Pero no todo son inconvenientes, los <b>Metis</b> tienen una mejor conexi&oacute;n con Gaia que sus hermanos <b>Hom&iacute;nidos</b> y, como desde su nacimiento est&aacute;n en la socidad Garou, conocen todas sus leyes a la perfecci&oacute;n.<br/><b>Dones iniciales</b>: Crear Elemento, Ira Primaria, Sentir al Wyrm<br/><b>Gnosis inicial</b>: 3</li>

<br/>
<li><b>Lupus</b>: Nacidos en el bosque o en las selvas como lobos normales, los Lupus est&aacute;n extingui&eacute;ndose cada d&iacute; m&aacute;s r&aacute;pido, mayormente por culpa del hombre. Por esto, suelen odiar la humanidad y proteger a sus compa&ntilde;eros lupinos. Los personajes <b>Lupus</b> no pueden adquirir ciertas habilidades, ya que est&aacute;n relacionadas &iacute;ntimamente con los humanos.<br/><b>Dones iniciales</b>: Salto de Liebre, Sentidos Aguzados, Sentir Presa<br/><b>Gnosis inicial</b>: 5</li>

</ul>

</div>

<center>
Elige tu raza: 

<select name='raza'>

<option value='Hom&iacute;nido'>Hom&iacute;nido</option>
<option value='Metis'>Metis</option>
<option value='Lupus'>Lupus</option>

</select>
</center>

<br/>

Es momento de seleccionar el <b>Auspicio</b> de tu personaje. Es la fase lunar bajo la que naci&oacute;; es como una se&ntilde;al astrol&oacute;gica, s&oacute;lo que mucho m&aacute;s potente. El <b>Auspicio</b> de un Garou determina el papel que desarrollar&aacute; dentro de la sociedad de los Hombres Lobo.

<br/><br/>

<a href="javascript:MostrarOcultar('texto2');" id="enlace2">Tipos de Auspicio</a>

<div class="ocultable" id="texto2">

<br/>

<ul>

<li><b>Ragabash: Luna Nueva:</b> Embaucadores e interrogadores; combaten al Wyrm con astucia e ingenio.<br/><b>Dones iniciales</b>: Abrir Sello, Ojo Nublado, Olor a Agua Corriente.<br/><b>Rabia inicial</b>: 1<br/><b>Renombre inicial</b>: Tres en cualquier combinaci&oacute;n</li> 

<br/>
<li><b>Theurge: Luna Creciente:</b> Videntes y chamanes; hablan con los esp&iacute;ritus y comprenden sus tradiciones.<br/><b>Dones iniciales</b>: Lenguaje Espiritual, Roce Materno, Sentir al Wyrm.<br/><b>Rabia inicial</b>: 2<br/><b>Renombre inicial</b>: Sabidur&iacute;a 3</li> 

<br/>
<li><b>Philodox: Media Luna:</b> Jueces y guardianes de la ley; presentan desaf&iacute;os a los Garou y suelen ser los &aacute;rbitros finales.<br/><b>Dones iniciales</b>: Olor de la Aut&eacute;ntica Forma, Resistir Dolor, Verdad de Gaia.<br/><b>Rabia inicial</b>: 3<br/><b>Renombre inicial</b>: Honor 3</li> 

<br/>
<li><b>Galliard: Luna Gibosa:</b> Guardianes de la Sabidur&iacute;a y Cantadores de historias; recuerdan la historia delos Garou y la ense&ntilde;an a trave&eacute;s de sus apasionados relatos.<br/><b>Dones iniciales</b>: Habla Mental, Lenguaje Animal, La Llamada de la Selva.<br/><b>Rabia inicial</b>: 4<br/><b>Renombre inicial</b>: Gloria 2, Sabidur&iacute;a 1</li> 

<br/>
<li><b>Ahroun: Luna Llena:</b> Guerreros y protectores; luchan como los de ning&uacute;n otro auspicio y llevan la destrucci&oacute;n al Wyrm, all&iacute; donde &eacute;ste habita y prolifera.<br/><b>Dones iniciales</b>: Garras como Cuchillos, Inspiraci&oacute;n, El Roce del Derribo<br/><b>Rabia inicial</b>: 5<br/><b>Renombre inicial</b>: Gloria 2, Honor 1</li> 

</ul>

</div>

<center>

Elige tu auspicio:

<select name='auspicio'>

<option value='Ragabash'>Ragabash</option>
<option value='Theurge'>Theurge</option>
<option value='Philodox'>Philodox</option>
<option value='Galliard'>Galliard</option>
<option value='Ahroun'>Ahroun</option>

</select>
</center>

</span>