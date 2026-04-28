<?php
/**
 * Org chart schema setup (2026-04-27)
 *
 * Uso:
 *   php app/tools/org_chart_schema_20260427.php
 *   php app/tools/org_chart_schema_20260427.php --apply
 *   php app/tools/org_chart_schema_20260427.php --apply --seed-justicia-metalica
 */

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(org_chart_schema_cli_main($argv));
}

function org_chart_schema_cli_main(array $argv): int
{
    $apply = in_array('--apply', $argv, true);
    $seedJusticiaMetalica = in_array('--seed-justicia-metalica', $argv, true);

    org_chart_schema_log('=== Org chart schema setup start ===');
    org_chart_schema_log($apply ? '[modo] apply' : '[modo] dry-run');

    $env = org_chart_schema_load_env();
    if (!is_array($env)) {
        org_chart_schema_log('[warn] config.env no disponible');
        return $apply ? 1 : 0;
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    $link = @mysqli_connect(
        (string)$env['MYSQL_HOST'],
        (string)$env['MYSQL_USER'],
        (string)$env['MYSQL_PWD'],
        (string)$env['MYSQL_BDD']
    );

    if (!$link) {
        org_chart_schema_log('[warn] no se pudo conectar a la BDD: ' . (mysqli_connect_error() ?: 'conexion no disponible'));
        return $apply ? 1 : 0;
    }

    mysqli_set_charset($link, 'utf8mb4');

    $result = org_chart_schema_apply($link, $apply);
    foreach ($result['messages'] as $message) {
        org_chart_schema_log($message);
    }

    if ($apply && $seedJusticiaMetalica && empty($result['errors'])) {
        $seedResult = org_chart_schema_seed_justicia_metalica($link);
        foreach ($seedResult['messages'] as $message) {
            org_chart_schema_log($message);
        }
        $result['errors'] = array_merge($result['errors'], $seedResult['errors']);
    }

    org_chart_schema_log('=== Org chart schema setup end ===');
    return empty($result['errors']) ? 0 : 1;
}

function org_chart_schema_statements(): array
{
    return [
        'dim_organization_departments' => "
            CREATE TABLE IF NOT EXISTS `dim_organization_departments` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `organization_id` int(10) unsigned NOT NULL,
              `parent_department_id` int(10) unsigned DEFAULT NULL,
              `pretty_id` varchar(190) DEFAULT NULL,
              `name` varchar(150) NOT NULL,
              `department_type` enum('board','department','unit','delegation','special','territory','other') NOT NULL DEFAULT 'department',
              `hierarchy_level` tinyint(3) unsigned NOT NULL DEFAULT 1,
              `color` varchar(7) DEFAULT '#e2e8f0',
              `description` text DEFAULT NULL,
              `sort_order` int(11) NOT NULL DEFAULT 0,
              `is_active` tinyint(1) NOT NULL DEFAULT 1,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_dim_org_dept_pretty` (`organization_id`,`pretty_id`),
              KEY `idx_dim_org_dept_org_parent` (`organization_id`,`parent_department_id`,`sort_order`),
              KEY `idx_dim_org_dept_level` (`organization_id`,`hierarchy_level`,`sort_order`),
              CONSTRAINT `fk_dim_org_dept_org` FOREIGN KEY (`organization_id`) REFERENCES `dim_organizations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk_dim_org_dept_parent` FOREIGN KEY (`parent_department_id`) REFERENCES `dim_organization_departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
              CONSTRAINT `chk_dim_org_dept_level` CHECK (`hierarchy_level` between 0 and 99)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'bridge_characters_org' => "
            CREATE TABLE IF NOT EXISTS `bridge_characters_org` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `character_id` int(10) unsigned DEFAULT NULL,
              `organization_id` int(10) unsigned NOT NULL,
              `department_id` int(10) unsigned DEFAULT NULL,
              `parent_bridge_id` int(10) unsigned DEFAULT NULL,
              `hierarchy_level` tinyint(3) unsigned NOT NULL DEFAULT 1,
              `position_name` varchar(150) NOT NULL DEFAULT '',
              `position_code` varchar(120) DEFAULT NULL,
              `scope_label` varchar(150) DEFAULT NULL,
              `responsibility` text DEFAULT NULL,
              `is_head` tinyint(1) NOT NULL DEFAULT 0,
              `is_primary` tinyint(1) NOT NULL DEFAULT 1,
              `is_active` tinyint(1) NOT NULL DEFAULT 1,
              `sort_order` int(11) NOT NULL DEFAULT 0,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_bridge_char_org_position` (`character_id`,`organization_id`,`department_id`,`position_name`),
              KEY `idx_bco2_org_level` (`organization_id`,`hierarchy_level`,`sort_order`),
              KEY `idx_bco2_department` (`department_id`,`sort_order`),
              KEY `idx_bco2_parent` (`parent_bridge_id`),
              KEY `idx_bco2_character` (`character_id`,`is_active`),
              CONSTRAINT `fk_bco2_character` FOREIGN KEY (`character_id`) REFERENCES `fact_characters` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
              CONSTRAINT `fk_bco2_org` FOREIGN KEY (`organization_id`) REFERENCES `dim_organizations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk_bco2_department` FOREIGN KEY (`department_id`) REFERENCES `dim_organization_departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
              CONSTRAINT `fk_bco2_parent` FOREIGN KEY (`parent_bridge_id`) REFERENCES `bridge_characters_org` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
              CONSTRAINT `chk_bco2_level` CHECK (`hierarchy_level` between 0 and 99)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'bridge_characters_org_nullable_character' => "
            ALTER TABLE `bridge_characters_org`
              MODIFY `character_id` int(10) unsigned DEFAULT NULL
        ",
    ];
}

function org_chart_schema_apply(mysqli $link, bool $apply): array
{
    $messages = [];
    $errors = [];

    foreach (org_chart_schema_statements() as $label => $sql) {
        $compactSql = preg_replace('/\s+/', ' ', trim($sql)) ?? trim($sql);
        if (!$apply) {
            $messages[] = '[READY] ' . $label;
            $messages[] = '        ' . $compactSql;
            continue;
        }

        if (mysqli_query($link, $sql)) {
            $messages[] = '[OK] ' . $label;
            continue;
        }

        $error = mysqli_error($link);
        $messages[] = '[ERR] ' . $label . ' :: ' . $error;
        $errors[] = [
            'label' => $label,
            'sql' => $compactSql,
            'error' => $error,
        ];
        break;
    }

    return ['messages' => $messages, 'errors' => $errors];
}

function org_chart_schema_seed_justicia_metalica(mysqli $link): array
{
    $messages = [];
    $errors = [];
    $orgId = org_chart_schema_fetch_id($link, "SELECT id FROM dim_organizations WHERE pretty_id = 'justicia-metalica' LIMIT 1");
    if ($orgId <= 0) {
        $errors[] = ['label' => 'seed_justicia_metalica', 'error' => 'Justicia Metalica no encontrada'];
        $messages[] = '[seed] Justicia Metalica no encontrada';
        return ['messages' => $messages, 'errors' => $errors];
    }

    org_chart_schema_deactivate_org_roles($link, $orgId);

    $departments = [
        ['central', null, 'Direccion Central', 'board', 0, '#c7d2fe', 'Punto de mando federal.', 10],
        ['federal-departments', 'central', 'Departamentos Federales', 'board', 1, '#e2e8f0', 'Bloque funcional de la organizacion.', 20],
        ['territorial-delegations', 'central', 'Delegaciones Territoriales', 'board', 1, '#ffedd5', 'Bloque territorial norteamericano.', 30],
        ['training', 'federal-departments', 'Ensenanza y Adiestramiento', 'department', 2, '#bfdbfe', 'Adoctrinamiento, formacion y recuperacion de cachorros.', 10],
        ['info-security', 'federal-departments', 'Informacion y Seguridad', 'department', 2, '#c4b5fd', 'Espionaje, vigilancia y seguridad interna.', 20],
        ['research-development', 'federal-departments', 'Investigacion y Desarrollo', 'department', 2, '#bbf7d0', 'Laboratorios, Proyecto Icaro y biotecnologia espiritual.', 30],
        ['covert-support', 'federal-departments', 'Apoyo Encubierto', 'department', 2, '#fbcfe8', 'Operaciones negras y redes discretas.', 40],
        ['judgment-diplomacy', 'federal-departments', 'Juicios y Diplomacia', 'department', 2, '#fed7aa', 'Legalidad interna, pactos y embajadas.', 50],
        ['assault-defense', 'federal-departments', 'Asalto y Defensa', 'department', 2, '#bae6fd', 'Respuesta militar y defensa.', 60],
        ['recovery-team', 'training', 'Equipo de Recuperacion', 'unit', 3, '#dbeafe', 'Cachorros, Icaros fugados y sujetos inestables.', 10],
        ['trainer-team', 'training', 'Equipo de Adiestradores', 'unit', 3, '#dbeafe', 'Formacion aplicada y protocolos de campo.', 20],
        ['new-applications', 'research-development', 'Nuevas Aplicaciones', 'unit', 3, '#dcfce7', 'Aplicacion practica de proyectos experimentales.', 10],
        ['cleaning-unit', 'covert-support', 'Unidad de Limpieza', 'unit', 3, '#fce7f3', 'Borrado de rastros, pruebas y contencion del Velo.', 10],
        ['ambassadors', 'judgment-diplomacy', 'Embajadores', 'unit', 3, '#ffedd5', 'Representacion formal entre clanes.', 10],
        ['external-relations', 'judgment-diplomacy', 'Relaciones Externas', 'unit', 3, '#ffedd5', 'Contactos internacionales y pactos externos.', 20],
        ['jailers', 'assault-defense', 'Carceleros', 'unit', 3, '#e0f2fe', 'Custodia de prisioneros y activos peligrosos.', 10],
        ['external-assets', 'central', 'Supervision de Activos Externos', 'special', 2, '#fde68a', 'Unidad especial de proyectos y oportunidades politicas externas.', 40],
        ['east-delegation', 'territorial-delegations', 'Delegacion Este', 'delegation', 2, '#fed7aa', 'Maine, New Hampshire, Vermont, Massachusetts, New York, Rhode Island, Connecticut, New Jersey, Pennsylvania, Delaware, Maryland, West Virginia, Virginia, North Carolina, South Carolina, Georgia, Florida, Ohio, Kentucky, Tennessee, Alabama.', 10],
        ['west-delegation', 'territorial-delegations', 'Delegacion Oeste', 'delegation', 2, '#fed7aa', 'Washington, Oregon, California, Nevada, Idaho, Utah, Arizona, Montana.', 20],
        ['north-delegation', 'territorial-delegations', 'Delegacion Norte', 'delegation', 2, '#fed7aa', 'North Dakota, South Dakota, Wyoming, Colorado, Nebraska, Kansas, Missouri, Iowa, Minnesota, Wisconsin, Illinois, Indiana, Michigan, Alaska.', 30],
        ['south-delegation', 'territorial-delegations', 'Delegacion Sur', 'delegation', 2, '#fed7aa', 'Mississippi, Louisiana, Arkansas, Oklahoma, Texas, New Mexico, Hawaii.', 40],
    ];

    $departmentIds = [];
    foreach ($departments as $dept) {
        [$pretty, $parentPretty, $name, $type, $level, $color, $description, $sort] = $dept;
        $parentId = $parentPretty ? (int)($departmentIds[$parentPretty] ?? 0) : 0;
        $departmentIds[$pretty] = org_chart_schema_upsert_department(
            $link,
            $orgId,
            $parentId > 0 ? $parentId : null,
            $pretty,
            $name,
            $type,
            $level,
            $color,
            $description,
            $sort
        );
    }

    $roles = [
        ['Angel Fairbanks', 'central', null, 0, 'Direccion central', 'CEO', null, 'Punto de mando de la Justicia Metalica.', 1, 10],
        ['Henry Beret', 'training', 'Angel Fairbanks', 1, 'Director de Ensenanza y Adiestramiento', 'DIR-TRAINING', null, 'Adoctrinamiento, formacion y recuperacion de cachorros.', 1, 10],
        ['Mateus Grimshaw', 'info-security', 'Angel Fairbanks', 1, 'Director de Informacion y Seguridad', 'DIR-INFOSEC', null, 'Espionaje, vigilancia y manipulacion de informacion.', 1, 20],
        ['Owen Shipnewcard', 'research-development', 'Angel Fairbanks', 1, 'Director de Investigacion y Desarrollo', 'DIR-RD', null, 'Laboratorios, biotecnologia espiritual y proyectos experimentales.', 1, 30],
        ['Gothalie Voileur', 'covert-support', 'Angel Fairbanks', 1, 'Directora de Apoyo Encubierto', 'DIR-COVERT', null, 'Operaciones negras, limpieza politica y redes discretas.', 1, 40],
        ['Voice Stefani', 'judgment-diplomacy', 'Angel Fairbanks', 1, 'Directora de Juicios y Diplomacia', 'DIR-JUDGMENT', null, 'Legalidad interna, coordinacion del departamento y diplomacia formal.', 1, 50],
        ['Lenard Stefani', 'judgment-diplomacy', 'Voice Stefani', 2, 'Adjunto de Juicios y Diplomacia', 'ADJ-JUDGMENT', null, 'Apoyo directo a la direccion del departamento, gestion interna y soporte juridico.', 0, 51],
        ['Borschtey Jox', 'assault-defense', 'Angel Fairbanks', 1, 'Director de Asalto y Defensa', 'DIR-ASSAULT', null, 'Respuesta militar, defensa de tumulos y despliegues armados.', 1, 60],
        ['Anthony Castleden', 'recovery-team', 'Henry Beret', 3, 'Responsable del Equipo de Recuperacion', 'LEAD-RECOVERY', null, 'Recuperacion de cachorros, Icaros fugados y sujetos inestables.', 1, 10],
        ['James Ivory', 'trainer-team', 'Henry Beret', 3, 'Responsable del Equipo de Adiestradores', 'LEAD-TRAINERS', null, 'Formacion aplicada y protocolos de campo.', 1, 20],
        ['Sophie Kult', 'new-applications', 'Owen Shipnewcard', 3, 'Responsable de Nuevas Aplicaciones', 'LEAD-NEWAPP', null, 'Aplicacion practica de tecnologia, dones, sueros y derivados.', 1, 10],
        ['Yoshiyuki Kotetsu', 'cleaning-unit', 'Gothalie Voileur', 3, 'Responsable de la Unidad de Limpieza', 'LEAD-CLEANING', null, 'Neutralizacion de rastros, pruebas y crisis del Velo.', 1, 10],
        ['William Pettyknox', 'ambassadors', 'Voice Stefani', 3, 'Responsable de Embajadores', 'LEAD-AMBASSADORS', null, 'Representacion formal y negociaciones entre clanes.', 1, 10],
        ['Marco Trovianni', 'external-relations', 'Voice Stefani', 3, 'Responsable de Relaciones Externas', 'LEAD-EXTREL', null, 'Contactos internacionales y pactos con estructuras extranjeras.', 1, 20],
        ['Adam Suschkind', 'jailers', 'Borschtey Jox', 3, 'Responsable de los Carceleros', 'LEAD-JAILERS', null, 'Prisioneros, activos peligrosos y sujetos de alto riesgo.', 1, 10],
        ['Terrence McCoil', 'external-assets', 'Angel Fairbanks', 2, 'Supervisor de Activos Externos', 'SUP-EXTASSETS', null, 'Operador politico ligado a Icaro, proyectos externos y Penasco Blanco.', 1, 40],
        ['David Chase', 'east-delegation', 'Angel Fairbanks', 2, 'Delegado Este', 'DEL-EAST', 'Este', 'Jefatura territorial Este.', 1, 10],
        ['Parice Schreiter', 'west-delegation', 'Angel Fairbanks', 2, 'Delegada Oeste', 'DEL-WEST', 'Oeste', 'Jefatura territorial Oeste.', 1, 20],
        ['Marian Coldwater', 'north-delegation', 'Angel Fairbanks', 2, 'Delegada Norte', 'DEL-NORTH', 'Norte', 'Jefatura territorial Norte.', 1, 30],
        ['Marcus Hawke', 'south-delegation', 'Angel Fairbanks', 2, 'Delegado Sur', 'DEL-SOUTH', 'Sur', 'Jefatura territorial Sur.', 1, 40],
    ];

    $roleIdsByCharacter = [];
    foreach ($roles as $role) {
        [$characterName, $departmentPretty, $parentCharacterName, $level, $positionName, $positionCode, $scopeLabel, $responsibility, $isHead, $sort] = $role;
        $characterId = org_chart_schema_fetch_character_id($link, $characterName);
        if ($characterId <= 0) {
            $messages[] = '[seed warn] personaje no encontrado: ' . $characterName;
            continue;
        }

        $departmentId = (int)($departmentIds[$departmentPretty] ?? 0);
        if ($departmentId <= 0) {
            $messages[] = '[seed warn] departamento no encontrado: ' . $departmentPretty;
            continue;
        }

        $parentBridgeId = $parentCharacterName ? (int)($roleIdsByCharacter[$parentCharacterName] ?? 0) : 0;
        $roleId = org_chart_schema_upsert_role(
            $link,
            $characterId,
            $orgId,
            $departmentId,
            $parentBridgeId > 0 ? $parentBridgeId : null,
            $level,
            $positionName,
            $positionCode,
            $scopeLabel,
            $responsibility,
            (int)$isHead,
            $sort
        );

        $roleIdsByCharacter[$characterName] = $roleId;
    }

    org_chart_schema_deactivate_role_position($link, $orgId, 'Voice Stefani', 'Codirectora de Juicios y Diplomacia');
    org_chart_schema_deactivate_role_position($link, $orgId, 'Lenard Stefani', 'Codirector de Juicios y Diplomacia');

    $messages[] = '[seed] Justicia Metalica preparada';
    return ['messages' => $messages, 'errors' => $errors];
}

function org_chart_schema_upsert_department(mysqli $link, int $orgId, ?int $parentId, string $pretty, string $name, string $type, int $level, string $color, string $description, int $sort): int
{
    $existing = org_chart_schema_fetch_id_prepared(
        $link,
        'SELECT id FROM dim_organization_departments WHERE organization_id = ? AND pretty_id = ? LIMIT 1',
        'is',
        [$orgId, $pretty]
    );

    if ($existing > 0) {
        $stmt = $link->prepare("
            UPDATE dim_organization_departments
            SET parent_department_id = ?, name = ?, department_type = ?, hierarchy_level = ?, color = ?, description = ?, sort_order = ?, is_active = 1
            WHERE id = ?
        ");
        $stmt->bind_param('ississii', $parentId, $name, $type, $level, $color, $description, $sort, $existing);
        $stmt->execute();
        $stmt->close();
        return $existing;
    }

    $stmt = $link->prepare("
        INSERT INTO dim_organization_departments
            (organization_id, parent_department_id, pretty_id, name, department_type, hierarchy_level, color, description, sort_order, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->bind_param('iisssissi', $orgId, $parentId, $pretty, $name, $type, $level, $color, $description, $sort);
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return $id;
}

function org_chart_schema_upsert_role(mysqli $link, int $characterId, int $orgId, int $departmentId, ?int $parentBridgeId, int $level, string $positionName, string $positionCode, ?string $scopeLabel, string $responsibility, int $isHead, int $sort): int
{
    $existing = org_chart_schema_fetch_id_prepared(
        $link,
        'SELECT id FROM bridge_characters_org WHERE character_id = ? AND organization_id = ? AND department_id = ? AND position_name = ? LIMIT 1',
        'iiis',
        [$characterId, $orgId, $departmentId, $positionName]
    );

    if ($existing > 0) {
        $stmt = $link->prepare("
            UPDATE bridge_characters_org
            SET parent_bridge_id = ?, hierarchy_level = ?, position_code = ?, scope_label = ?, responsibility = ?, is_head = ?, is_primary = 1, is_active = 1, sort_order = ?
            WHERE id = ?
        ");
        $stmt->bind_param('iisssiii', $parentBridgeId, $level, $positionCode, $scopeLabel, $responsibility, $isHead, $sort, $existing);
        $stmt->execute();
        $stmt->close();
        return $existing;
    }

    $stmt = $link->prepare("
        INSERT INTO bridge_characters_org
            (character_id, organization_id, department_id, parent_bridge_id, hierarchy_level, position_name, position_code, scope_label, responsibility, is_head, is_primary, is_active, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, ?)
    ");
    $stmt->bind_param('iiiiissssii', $characterId, $orgId, $departmentId, $parentBridgeId, $level, $positionName, $positionCode, $scopeLabel, $responsibility, $isHead, $sort);
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return $id;
}

function org_chart_schema_deactivate_role_position(mysqli $link, int $orgId, string $characterName, string $positionName): void
{
    $characterId = org_chart_schema_fetch_character_id($link, $characterName);
    if ($characterId <= 0) {
        return;
    }

    $stmt = $link->prepare("
        UPDATE bridge_characters_org
        SET is_active = 0, is_head = 0
        WHERE organization_id = ?
          AND character_id = ?
          AND position_name = ?
    ");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('iis', $orgId, $characterId, $positionName);
    $stmt->execute();
    $stmt->close();
}

function org_chart_schema_deactivate_org_roles(mysqli $link, int $orgId): void
{
    $stmt = $link->prepare("
        UPDATE bridge_characters_org
        SET is_active = 0, is_head = 0
        WHERE organization_id = ?
    ");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $orgId);
    $stmt->execute();
    $stmt->close();
}

function org_chart_schema_fetch_character_id(mysqli $link, string $name): int
{
    return org_chart_schema_fetch_id_prepared(
        $link,
        'SELECT id FROM fact_characters WHERE name = ? LIMIT 1',
        's',
        [$name]
    );
}

function org_chart_schema_fetch_id(mysqli $link, string $sql): int
{
    $rs = mysqli_query($link, $sql);
    if (!$rs) {
        return 0;
    }
    $row = mysqli_fetch_assoc($rs);
    return (int)($row['id'] ?? 0);
}

function org_chart_schema_fetch_id_prepared(mysqli $link, string $sql, string $types, array $params): int
{
    $stmt = $link->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rs = $stmt->get_result();
    $row = $rs ? $rs->fetch_assoc() : null;
    $stmt->close();
    return (int)($row['id'] ?? 0);
}

function org_chart_schema_load_env(): ?array
{
    $candidates = [
        dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'config.env',
        dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config.env',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.env',
    ];

    foreach ($candidates as $candidate) {
        if (!is_file($candidate)) {
            continue;
        }
        $parsed = parse_ini_file($candidate);
        if (!is_array($parsed)) {
            continue;
        }
        foreach (['MYSQL_HOST', 'MYSQL_USER', 'MYSQL_PWD', 'MYSQL_BDD'] as $key) {
            if (!array_key_exists($key, $parsed) || (string)$parsed[$key] === '') {
                continue 2;
            }
        }
        return $parsed;
    }

    return null;
}

function org_chart_schema_log(string $text): void
{
    echo $text . PHP_EOL;
}
