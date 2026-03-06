<?php

if (!function_exists('sim_pick_pool_item')) {
    function sim_pick_pool_item($pool, $fallback = '')
    {
        if (!is_array($pool) || empty($pool)) {
            return (string)$fallback;
        }
        return (string)$pool[array_rand($pool)];
    }
}

$tipoCombate = isset($tipoCombate) ? (string)$tipoCombate : 'normal';
$simNarrativeTone = isset($simNarrativeTone) ? strtolower((string)$simNarrativeTone) : 'random';
$simRelationContext = isset($simRelationContext) ? (string)$simRelationContext : 'neutral';

$simDefeatMessages = array(
    'normal' => array(
        'brutal' => array(
            "explota en una supernova de v&iacute;sceras",
            "se desploma miserablemente",
            "comienza a toser descontroladamente y un rato despu&eacute;s, exhala su &uacute;ltimo aliento",
            "cae al suelo para no levantarse jam&aacute;s",
            "muere dolorosamente",
            "ha sufrido diversas hemorragias y no se puede mantener en pie",
            "recibe un golpe mortal, empieza a expulsar sangre violentamente y &iexcl;MUERE!",
            "tiene los m&uacute;sculos demasiado entumecidos para continuar el combate",
            "pierde el conocimiento por el dolor",
            "acaba desangr&aacute;ndose en el suelo",
            "queda fuera de combate",
            "no consigue soportar las heridas y se desploma",
            "revienta brutalmente, quedando s&oacute;lo la parte inferior de su cuerpo",
            "cae de rodillas y termina derrumb&aacute;ndose sin respuesta",
            "recibe un impacto seco y no vuelve a incorporarse",
            "trata de resistir, pero su cuerpo no responde",
            "se tambalea, pierde el equilibrio y cae",
            "se queda inm&oacute;vil tras el &uacute;ltimo intercambio"
        ),
        'serio' => array(
            "cae derrotado tras una dura resistencia",
            "cede finalmente ante el castigo recibido",
            "no logra mantener la guardia y termina en el suelo",
            "queda neutralizado y fuera de combate",
            "se derrumba agotado tras el intercambio final"
        ),
        'neutral' => array(
            "no puede continuar el enfrentamiento",
            "pierde el duelo y se retira de la pelea",
            "queda fuera de juego",
            "es superado en el tramo final del combate",
            "termina derrotado tras un combate intenso"
        ),
        'ironico' => array(
            "decide que hoy no era su d&iacute;a y se va al suelo",
            "comprueba en primera persona que la estrategia era mejorable",
            "pierde la discusi&oacute;n y tambi&eacute;n el combate",
            "se queda sin argumentos... y sin guardia",
            "aprende por las malas que subestimar al rival era mala idea"
        )
    ),
    'umbral' => array(
        'epico' => array(
            "se desintegra en un tornado espiritual",
            "fallece con violencia, perturbando a los esp&iacute;ritus cercanos",
            "acaba desvaneci&eacute;ndose sin dejar rastro",
            "se quiebra en una lluvia de esencia umbral",
            "es absorbido por corrientes espirituales inestables",
            "queda disipado entre ecos de la Umbra",
            "pierde su forma y se diluye en la niebla espiritual",
            "colapsa, incapaz de sostener su energ&iacute;a interior"
        ),
        'serio' => array(
            "es doblegado en el plano espiritual",
            "pierde el control de su esencia y cae",
            "no consigue sostener su voluntad y se desvanece"
        ),
        'neutral' => array(
            "es superado y queda fuera del conflicto espiritual",
            "cede en la Umbra y no puede continuar",
            "queda derrotado en el choque de voluntades"
        ),
        'ironico' => array(
            "buscaba iluminaci&oacute;n, pero encuentra derrota",
            "su paz interior se toma el d&iacute;a libre",
            "la Umbra no acepta su solicitud de pr&oacute;rroga"
        )
    )
);

$simMode = ($tipoCombate === 'umbral') ? 'umbral' : 'normal';
$availableTones = $simDefeatMessages[$simMode] ?? array();
$weightedTone = 'serio';
if ($simNarrativeTone !== 'random' && isset($availableTones[$simNarrativeTone])) {
    $weightedTone = $simNarrativeTone;
} else {
    $toneKeys = array_keys($availableTones);
    $weightedTone = !empty($toneKeys) ? $toneKeys[array_rand($toneKeys)] : 'serio';
}

$caexy = $availableTones[$weightedTone] ?? array();
if (empty($caexy)) {
    $caexy = array("cae derrotado");
}

$ceaxy = array_rand($caexy);
$kae = $caexy[$ceaxy];

