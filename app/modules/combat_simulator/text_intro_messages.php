<?php

$tipoCombate = isset($tipoCombate) ? (string)$tipoCombate : 'normal';
$nombre1 = isset($nombre1) ? (string)$nombre1 : 'Combatiente 1';
$nombre2 = isset($nombre2) ? (string)$nombre2 : 'Combatiente 2';
$usarRegen = isset($usarRegen) ? (string)$usarRegen : 'no';
$maxturn = isset($maxturn) ? (int)$maxturn : 5;
$simNarrativeTone = isset($simNarrativeTone) ? strtolower((string)$simNarrativeTone) : 'random';
$simRelationContext = isset($simRelationContext) ? (string)$simRelationContext : 'neutral';
$simRelationType = isset($simRelationType) ? (string)$simRelationType : '';
$gender1 = isset($gender1) ? (string)$gender1 : '';
$gender2 = isset($gender2) ? (string)$gender2 : '';

$simIntroMessages = array(
    'normal' => array(
        'serio' => array(
            "<b>$nombre1</b> mira desafiante a <b>$nombre2</b>, se huele violencia en el aire...",
            "&iexcl;Todos se preparan para el combate entre <b>$nombre1</b> y <b>$nombre2</b>!",
            "<b>$nombre1</b> escupe en la cara a <b>$nombre2</b>. La pelea es inevitable.",
            "En un acantilado precioso, el mar ser&aacute; testigo de la violenta batalla entre <b>$nombre2</b> y <b>$nombre1</b>.",
            "&iexcl;<b>$nombre1</b> se abalanza sobre <b>$nombre2</b>! &iexcl;<b>$nombre2</b> aparta a <b>$nombre1</b> y se prepara para la lucha!",
            "La presencia de <b>$nombre2</b> ofende a <b>$nombre1</b> sobremanera. &iexcl;<b>$nombre1</b> se predispone a atacar!",
            "&iexcl;El silencio de la noche se rompe para dar lugar al enfrentamiento entre <b>$nombre1</b> y <b>$nombre2</b>!",
            "<b>$nombre1</b> y <b>$nombre2</b> cierran distancia sin apartar la mirada.",
            "Nadie habla. S&oacute;lo se oye la respiraci&oacute;n de <b>$nombre1</b> y <b>$nombre2</b> antes del choque.",
            "Los dos rivales adoptan guardia. <b>$nombre1</b> y <b>$nombre2</b> ya no pueden retroceder.",
            "Una calma tensa precede al primer golpe entre <b>$nombre1</b> y <b>$nombre2</b>.",
            "<b>$nombre1</b> se ajusta la postura. <b>$nombre2</b> responde con una sonrisa helada.",
            "Las reglas son simples: dos entran, uno sale. <b>$nombre1</b> frente a <b>$nombre2</b>."
        ),
        'epico' => array(
            "El destino de esta noche se decide entre <b>$nombre1</b> y <b>$nombre2</b>.",
            "Los focos caen sobre la arena: <b>$nombre1</b> contra <b>$nombre2</b>.",
            "Se alza el clamor. <b>$nombre1</b> y <b>$nombre2</b> dan un paso al frente.",
            "La batalla que todos esperaban ya est&aacute; aqu&iacute;: <b>$nombre1</b> vs <b>$nombre2</b>.",
            "El suelo tiembla al primer cruce de miradas entre <b>$nombre1</b> y <b>$nombre2</b>.",
            "La noche recordar&aacute; este duelo entre <b>$nombre1</b> y <b>$nombre2</b>.",
            "<b>$nombre1</b> y <b>$nombre2</b> entran en escena como si fueran leyenda."
        ),
        'brutal' => array(
            "No hay tregua: <b>$nombre1</b> y <b>$nombre2</b> vienen a hacerse da&ntilde;o.",
            "La arena pide sangre y <b>$nombre1</b> acepta el reto de <b>$nombre2</b>.",
            "El combate promete ser salvaje: <b>$nombre1</b> contra <b>$nombre2</b>.",
            "<b>$nombre1</b> cruje los nudillos; <b>$nombre2</b> ense&ntilde;a los dientes.",
            "Ninguno busca puntos: <b>$nombre1</b> y <b>$nombre2</b> buscan derribar."
        ),
        'ironico' => array(
            "Otra noche tranquila... hasta que <b>$nombre1</b> se cruza con <b>$nombre2</b>.",
            "Plan de la noche: cero drama. Realidad: <b>$nombre1</b> contra <b>$nombre2</b>.",
            "Parec&iacute;a un encuentro cordial, pero <b>$nombre1</b> y <b>$nombre2</b> discrepan con los pu&ntilde;os.",
            "Nadie esperaba violencia. Nadie, excepto <b>$nombre1</b> y <b>$nombre2</b>.",
            "La diplomacia fracasa en tiempo r&eacute;cord entre <b>$nombre1</b> y <b>$nombre2</b>."
        )
    ),
    'umbral' => array(
        'serio' => array(
            "Las tierras de la Umbra se estremecen por el enfrentamiento entre <b>$nombre1</b> y <b>$nombre2</b>.",
            "&iexcl;La b&uacute;squeda espiritual de <b>$nombre1</b> choca con <b>$nombre2</b>!",
            "Los esp&iacute;ritus observan en silencio a <b>$nombre1</b> y <b>$nombre2</b>.",
            "La bruma de la Umbra se abre para el choque de voluntades entre <b>$nombre1</b> y <b>$nombre2</b>.",
            "Entre ecos y sombras, <b>$nombre1</b> y <b>$nombre2</b> miden su esencia."
        ),
        'epico' => array(
            "La Umbra ruge: <b>$nombre1</b> y <b>$nombre2</b> desatan su poder espiritual.",
            "Bajo cielos imposibles, <b>$nombre1</b> y <b>$nombre2</b> libran un duelo ancestral.",
            "La energ&iacute;a espiritual se concentra. <b>$nombre1</b> contra <b>$nombre2</b>, sin marcha atr&aacute;s.",
            "Los senderos umbrales se curvan ante el combate entre <b>$nombre1</b> y <b>$nombre2</b>."
        ),
        'brutal' => array(
            "La esencia se desgarrar&aacute; en este choque entre <b>$nombre1</b> y <b>$nombre2</b>.",
            "En la Umbra no hay misericordia: <b>$nombre1</b> frente a <b>$nombre2</b>.",
            "La tempestad espiritual cae sobre <b>$nombre1</b> y <b>$nombre2</b>."
        ),
        'ironico' => array(
            "Meditar era una opci&oacute;n, pero <b>$nombre1</b> y <b>$nombre2</b> eligieron pelear.",
            "Paz interior: pospuesta. <b>$nombre1</b> y <b>$nombre2</b> entran en combate.",
            "La Umbra iba a estar tranquila... hasta que aparecieron <b>$nombre1</b> y <b>$nombre2</b>."
        )
    )
);

