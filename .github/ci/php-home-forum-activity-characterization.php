<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/domains/forum/recent_activity.php';

function hg_forum_activity_expect(bool $condition, string $description): void
{
    if (!$condition) {
        fwrite(STDERR, 'Forum activity regression: ' . $description . PHP_EOL);
        exit(1);
    }
}

$body = '[quote author=Mark]CITA [quote]CITA INTERIOR[/quote] FIN CITA[/quote]'
    . '[hg_avatar=89,SkyBlue]— Se acerca la tormenta.[/hg_avatar]'
    . ' [spoiler=Secreto]NO PUBLICAR[/spoiler]'
    . ' [img]https://example.com/image.jpg[/img]';

$excerpt = hg_forum_recent_excerpt($body);
hg_forum_activity_expect(str_contains($excerpt, 'Se acerca la tormenta'), 'character dialogue must remain');
hg_forum_activity_expect(!str_contains($excerpt, 'CITA'), 'nested quotes must be hidden');
hg_forum_activity_expect(!str_contains($excerpt, 'NO PUBLICAR'), 'spoiler body must be hidden');
hg_forum_activity_expect(!str_contains($excerpt, 'example.com'), 'image embeds must be hidden');
hg_forum_activity_expect(!str_contains($excerpt, 'hg_avatar'), 'BBCode must be stripped');

$plain = hg_forum_recent_excerpt('Una frase &amp; otra <strong>con estilo</strong>.');
hg_forum_activity_expect($plain === 'Una frase & otra con estilo.', 'HTML and entities must become plain text');

$empty = hg_forum_recent_excerpt('[spoiler]Oculto[/spoiler]');
hg_forum_activity_expect($empty === 'Lee la intervención completa en el lector del foro.', 'empty excerpt has fallback');

fwrite(STDOUT, "Home forum activity characterization: OK\n");
