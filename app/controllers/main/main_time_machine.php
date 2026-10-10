<?php
setMetaFromPage(
    "Máquina del Tiempo | Heaven's Gate",
    'Visita las primeras versiones de la web de Heaven\'s Gate, conservadas como archivo histórico de enero de 2007.',
    null,
    'website'
);
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-time-machine.css');
}
$archiveRoot = dirname(__DIR__, 3) . '/public/time-machine';
$versions = [
    [
        'path' => '2007',
        'date' => '9 de enero de 2007',
        'title' => 'Heaven\'s Gate: la primera web',
        'description' => 'La primera presentación pública conservada: HTML artesanal, un lobo bajo el cielo nocturno, biografías y documentos.',
        'preview' => '/public/time-machine/2007/hg/fondo.jpg',
        'count' => '154 archivos publicados',
    ],
    [
        'path' => 'v1.2',
        'date' => '30 de enero de 2007',
        'title' => 'Heaven\'s Gate v1.2',
        'description' => 'Menú desplegable, cursor inspirado en Final Fantasy, navegación con marcos y un archivo más organizado.',
        'preview' => '/public/time-machine/v1.2/imagenes/fondomenu.gif',
        'count' => '160 archivos publicados',
    ],
];
?>
<section class="hg-time-machine" aria-labelledby="hg-time-machine-title">
    <div class="hg-time-machine__heading">
        <span class="hg-time-machine__eyebrow">Archivo histórico · 2007</span>
        <h1 id="hg-time-machine-title">Máquina del Tiempo</h1>
        <p>Antes de la web actual hubo dos versiones que contaban nuestras primeras historias a golpe de HTML, GIF y buenas intenciones. Aquí se conservan para poder volver a visitarlas tal como eran.</p>
    </div>
    <div class="hg-time-machine__versions">
        <?php foreach ($versions as $version): ?>
            <?php
                $available = is_file($archiveRoot . '/' . $version['path'] . '/index.html');
                $href = '/public/time-machine/' . $version['path'] . '/index.html';
            ?>
            <article class="hg-time-machine__card">
                <div class="hg-time-machine__visual">
                    <?php if ($available): ?>
                        <img src="<?= htmlspecialchars($version['preview'], ENT_QUOTES, 'UTF-8') ?>" alt="Imagen original de la web de <?= htmlspecialchars($version['date'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                    <?php else: ?>
                        <span class="hg-time-machine__placeholder" aria-hidden="true">2007</span>
                    <?php endif; ?>
                </div>
                <div class="hg-time-machine__card-content">
                    <time class="hg-time-machine__date" datetime="2007-01-<?= $version['path'] === '2007' ? '09' : '30' ?>"><?= htmlspecialchars($version['date'], ENT_QUOTES, 'UTF-8') ?></time>
                    <h2><?= htmlspecialchars($version['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p><?= htmlspecialchars($version['description'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if ($available): ?>
                        <span class="hg-time-machine__file-count"><?= htmlspecialchars($version['count'], ENT_QUOTES, 'UTF-8') ?></span>
                        <a class="hg-time-machine__open" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Abrir versión original <span aria-hidden="true">↗</span></a>
                    <?php else: ?>
                        <span class="hg-time-machine__file-count">Copia estática pendiente de instalación</span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <aside class="hg-time-machine__notice">
        <h2>Una fotografía de su época, no la continuidad vigente</h2>
        <p>Las páginas se publican como documentos históricos. Algunas fechas, nombres y acontecimientos difieren de la reconstrucción narrativa actual. <strong>Prevalece siempre la continuidad vigente</strong>, no las notas de 2007.</p>
        <p>Se mantienen los diseños y gran parte de la navegación original. Ciertos formatos de época, especialmente la música MIDI incrustada y funciones dependientes de navegadores antiguos, pueden no reproducirse hoy.</p>
    </aside>
</section>