$simRelationDefeatMessages = array(
    'enemy' => array(
        "es aplastado con una crueldad despiadada",
        "cae tras un castigo brutal sin posibilidad de respuesta",
        "es aniquilado en un estallido de violencia feroz",
        "es derribado sin misericordia por su enemigo"
    ),
    'rival' => array(
        "es superado por su rival en el momento decisivo",
        "pierde el duelo de rivalidad en un final intenso",
        "cae tras un intercambio feroz entre rivales"
    ),
    'ally' => array(
        "acepta la derrota ante su antiguo aliado",
        "cae con respeto, derrotado por quien fue su apoyo",
        "es superado en un duelo entre aliados"
    )
);

if (isset($simRelationDefeatMessages[$simRelationContext])) {
    $kae = sim_pick_pool_item(
        array_merge($simRelationDefeatMessages[$simRelationContext], array($kae)),
        $kae
    );
}

$simDoubleKoMessages = array(
    "&iexcl;Los dos combatientes se han matado el uno al otro!",
    "&iexcl;Doble KO! Ninguno queda en pie.",
    "Ambos caen al mismo tiempo. El combate termina sin vencedor.",
    "Golpe final simult&aacute;neo: nadie sobrevive al intercambio."
);

$simTimeLimitMessages = array(
    "&iexcl;Tiempo! Combate terminado.",
    "Se agota el tiempo reglamentario. Fin del combate.",
    "La campana final detiene el enfrentamiento.",
    "No queda tiempo: el combate concluye ahora."
);

$simVictoryMessages = array(
    'close' => array(
        "&iexcl;%s vence por la m&iacute;nima!",
        "&iexcl;%s se lleva una victoria ajustad&iacute;sima!",
        "&iexcl;%s gana por un margen m&iacute;nimo!",
        "&iexcl;%s termina agotado, pero se lleva la victoria!"
    ),
    'dominant' => array(
        "&iexcl;%s arrasa sin contemplaci&oacute;n!",
        "&iexcl;%s domina el combate de principio a fin!",
        "&iexcl;%s firma una victoria aplastante!",
        "&iexcl;%s se mantiene firme y deja al rival sin respuesta!"
    ),
    'comeback' => array(
        "&iexcl;%s remonta cuando todo parec&iacute;a perdido!",
        "&iexcl;%s le da la vuelta al combate en el &uacute;ltimo momento!",
        "&iexcl;%s sobrevive al l&iacute;mite y termina venciendo!",
        "&iexcl;%s, agotado y al borde del colapso, consigue imponerse!"
    ),
    'standard' => array(
        "&iexcl;%s derrota a su rival!",
        "&iexcl;%s gana la pelea!",
        "&iexcl;%s impone su ley en la arena!",
        "&iexcl;%s se muestra implacable y cierra el combate!"
    )
);

$simRelationVictoryMessages = array(
    'enemy' => array(
        "&iexcl;%s despedaza a su oponente sin piedad!",
        "&iexcl;%s ejecuta una victoria cruel sobre su rival!",
        "&iexcl;%s castiga a su oponente hasta dejarlo fuera de combate!"
    ),
    'rival' => array(
        "&iexcl;%s vence a su rival en un duelo de alto voltaje!",
        "&iexcl;%s se impone en la rivalidad!",
        "&iexcl;%s gana el pulso definitivo contra su rival!"
    ),
    'ally' => array(
        "&iexcl;%s supera a su compa&ntilde;ero de bando en un combate exigente!",
        "&iexcl;%s se lleva la victoria ante quien fue su apoyo!",
        "&iexcl;%s gana, pero reconoce la resistencia de su oponente!"
    ),
    'romance' => array(
        "&iexcl;%s vence en un combate cargado de tensi&oacute;n personal!",
        "&iexcl;%s se impone en un duelo con historia compartida!",
        "&iexcl;%s gana un enfrentamiento tan duro como &iacute;ntimo!"
    ),
    'family' => array(
        "&iexcl;%s vence en un combate marcado por lazos de sangre!",
        "&iexcl;%s se impone en un duelo familiar!",
        "&iexcl;%s gana pese al peso de la familia!"
    ),
    'hierarchy' => array(
        "&iexcl;%s redefine la jerarqu&iacute;a con su victoria!",
        "&iexcl;%s se impone en la cadena de mando!",
        "&iexcl;%s gana y deja clara su posici&oacute;n!"
    )
);

$simDrawMessages = array(
    "El combate termina en empate.",
    "Ninguno cede: empate total.",
    "No hay vencedor. La pelea acaba en tablas."
);

?>