$simMode = ($tipoCombate === 'umbral') ? 'umbral' : 'normal';
$tonePool = $simIntroMessages[$simMode] ?? array();
$toneKeys = array_keys($tonePool);
$simIntroTone = 'serio';
if ($simNarrativeTone !== 'random' && isset($tonePool[$simNarrativeTone])) {
    $simIntroTone = $simNarrativeTone;
} elseif (!empty($toneKeys)) {
    if ($simRelationContext === 'enemy' && isset($tonePool['brutal'])) {
        $simIntroTone = (rand(1, 100) <= 75) ? 'brutal' : $toneKeys[array_rand($toneKeys)];
    } elseif ($simRelationContext === 'rival' && isset($tonePool['epico'])) {
        $simIntroTone = (rand(1, 100) <= 60) ? 'epico' : $toneKeys[array_rand($toneKeys)];
    } else {
        $simIntroTone = $toneKeys[array_rand($toneKeys)];
    }
}
$quote = $tonePool[$simIntroTone] ?? array();

$p1Prepared = function_exists('sim_apply_gender_aware_phrase')
    ? sim_apply_gender_aware_phrase('preparado', $gender1)
    : 'preparade';
$p2Prepared = function_exists('sim_apply_gender_aware_phrase')
    ? sim_apply_gender_aware_phrase('preparado', $gender2)
    : 'preparade';
$p1Tense = function_exists('sim_apply_gender_aware_phrase')
    ? sim_apply_gender_aware_phrase('tenso', $gender1)
    : 'tense';
$p2Tense = function_exists('sim_apply_gender_aware_phrase')
    ? sim_apply_gender_aware_phrase('tenso', $gender2)
    : 'tense';

$quote[] = "<b>$nombre1</b> llega <b>$p1Prepared</b>; <b>$nombre2</b> tambi&eacute;n est&aacute; <b>$p2Prepared</b> para pelear.";
$quote[] = "<b>$nombre1</b> observa a <b>$nombre2</b>, $p1Tense y sin apartar la guardia.";
$quote[] = "<b>$nombre2</b> parece $p2Tense, pero no retrocede ni un paso frente a <b>$nombre1</b>.";

