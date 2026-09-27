<?php
setMetaFromPage(
    "Manual del visor de partidas por foro | Ayuda | Heaven's Gate",
    "Guía de uso del visor de partidas por foro de Heaven's Gate.",
    null,
    'article'
);
include("app/partials/main_nav_bar.php");
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-help.css');
}
?>

<main class="hg-help-shell help-article">
    <header class="hg-help-hero">
        <p class="hg-help-kicker"><a href="/help">Ayuda</a></p>
        <h1>Visor de partidas por foro</h1>
        <p class="hg-help-lead">Lectura rápida, navegación por mensajes y acceso directo al foro.</p>
    </header>

    <aside class="hg-help-callout">
        <strong>Importante</strong>
        <p>El visor no sustituye al foro. El contenido se consulta directamente desde el foro y se presenta en Heaven's Gate con una interfaz de lectura más cómoda. Para responder o editar mensajes se sigue utilizando el foro original.</p>
    </aside>

    <nav class="hg-help-toc" aria-label="Contenido de la guía">
        <a href="#antes">Antes de empezar</a>
        <a href="#capitulo">Elegir capítulo</a>
        <a href="#leer">Leer y moverse</a>
        <a href="#toc">Tabla de contenidos</a>
        <a href="#flujo">Flujo recomendado</a>
        <a href="#faq">Preguntas rápidas</a>
    </nav>

    <section class="hg-help-section" id="antes">
        <span class="hg-help-section-number">1</span>
        <div class="hg-help-section-head">
            <h2>Antes de empezar</h2>
            <p>Un capítulo solo aparece en el visor cuando Dirección lo ha dado de alta y lo ha asociado a su tema del foro. Si el tema existe pero no aparece aquí, debe registrarse primero.</p>
        </div>
        <div class="hg-help-definition-grid">
            <div><strong>Contenido autorizado</strong><span>El visor muestra únicamente capítulos y temas registrados para esta herramienta.</span></div>
            <div><strong>Lectura directa</strong><span>Los mensajes se leen desde el foro al cargar la página; no hace falta copiarlos a Heaven's Gate.</span></div>
            <div><strong>Orden cronológico</strong><span>El hilo se presenta desde el mensaje más antiguo al más reciente.</span></div>
            <div><strong>Navegación rápida</strong><span>Puedes saltar entre mensajes sin recorrer manualmente páginas completas mediante scroll.</span></div>
        </div>
    </section>

    <section class="hg-help-section" id="capitulo">
        <span class="hg-help-section-number">2</span>
        <div class="hg-help-section-head">
            <h2>Elegir un capítulo</h2>
        </div>
        <!-- Captura futura: cabecera del visor con Cambiar capítulo e Ir al último mensaje. -->
        <div class="hg-help-steps">
            <div class="hg-help-step"><span>1</span><div><strong>Pulsa «Cambiar capítulo».</strong><p>El catálogo permanece plegado mientras lees para no robar espacio al hilo.</p></div></div>
            <div class="hg-help-step"><span>2</span><div><strong>Busca el capítulo.</strong><p>Puedes localizarlo por título, episodio o agrupación. Solo aparecen los capítulos registrados por Dirección.</p></div></div>
            <div class="hg-help-step"><span>3</span><div><strong>Abre el tema.</strong><p>La cabecera mostrará episodio, temporada y agrupación cuando esos datos estén vinculados al capítulo.</p></div></div>
        </div>
        <div class="hg-help-note">
            <strong>Si no encuentras un capítulo</strong>
            <p>No significa necesariamente que el tema no exista. Comprueba primero que el DJ lo haya dado de alta y vinculado correctamente.</p>
        </div>
    </section>

    <section class="hg-help-section" id="leer">
        <span class="hg-help-section-number">3</span>
        <div class="hg-help-section-head">
            <h2>Leer y moverse por un hilo</h2>
            <p>El control flotante permanece visible mientras lees y actúa como centro de navegación.</p>
        </div>
        <div class="hg-help-definition-grid">
            <div><strong>Flecha izquierda</strong><span>Salta al mensaje anterior.</span></div>
            <div><strong>Indicador central</strong><span>Muestra tu posición actual y el autor del mensaje visible. Al pulsarlo abre la tabla de contenidos.</span></div>
            <div><strong>Flecha derecha</strong><span>Salta al mensaje siguiente.</span></div>
            <div><strong>Ir al último mensaje</strong><span>Desde la cabecera puedes saltar directamente a la intervención más reciente.</span></div>
        </div>
        <div class="hg-help-note">
            <strong>Acciones de cada mensaje</strong>
            <p>«Abrir en el foro» lleva a la intervención original para responder, citar o editar. «Copiar mensaje» copia su contenido; la cabecera ofrece además una acción para copiar el hilo completo.</p>
        </div>
    </section>

    <section class="hg-help-section" id="toc">
        <span class="hg-help-section-number">4</span>
        <div class="hg-help-section-head">
            <h2>Tabla de contenidos flotante</h2>
            <p>Pulsa el indicador central del navegador para abrir la tabla sin reducir el ancho del texto.</p>
        </div>
        <!-- Captura futura: tabla de contenidos flotante abierta sobre un hilo largo. -->
        <div class="hg-help-steps">
            <div class="hg-help-step"><span>1</span><div><strong>Se abre sobre la lectura.</strong><p>El texto permanece debajo y conserva todo su ancho.</p></div></div>
            <div class="hg-help-step"><span>2</span><div><strong>Empieza por lo reciente.</strong><p>En hilos largos, la vista compacta muestra primero los últimos mensajes.</p></div></div>
            <div class="hg-help-step"><span>3</span><div><strong>Pulsa «Ver todos».</strong><p>Expande la lista completa cuando necesitas localizar una intervención antigua.</p></div></div>
            <div class="hg-help-step"><span>4</span><div><strong>Elige un mensaje.</strong><p>El visor salta a esa intervención y cierra automáticamente la tabla.</p></div></div>
            <div class="hg-help-step"><span>5</span><div><strong>Cierra sin saltar.</strong><p>Pulsa de nuevo el control central, haz clic fuera de la ventana o utiliza Esc.</p></div></div>
        </div>
    </section>

    <section class="hg-help-section" id="flujo">
        <span class="hg-help-section-number">5</span>
        <div class="hg-help-section-head">
            <h2>Flujo recomendado</h2>
        </div>
        <div class="hg-help-flow-grid">
            <div>
                <strong>Para jugadores</strong>
                <p>Abre el visor, elige capítulo y usa «Ir al último mensaje» si estás al día. Navega con las flechas o abre la tabla central cuando necesites localizar una intervención concreta. Para continuar la partida, abre el mensaje original en el foro.</p>
            </div>
            <div>
                <strong>Para Dirección</strong>
                <p>Da de alta el capítulo y vincúlalo al tema correcto antes de compartir el visor. Después no hace falta copiar las respuestas: el contenido se consulta directamente desde el foro.</p>
            </div>
        </div>
    </section>

    <section class="hg-help-section" id="faq">
        <span class="hg-help-section-number">6</span>
        <div class="hg-help-section-head">
            <h2>Preguntas rápidas</h2>
        </div>
        <div class="hg-help-faq">
            <details>
                <summary>¿Por qué no aparece mi capítulo?</summary>
                <p>Porque todavía no está registrado, está desactivado o no se ha vinculado al tema correcto. Debe revisarlo el DJ.</p>
            </details>
            <details>
                <summary>¿Tengo que publicar también en Heaven's Gate?</summary>
                <p>No. La partida sigue escribiéndose en el foro. El visor es una capa de lectura y navegación.</p>
            </details>
            <details>
                <summary>¿Cuándo aparecen las respuestas nuevas?</summary>
                <p>El visor consulta el foro cuando se carga la página. Si sabes que hay una respuesta nueva y no la ves, recarga el capítulo.</p>
            </details>
            <details>
                <summary>¿Puedo responder desde el visor?</summary>
                <p>No. Utiliza la acción «Abrir en el foro» del mensaje para continuar desde la intervención original.</p>
            </details>
            <details>
                <summary>¿Por qué los mensajes están de antiguo a reciente?</summary>
                <p>Porque así se conserva la lectura natural. Para retomar rápidamente una partida puedes saltar al último mensaje o utilizar la tabla de contenidos.</p>
            </details>
        </div>
    </section>

    <footer class="hg-help-article-footer">
        <a href="/tools/forum-topic-viewer">Abrir el visor</a>
        <a href="/help">&larr; Volver a Ayuda</a>
    </footer>
</main>