$simRelationIntroMessages = array(
    'enemy' => array(
        "No es una pelea cualquiera: entre <b>$nombre1</b> y <b>$nombre2</b> hay odio viejo y cuentas pendientes.",
        "La rivalidad se qued&oacute; corta. <b>$nombre1</b> y <b>$nombre2</b> vienen a destruirse.",
        "No hay lugar para la piedad cuando <b>$nombre1</b> y <b>$nombre2</b> se cruzan."
    ),
    'rival' => array(
        "La competencia entre <b>$nombre1</b> y <b>$nombre2</b> vuelve a la arena.",
        "Dos rivales, una sola victoria: <b>$nombre1</b> contra <b>$nombre2</b>.",
        "Se conocen demasiado bien. <b>$nombre1</b> y <b>$nombre2</b> no se van a regalar nada."
    ),
    'ally' => array(
        "<b>$nombre1</b> y <b>$nombre2</b> lucharon en el mismo bando, pero hoy se miden sin concesiones.",
        "La confianza se aparca por un momento: <b>$nombre1</b> y <b>$nombre2</b> prueban su fuerza.",
        "Compa&ntilde;eros fuera de la arena, rivales dentro: <b>$nombre1</b> y <b>$nombre2</b>."
    ),
    'romance' => array(
        "Entre <b>$nombre1</b> y <b>$nombre2</b> hay un v&iacute;nculo que hace este combate a&uacute;n m&aacute;s tenso.",
        "No es f&aacute;cil alzar la guardia contra alguien cercano: <b>$nombre1</b> y <b>$nombre2</b> lo intentan.",
        "La historia compartida entre <b>$nombre1</b> y <b>$nombre2</b> pesa sobre cada movimiento."
    ),
    'family' => array(
        "La sangre no evita el choque: <b>$nombre1</b> y <b>$nombre2</b> entran en combate.",
        "El lazo familiar no frena esta batalla entre <b>$nombre1</b> y <b>$nombre2</b>.",
        "Cuando la familia choca, cada golpe duele el doble: <b>$nombre1</b> vs <b>$nombre2</b>."
    ),
    'hierarchy' => array(
        "La jerarqu&iacute;a se pone a prueba entre <b>$nombre1</b> y <b>$nombre2</b>.",
        "La relaci&oacute;n de mando y aprendizaje queda a un lado: hoy s&oacute;lo hay combate.",
        "<b>$nombre1</b> y <b>$nombre2</b> redefinen su posici&oacute;n a golpes."
    )
);

if (isset($simRelationIntroMessages[$simRelationContext])) {
    $quote = array_merge($simRelationIntroMessages[$simRelationContext], $quote);
}
if ($simRelationType !== '') {
    $safeRelationType = htmlspecialchars((string)$simRelationType, ENT_QUOTES, 'UTF-8');
    $quote[] = "Relaci&oacute;n conocida entre combatientes: <b>$safeRelationType</b>.";
}

if ($usarRegen !== 'no') {
    $quote[] = "La regeneraci&oacute;n est&aacute; activa. <b>$nombre1</b> y <b>$nombre2</b> saben que cada herida cuenta.";
}
if ($maxturn >= 15) {
    $quote[] = "Se espera un combate largo. La resistencia puede decidir el destino de <b>$nombre1</b> y <b>$nombre2</b>.";
}
if ($maxturn <= 3) {
    $quote[] = "No habr&aacute; mucho margen: <b>$nombre1</b> y <b>$nombre2</b> deber&aacute;n golpear r&aacute;pido.";
}

if (empty($quote)) {
    $quote = array("&iexcl;<b>$nombre1</b> y <b>$nombre2</b> se preparan para luchar!");
}

$echo = array_rand($quote);

$simCrowdMessages = array(
    "El p&uacute;blico ruge en las gradas.",
    "Se oyen gritos pidiendo un golpe definitivo.",
    "La arena vibra con cada intercambio.",
    "Un murmullo tenso recorre la sala.",
    "Nadie aparta la vista del combate.",
    "Los espectadores se levantan de sus asientos."
);

$simLateTurnMessages = array(
    "El desgaste empieza a notarse en ambos combatientes.",
    "Cada movimiento cuesta m&aacute;s que en el turno anterior.",
    "La respiraci&oacute;n se vuelve pesada; el ritmo no afloja.",
    "La fatiga aprieta, pero ninguno quiere ceder.",
    "Las guardias bajan por momentos y cualquier error puede costar caro."
);

?>
