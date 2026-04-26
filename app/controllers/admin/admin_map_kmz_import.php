<?php
/*
============================================================
  admin_map_kmz_import.php - Importador simple de KMZ/KML a
  dim_maps, fact_map_pois y fact_map_areas
============================================================ */

include_once(__DIR__ . '/../../helpers/admin_ajax.php');
include_once(__DIR__ . '/../../helpers/pretty.php');
include_once(__DIR__ . '/../../partials/admin/admin_styles.php');

if (!hg_admin_require_db($link)) {
    return;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!function_exists('hg_kmz_import_h')) {
    function hg_kmz_import_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('hg_kmz_import_slug')) {
    function hg_kmz_import_slug(string $value): string
    {
        if (function_exists('slugify_pretty_id')) {
            return slugify_pretty_id($value);
        }
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim((string)$value, '-');
    }
}

if (!function_exists('hg_kmz_import_table_has_column')) {
    function hg_kmz_import_table_has_column(mysqli $link, string $table, string $column): bool
    {
        $table = trim($table);
        $column = trim($column);
        if ($table === '' || $column === '') {
            return false;
        }

        if (function_exists('hg_table_has_column')) {
            return hg_table_has_column($link, $table, $column);
        }

        $stmt = $link->prepare(
            "SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?"
        );
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return ((int)$count > 0);
    }
}

if (!function_exists('hg_kmz_import_fetch_maps')) {
    function hg_kmz_import_fetch_maps(mysqli $link): array
    {
        $rows = [];
        $sql = "SELECT id, name, slug, center_lat, center_lng, default_zoom, min_zoom, max_zoom,
                       bounds_sw_lat, bounds_sw_lng, bounds_ne_lat, bounds_ne_lng, default_tile
                FROM dim_maps
                ORDER BY name ASC, id ASC";
        $res = $link->query($sql);
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }
}

if (!function_exists('hg_kmz_import_fetch_categories')) {
    function hg_kmz_import_fetch_categories(mysqli $link): array
    {
        $rows = [];
        $sql = "SELECT id, name, slug, color_hex, sort_order
                FROM dim_map_categories
                ORDER BY sort_order ASC, name ASC, id ASC";
        $res = $link->query($sql);
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }
}

if (!function_exists('hg_kmz_import_default_category_id')) {
    function hg_kmz_import_default_category_id(array $categories): int
    {
        $fallback = 0;
        foreach ($categories as $category) {
            $id = (int)($category['id'] ?? 0);
            if ($fallback <= 0 && $id > 0) {
                $fallback = $id;
            }
            $nameSlug = hg_kmz_import_slug((string)($category['name'] ?? ''));
            $slug = hg_kmz_import_slug((string)($category['slug'] ?? ''));
            if (in_array($nameSlug, ['otros', 'otro'], true) || in_array($slug, ['otros', 'otro'], true)) {
                return $id;
            }
        }
        return $fallback;
    }
}

if (!function_exists('hg_kmz_import_try_resolve_path')) {
    function hg_kmz_import_try_resolve_path(string $projectRoot, string $inputPath): array
    {
        $inputPath = trim($inputPath);
        if ($inputPath === '') {
            return ['ok' => false, 'error' => 'Indica un archivo fuente .kmz o .kml.'];
        }

        $projectRootReal = realpath($projectRoot);
        if ($projectRootReal === false) {
            return ['ok' => false, 'error' => 'No se pudo resolver la raíz del proyecto.'];
        }

        $candidate = $inputPath;
        if (!preg_match('~^(?:[A-Za-z]:[\\\\/]|[\\\\/]{2}|[\\\\/])~', $candidate)) {
            $candidate = $projectRootReal . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $inputPath), DIRECTORY_SEPARATOR);
        }

        $real = realpath($candidate);
        if ($real === false || !is_file($real)) {
            return ['ok' => false, 'error' => 'No se encontró el archivo indicado en la raíz del proyecto.'];
        }

        $rootPrefix = rtrim($projectRootReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $realPrefix = rtrim($real, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strpos($realPrefix, $rootPrefix) !== 0 && $real !== $projectRootReal) {
            return ['ok' => false, 'error' => 'Por seguridad, solo se permiten archivos dentro de la raíz del proyecto.'];
        }

        $relative = ltrim(str_replace($projectRootReal, '', $real), DIRECTORY_SEPARATOR);
        return [
            'ok' => true,
            'absolute_path' => $real,
            'relative_path' => $relative !== '' ? str_replace(DIRECTORY_SEPARATOR, '/', $relative) : basename($real),
            'extension' => strtolower((string)pathinfo($real, PATHINFO_EXTENSION)),
        ];
    }
}

if (!function_exists('hg_kmz_import_shell_extract')) {
    function hg_kmz_import_shell_extract(string $archivePath, string $entryName): ?array
    {
        if (!function_exists('shell_exec')) {
            return null;
        }

        $archiveArg = escapeshellarg($archivePath);
        $entryArg = escapeshellarg($entryName);
        $commands = [];
        if (DIRECTORY_SEPARATOR === '\\') {
            $commands[] = 'tar -xOf ' . $archiveArg . ' ' . $entryArg;
        } else {
            $commands[] = 'unzip -p ' . $archiveArg . ' ' . $entryArg . ' 2>/dev/null';
            $commands[] = 'tar -xOf ' . $archiveArg . ' ' . $entryArg . ' 2>/dev/null';
        }

        foreach ($commands as $command) {
            $output = @shell_exec($command);
            if (is_string($output) && trim($output) !== '') {
                $tool = strtok($command, ' ');
                return [
                    'contents' => $output,
                    'method' => 'shell:' . $tool,
                ];
            }
        }

        return null;
    }
}

if (!function_exists('hg_kmz_import_read_source_xml')) {
    function hg_kmz_import_read_source_xml(string $absolutePath, string $extension): array
    {
        if ($extension === 'kml') {
            $contents = @file_get_contents($absolutePath);
            if (!is_string($contents) || trim($contents) === '') {
                return ['ok' => false, 'error' => 'No se pudo leer el fichero KML.'];
            }
            return [
                'ok' => true,
                'xml' => $contents,
                'entry_name' => basename($absolutePath),
                'extract_method' => 'direct',
                'source_type' => 'kml',
            ];
        }

        if ($extension !== 'kmz') {
            return ['ok' => false, 'error' => 'El archivo debe ser .kmz o .kml.'];
        }

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($absolutePath) === true) {
                $entryName = 'doc.kml';
                $xml = $zip->getFromName($entryName);
                if (!is_string($xml) || trim($xml) === '') {
                    $entryName = '';
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $stat = $zip->statIndex($i);
                        $name = (string)($stat['name'] ?? '');
                        if ($name !== '' && preg_match('/\.kml$/i', $name)) {
                            $entryName = $name;
                            $xml = $zip->getFromName($name);
                            break;
                        }
                    }
                }
                $zip->close();
                if (is_string($xml) && trim($xml) !== '') {
                    return [
                        'ok' => true,
                        'xml' => $xml,
                        'entry_name' => $entryName !== '' ? $entryName : 'doc.kml',
                        'extract_method' => 'ziparchive',
                        'source_type' => 'kmz',
                    ];
                }
            }
        }

        $entryCandidates = ['doc.kml'];
        foreach ($entryCandidates as $entryName) {
            $shell = hg_kmz_import_shell_extract($absolutePath, $entryName);
            if ($shell !== null) {
                return [
                    'ok' => true,
                    'xml' => $shell['contents'],
                    'entry_name' => $entryName,
                    'extract_method' => $shell['method'],
                    'source_type' => 'kmz',
                ];
            }
        }

        return [
            'ok' => false,
            'error' => 'No se pudo abrir el KMZ. Este servidor necesita ZipArchive o una herramienta de sistema como unzip/tar. Como alternativa, deja también el KML extraído en la raíz e impórtalo directamente.',
        ];
    }
}

if (!function_exists('hg_kmz_import_xpath_text')) {
    function hg_kmz_import_xpath_text(DOMXPath $xpath, ?DOMNode $contextNode, string $expression): string
    {
        $nodes = $xpath->query($expression, $contextNode);
        if (!($nodes instanceof DOMNodeList) || $nodes->length === 0) {
            return '';
        }
        return trim((string)$nodes->item(0)->textContent);
    }
}

if (!function_exists('hg_kmz_import_parse_kml_color')) {
    function hg_kmz_import_parse_kml_color(string $value, string $fallbackHex = '#3388ff', float $fallbackOpacity = 0.35): array
    {
        $value = strtolower(trim($value));
        if (!preg_match('/^[0-9a-f]{8}$/', $value)) {
            return ['hex' => $fallbackHex, 'opacity' => $fallbackOpacity];
        }

        $alpha = hexdec(substr($value, 0, 2));
        $blue = substr($value, 2, 2);
        $green = substr($value, 4, 2);
        $red = substr($value, 6, 2);

        return [
            'hex' => '#' . strtoupper($red . $green . $blue),
            'opacity' => max(0, min(1, round($alpha / 255, 3))),
        ];
    }
}

if (!function_exists('hg_kmz_import_extract_style')) {
    function hg_kmz_import_extract_style(DOMXPath $xpath, DOMElement $styleNode): array
    {
        $line = hg_kmz_import_parse_kml_color(
            hg_kmz_import_xpath_text($xpath, $styleNode, "./*[local-name()='LineStyle']/*[local-name()='color']"),
            '#27ae60',
            1.0
        );
        $poly = hg_kmz_import_parse_kml_color(
            hg_kmz_import_xpath_text($xpath, $styleNode, "./*[local-name()='PolyStyle']/*[local-name()='color']"),
            '#3388ff',
            0.35
        );

        $width = (float)hg_kmz_import_xpath_text($xpath, $styleNode, "./*[local-name()='LineStyle']/*[local-name()='width']");
        if ($width <= 0) {
            $width = 2.0;
        }

        return [
            'stroke_hex' => $line['hex'],
            'stroke_opacity' => $line['opacity'],
            'fill_hex' => $poly['hex'],
            'fill_opacity' => $poly['opacity'],
            'stroke_weight' => max(1, (int)round($width)),
        ];
    }
}

if (!function_exists('hg_kmz_import_parse_styles')) {
    function hg_kmz_import_parse_styles(DOMXPath $xpath): array
    {
        $styles = [];
        $styleMaps = [];

        $styleNodes = $xpath->query("//*[local-name()='Style']");
        if ($styleNodes instanceof DOMNodeList) {
            foreach ($styleNodes as $styleNode) {
                if (!($styleNode instanceof DOMElement)) {
                    continue;
                }
                $id = trim($styleNode->getAttribute('id'));
                if ($id === '') {
                    continue;
                }
                $styles['#' . $id] = hg_kmz_import_extract_style($xpath, $styleNode);
            }
        }

        $styleMapNodes = $xpath->query("//*[local-name()='StyleMap']");
        if ($styleMapNodes instanceof DOMNodeList) {
            foreach ($styleMapNodes as $styleMapNode) {
                if (!($styleMapNode instanceof DOMElement)) {
                    continue;
                }
                $id = trim($styleMapNode->getAttribute('id'));
                if ($id === '') {
                    continue;
                }
                $normal = '';
                $highlight = '';
                $pairs = $xpath->query("./*[local-name()='Pair']", $styleMapNode);
                if ($pairs instanceof DOMNodeList) {
                    foreach ($pairs as $pair) {
                        if (!($pair instanceof DOMElement)) {
                            continue;
                        }
                        $key = strtolower(hg_kmz_import_xpath_text($xpath, $pair, "./*[local-name()='key']"));
                        $styleUrl = trim(hg_kmz_import_xpath_text($xpath, $pair, "./*[local-name()='styleUrl']"));
                        if ($key === 'normal' && $styleUrl !== '') {
                            $normal = $styleUrl;
                        } elseif ($key === 'highlight' && $styleUrl !== '') {
                            $highlight = $styleUrl;
                        }
                    }
                }
                $styleMaps['#' . $id] = $normal !== '' ? $normal : $highlight;
            }
        }

        return [$styles, $styleMaps];
    }
}

if (!function_exists('hg_kmz_import_resolve_style')) {
    function hg_kmz_import_resolve_style(string $styleUrl, array $styles, array $styleMaps): array
    {
        $fallback = [
            'stroke_hex' => '#27ae60',
            'stroke_opacity' => 1.0,
            'fill_hex' => '#3388ff',
            'fill_opacity' => 0.35,
            'stroke_weight' => 2,
        ];

        $styleUrl = trim($styleUrl);
        if ($styleUrl === '') {
            return $fallback;
        }

        $seen = [];
        while (isset($styleMaps[$styleUrl]) && !isset($seen[$styleUrl])) {
            $seen[$styleUrl] = true;
            $styleUrl = (string)$styleMaps[$styleUrl];
        }

        if (!isset($styles[$styleUrl]) || !is_array($styles[$styleUrl])) {
            return $fallback;
        }

        return array_merge($fallback, $styles[$styleUrl]);
    }
}

if (!function_exists('hg_kmz_import_parse_coordinates')) {
    function hg_kmz_import_parse_coordinates(string $coordinatesText): array
    {
        $points = [];
        $chunks = preg_split('/\s+/', trim($coordinatesText));
        if (!is_array($chunks)) {
            return $points;
        }

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            $parts = array_map('trim', explode(',', $chunk));
            if (count($parts) < 2) {
                continue;
            }
            $lng = is_numeric($parts[0]) ? (float)$parts[0] : null;
            $lat = is_numeric($parts[1]) ? (float)$parts[1] : null;
            if ($lat === null || $lng === null) {
                continue;
            }
            $points[] = [$lng, $lat];
        }

        return $points;
    }
}

if (!function_exists('hg_kmz_import_close_ring')) {
    function hg_kmz_import_close_ring(array $ring): array
    {
        if (count($ring) < 3) {
            return $ring;
        }
        $first = $ring[0];
        $last = $ring[count($ring) - 1];
        if ($first[0] !== $last[0] || $first[1] !== $last[1]) {
            $ring[] = $first;
        }
        return $ring;
    }
}

if (!function_exists('hg_kmz_import_extract_point')) {
    function hg_kmz_import_extract_point(DOMXPath $xpath, DOMElement $placemark): ?array
    {
        $coordinatesText = hg_kmz_import_xpath_text($xpath, $placemark, ".//*[local-name()='Point']/*[local-name()='coordinates']");
        if ($coordinatesText === '') {
            return null;
        }

        $coords = hg_kmz_import_parse_coordinates($coordinatesText);
        if (empty($coords)) {
            return null;
        }

        return [
            'longitude' => (float)$coords[0][0],
            'latitude' => (float)$coords[0][1],
        ];
    }
}

if (!function_exists('hg_kmz_import_extract_polygon')) {
    function hg_kmz_import_extract_polygon(DOMXPath $xpath, DOMElement $placemark, array $style): ?array
    {
        $polygonNode = $xpath->query(".//*[local-name()='Polygon']", $placemark);
        if (!($polygonNode instanceof DOMNodeList) || $polygonNode->length === 0) {
            return null;
        }
        $polygon = $polygonNode->item(0);
        if (!($polygon instanceof DOMElement)) {
            return null;
        }

        $rings = [];

        $outerText = hg_kmz_import_xpath_text($xpath, $polygon, "./*[local-name()='outerBoundaryIs']/*[local-name()='LinearRing']/*[local-name()='coordinates']");
        $outerRing = hg_kmz_import_parse_coordinates($outerText);
        if (count($outerRing) < 3) {
            return null;
        }
        $rings[] = hg_kmz_import_close_ring($outerRing);

        $innerNodes = $xpath->query("./*[local-name()='innerBoundaryIs']/*[local-name()='LinearRing']/*[local-name()='coordinates']", $polygon);
        if ($innerNodes instanceof DOMNodeList) {
            foreach ($innerNodes as $innerNode) {
                $innerRing = hg_kmz_import_parse_coordinates((string)$innerNode->textContent);
                if (count($innerRing) >= 3) {
                    $rings[] = hg_kmz_import_close_ring($innerRing);
                }
            }
        }

        return [
            'color_hex' => (string)$style['fill_hex'],
            'fill_opacity' => (float)$style['fill_opacity'],
            'stroke_color' => (string)$style['stroke_hex'],
            'stroke_weight' => (int)$style['stroke_weight'],
            'geometry' => [
                'type' => 'Feature',
                'properties' => [
                    'fillColor' => (string)$style['fill_hex'],
                    'fillOpacity' => (float)$style['fill_opacity'],
                    'color' => (string)$style['stroke_hex'],
                    'weight' => (int)$style['stroke_weight'],
                ],
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => $rings,
                ],
            ],
        ];
    }
}

if (!function_exists('hg_kmz_import_guess_zoom')) {
    function hg_kmz_import_guess_zoom(array $bounds): int
    {
        $latSpan = abs((float)($bounds['max_lat'] ?? 0) - (float)($bounds['min_lat'] ?? 0));
        $lngSpan = abs((float)($bounds['max_lng'] ?? 0) - (float)($bounds['min_lng'] ?? 0));
        $span = max($latSpan, $lngSpan);

        if ($span > 140) return 2;
        if ($span > 70) return 3;
        if ($span > 35) return 4;
        if ($span > 18) return 5;
        if ($span > 9)  return 6;
        if ($span > 4)  return 7;
        if ($span > 2)  return 8;
        if ($span > 1)  return 9;
        if ($span > 0.5) return 10;
        return 11;
    }
}

if (!function_exists('hg_kmz_import_parse_document')) {
    function hg_kmz_import_parse_document(string $xml, array $sourceMeta): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        if (!$loaded) {
            return ['ok' => false, 'error' => 'No se pudo parsear el XML/KML del archivo fuente.'];
        }

        $xpath = new DOMXPath($dom);
        $documentNodeList = $xpath->query("//*[local-name()='Document']");
        $documentNode = ($documentNodeList instanceof DOMNodeList && $documentNodeList->length > 0)
            ? $documentNodeList->item(0)
            : $dom->documentElement;
        if (!($documentNode instanceof DOMNode)) {
            return ['ok' => false, 'error' => 'El KML no contiene un nodo Document válido.'];
        }

        [$styles, $styleMaps] = hg_kmz_import_parse_styles($xpath);

        $folders = [];
        $folderIndex = 0;
        $bounds = [
            'min_lat' => null,
            'max_lat' => null,
            'min_lng' => null,
            'max_lng' => null,
        ];
        $totals = ['points' => 0, 'areas' => 0, 'unsupported' => 0];

        $consumePlacemark = static function (DOMElement $placemark, array &$folderSummary, array &$bounds, array &$totals, DOMXPath $xpath, array $styles, array $styleMaps): void {
            $name = trim(hg_kmz_import_xpath_text($xpath, $placemark, "./*[local-name()='name']"));
            $description = trim(hg_kmz_import_xpath_text($xpath, $placemark, "./*[local-name()='description']"));
            $styleUrl = trim(hg_kmz_import_xpath_text($xpath, $placemark, "./*[local-name()='styleUrl']"));
            $resolvedStyle = hg_kmz_import_resolve_style($styleUrl, $styles, $styleMaps);

            $point = hg_kmz_import_extract_point($xpath, $placemark);
            if ($point !== null) {
                $folderSummary['points'][] = [
                    'name' => $name !== '' ? $name : '(Sin nombre)',
                    'description' => $description,
                    'latitude' => (float)$point['latitude'],
                    'longitude' => (float)$point['longitude'],
                    'style_url' => $styleUrl,
                ];
                $bounds['min_lat'] = $bounds['min_lat'] === null ? $point['latitude'] : min($bounds['min_lat'], $point['latitude']);
                $bounds['max_lat'] = $bounds['max_lat'] === null ? $point['latitude'] : max($bounds['max_lat'], $point['latitude']);
                $bounds['min_lng'] = $bounds['min_lng'] === null ? $point['longitude'] : min($bounds['min_lng'], $point['longitude']);
                $bounds['max_lng'] = $bounds['max_lng'] === null ? $point['longitude'] : max($bounds['max_lng'], $point['longitude']);
                $totals['points']++;
                return;
            }

            $polygon = hg_kmz_import_extract_polygon($xpath, $placemark, $resolvedStyle);
            if ($polygon !== null) {
                $folderSummary['areas'][] = [
                    'name' => $name !== '' ? $name : '(Sin nombre)',
                    'description' => $description,
                    'color_hex' => (string)$polygon['color_hex'],
                    'fill_opacity' => (float)$polygon['fill_opacity'],
                    'stroke_color' => (string)$polygon['stroke_color'],
                    'stroke_weight' => (int)$polygon['stroke_weight'],
                    'geometry' => $polygon['geometry'],
                ];
                foreach ((array)($polygon['geometry']['geometry']['coordinates'] ?? []) as $ring) {
                    foreach ((array)$ring as $pair) {
                        if (!is_array($pair) || count($pair) < 2) {
                            continue;
                        }
                        $lng = (float)$pair[0];
                        $lat = (float)$pair[1];
                        $bounds['min_lat'] = $bounds['min_lat'] === null ? $lat : min($bounds['min_lat'], $lat);
                        $bounds['max_lat'] = $bounds['max_lat'] === null ? $lat : max($bounds['max_lat'], $lat);
                        $bounds['min_lng'] = $bounds['min_lng'] === null ? $lng : min($bounds['min_lng'], $lng);
                        $bounds['max_lng'] = $bounds['max_lng'] === null ? $lng : max($bounds['max_lng'], $lng);
                    }
                }
                $totals['areas']++;
                return;
            }

            $folderSummary['unsupported'][] = [
                'name' => $name !== '' ? $name : '(Sin nombre)',
                'style_url' => $styleUrl,
            ];
            $totals['unsupported']++;
        };

        $folderNodes = $xpath->query("./*[local-name()='Folder']", $documentNode);
        if ($folderNodes instanceof DOMNodeList) {
            foreach ($folderNodes as $folderNode) {
                if (!($folderNode instanceof DOMElement)) {
                    continue;
                }
                $folderName = trim(hg_kmz_import_xpath_text($xpath, $folderNode, "./*[local-name()='name']"));
                if ($folderName === '') {
                    $folderName = 'Sin carpeta';
                }
                $folderSummary = [
                    'key' => sha1($folderIndex . '|' . $folderName),
                    'name' => $folderName,
                    'points' => [],
                    'areas' => [],
                    'unsupported' => [],
                ];
                $placemarks = $xpath->query("./*[local-name()='Placemark']", $folderNode);
                if ($placemarks instanceof DOMNodeList) {
                    foreach ($placemarks as $placemark) {
                        if ($placemark instanceof DOMElement) {
                            $consumePlacemark($placemark, $folderSummary, $bounds, $totals, $xpath, $styles, $styleMaps);
                        }
                    }
                }
                if (!empty($folderSummary['points']) || !empty($folderSummary['areas']) || !empty($folderSummary['unsupported'])) {
                    $folders[] = $folderSummary;
                    $folderIndex++;
                }
            }
        }

        $rootPlacemarks = $xpath->query("./*[local-name()='Placemark']", $documentNode);
        if ($rootPlacemarks instanceof DOMNodeList && $rootPlacemarks->length > 0) {
            $folderSummary = [
                'key' => sha1($folderIndex . '|General'),
                'name' => 'General',
                'points' => [],
                'areas' => [],
                'unsupported' => [],
            ];
            foreach ($rootPlacemarks as $placemark) {
                if ($placemark instanceof DOMElement) {
                    $consumePlacemark($placemark, $folderSummary, $bounds, $totals, $xpath, $styles, $styleMaps);
                }
            }
            if (!empty($folderSummary['points']) || !empty($folderSummary['areas']) || !empty($folderSummary['unsupported'])) {
                $folders[] = $folderSummary;
            }
        }

        if ($bounds['min_lat'] === null || $bounds['min_lng'] === null) {
            $bounds['min_lat'] = 0.0;
            $bounds['max_lat'] = 0.0;
            $bounds['min_lng'] = 0.0;
            $bounds['max_lng'] = 0.0;
        }
        $bounds['center_lat'] = (($bounds['min_lat'] + $bounds['max_lat']) / 2);
        $bounds['center_lng'] = (($bounds['min_lng'] + $bounds['max_lng']) / 2);
        $bounds['default_zoom'] = hg_kmz_import_guess_zoom($bounds);

        return [
            'ok' => true,
            'document_name' => hg_kmz_import_xpath_text($xpath, $documentNode, "./*[local-name()='name']"),
            'document_description' => hg_kmz_import_xpath_text($xpath, $documentNode, "./*[local-name()='description']"),
            'folders' => $folders,
            'bounds' => $bounds,
            'points_total' => (int)$totals['points'],
            'areas_total' => (int)$totals['areas'],
            'unsupported_total' => (int)$totals['unsupported'],
            'source_type' => (string)$sourceMeta['source_type'],
            'entry_name' => (string)$sourceMeta['entry_name'],
            'extract_method' => (string)$sourceMeta['extract_method'],
        ];
    }
}

if (!function_exists('hg_kmz_import_parse_source')) {
    function hg_kmz_import_parse_source(string $projectRoot, string $inputPath): array
    {
        $resolved = hg_kmz_import_try_resolve_path($projectRoot, $inputPath);
        if (!$resolved['ok']) {
            return $resolved;
        }

        $sourceMeta = hg_kmz_import_read_source_xml((string)$resolved['absolute_path'], (string)$resolved['extension']);
        if (!$sourceMeta['ok']) {
            return $sourceMeta;
        }

        $parsed = hg_kmz_import_parse_document((string)$sourceMeta['xml'], $sourceMeta);
        if (!$parsed['ok']) {
            return $parsed;
        }

        $parsed['absolute_path'] = $resolved['absolute_path'];
        $parsed['relative_path'] = $resolved['relative_path'];
        $parsed['extension'] = $resolved['extension'];
        return $parsed;
    }
}

if (!function_exists('hg_kmz_import_default_category_for_folder')) {
    function hg_kmz_import_default_category_for_folder(array $folder, array $categories, int $fallbackId): int
    {
        $folderSlug = hg_kmz_import_slug((string)($folder['name'] ?? ''));
        foreach ($categories as $category) {
            $id = (int)($category['id'] ?? 0);
            $nameSlug = hg_kmz_import_slug((string)($category['name'] ?? ''));
            $slug = hg_kmz_import_slug((string)($category['slug'] ?? ''));
            if ($folderSlug !== '' && ($folderSlug === $nameSlug || $folderSlug === $slug)) {
                return $id;
            }
        }
        return $fallbackId;
    }
}

if (!function_exists('hg_kmz_import_map_name_by_id')) {
    function hg_kmz_import_map_name_by_id(array $maps, int $id): string
    {
        foreach ($maps as $map) {
            if ((int)($map['id'] ?? 0) === $id) {
                return (string)($map['name'] ?? '');
            }
        }
        return '';
    }
}

if (!function_exists('hg_kmz_import_create_map')) {
    function hg_kmz_import_create_map(mysqli $link, array $payload): int
    {
        $name = trim((string)($payload['name'] ?? ''));
        $slug = trim((string)($payload['slug'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('El nombre del nuevo mapa es obligatorio.');
        }
        if ($slug === '') {
            $slug = hg_kmz_import_slug($name);
        }
        if ($slug === '') {
            throw new RuntimeException('No se pudo generar el slug del nuevo mapa.');
        }

        $check = $link->prepare("SELECT id FROM dim_maps WHERE name = ? OR slug = ? LIMIT 1");
        $check->bind_param('ss', $name, $slug);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        $check->close();
        if ($exists) {
            throw new RuntimeException('Ya existe un mapa con ese nombre o slug. Selecciónalo en la lista o cambia los datos del nuevo mapa.');
        }

        $centerLat = (float)($payload['center_lat'] ?? 0);
        $centerLng = (float)($payload['center_lng'] ?? 0);
        $defaultZoom = (int)($payload['default_zoom'] ?? 8);
        $minZoom = (int)($payload['min_zoom'] ?? 3);
        $maxZoom = (int)($payload['max_zoom'] ?? 17);
        $swLat = (float)($payload['bounds_sw_lat'] ?? 0);
        $swLng = (float)($payload['bounds_sw_lng'] ?? 0);
        $neLat = (float)($payload['bounds_ne_lat'] ?? 0);
        $neLng = (float)($payload['bounds_ne_lng'] ?? 0);
        $defaultTile = trim((string)($payload['default_tile'] ?? 'carto-dark'));
        if ($defaultTile === '') {
            $defaultTile = 'carto-dark';
        }

        $stmt = $link->prepare(
            "INSERT INTO dim_maps
             (name, slug, center_lat, center_lng, default_zoom, min_zoom, max_zoom,
              bounds_sw_lat, bounds_sw_lng, bounds_ne_lat, bounds_ne_lng, default_tile)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param(
            'ssddiiidddds',
            $name,
            $slug,
            $centerLat,
            $centerLng,
            $defaultZoom,
            $minZoom,
            $maxZoom,
            $swLat,
            $swLng,
            $neLat,
            $neLng,
            $defaultTile
        );
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();

        if (function_exists('hg_update_pretty_id_if_exists')) {
            hg_update_pretty_id_if_exists($link, 'dim_maps', $id, $name);
        }

        return $id;
    }
}

if (!function_exists('hg_kmz_import_poi_exists')) {
    function hg_kmz_import_poi_exists(mysqli $link, int $mapId, string $name, float $lat, float $lng): bool
    {
        $stmt = $link->prepare(
            "SELECT id
             FROM fact_map_pois
             WHERE map_id = ?
               AND name = ?
               AND ABS(latitude - ?) < 0.000001
               AND ABS(longitude - ?) < 0.000001
             LIMIT 1"
        );
        $stmt->bind_param('isdd', $mapId, $name, $lat, $lng);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc() !== null;
        $stmt->close();
        return $exists;
    }
}

if (!function_exists('hg_kmz_import_area_exists')) {
    function hg_kmz_import_area_exists(mysqli $link, int $mapId, string $name, string $geometryJson): bool
    {
        $stmt = $link->prepare(
            "SELECT id
             FROM fact_map_areas
             WHERE map_id = ?
               AND name = ?
               AND geometry = ?
             LIMIT 1"
        );
        $stmt->bind_param('iss', $mapId, $name, $geometryJson);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc() !== null;
        $stmt->close();
        return $exists;
    }
}

if (!function_exists('hg_kmz_import_insert_poi')) {
    function hg_kmz_import_insert_poi(mysqli $link, array $poi): int
    {
        $stmt = $link->prepare(
            "INSERT INTO fact_map_pois
             (name, map_id, category_id, description, thumbnail, latitude, longitude)
             VALUES (?,?,?,?,?,?,?)"
        );
        $thumbnail = '';
        $stmt->bind_param(
            'siissdd',
            $poi['name'],
            $poi['map_id'],
            $poi['category_id'],
            $poi['description'],
            $thumbnail,
            $poi['latitude'],
            $poi['longitude']
        );
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();

        if (function_exists('hg_update_pretty_id_if_exists')) {
            hg_update_pretty_id_if_exists($link, 'fact_map_pois', $id, (string)$poi['name']);
        }

        return $id;
    }
}

if (!function_exists('hg_kmz_import_insert_area')) {
    function hg_kmz_import_insert_area(mysqli $link, array $area, bool $hasCategoryId): int
    {
        if ($hasCategoryId) {
            $stmt = $link->prepare(
                "INSERT INTO fact_map_areas
                 (map_id, category_id, name, description, color_hex, geometry)
                 VALUES (?,?,?,?,?,?)"
            );
            $stmt->bind_param(
                'iissss',
                $area['map_id'],
                $area['category_id'],
                $area['name'],
                $area['description'],
                $area['color_hex'],
                $area['geometry_json']
            );
        } else {
            $stmt = $link->prepare(
                "INSERT INTO fact_map_areas
                 (map_id, name, description, color_hex, geometry)
                 VALUES (?,?,?,?,?)"
            );
            $stmt->bind_param(
                'issss',
                $area['map_id'],
                $area['name'],
                $area['description'],
                $area['color_hex'],
                $area['geometry_json']
            );
        }

        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();

        if (function_exists('hg_update_pretty_id_if_exists')) {
            hg_update_pretty_id_if_exists($link, 'fact_map_areas', $id, (string)$area['name']);
        }

        return $id;
    }
}

$ADMIN_CSRF_SESSION_KEY = 'csrf_admin_map_kmz_import';
$ADMIN_CSRF_TOKEN = function_exists('hg_admin_ensure_csrf_token')
    ? hg_admin_ensure_csrf_token($ADMIN_CSRF_SESSION_KEY)
    : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('hg_admin_csrf_valid')) {
    $payload = function_exists('hg_admin_read_json_payload') ? hg_admin_read_json_payload() : [];
    $csrf = function_exists('hg_admin_extract_csrf_token')
        ? hg_admin_extract_csrf_token($payload)
        : trim((string)($_POST['csrf'] ?? ''));
    if (!hg_admin_csrf_valid($csrf, $ADMIN_CSRF_SESSION_KEY)) {
        http_response_code(403);
        echo "<div class='bioSeccion'><h2>Importar KMZ/KML a Mapas</h2><p>Token CSRF inválido.</p></div>";
        return;
    }
}

$pageTitle2 = 'Importar KMZ/KML a Mapas';
$projectRoot = dirname(__DIR__, 3);
$defaultSourceFile = 'Mapa Ad Arcanum.kmz';
$sourceFile = trim((string)($_POST['source_file'] ?? $_GET['source_file'] ?? $defaultSourceFile));
$maps = hg_kmz_import_fetch_maps($link);
$categories = hg_kmz_import_fetch_categories($link);
$defaultCategoryId = hg_kmz_import_default_category_id($categories);
$parseResult = hg_kmz_import_parse_source($projectRoot, $sourceFile);
$flash = [];
$importReport = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['crud_action'] ?? '') === 'import_kmz') {
    if (empty($categories)) {
        $flash[] = ['type' => 'error', 'msg' => 'No hay categorías disponibles en dim_map_categories. Crea al menos una antes de importar POIs.'];
    } elseif (!$parseResult['ok']) {
        $flash[] = ['type' => 'error', 'msg' => (string)($parseResult['error'] ?? 'No se pudo analizar el fichero fuente.')];
    } else {
        $mapChoice = trim((string)($_POST['target_map_choice'] ?? '__new__'));
        $skipExisting = isset($_POST['skip_existing']) ? (string)$_POST['skip_existing'] === '1' : true;
        $hasAreaCategoryId = hg_kmz_import_table_has_column($link, 'fact_map_areas', 'category_id');

        try {
            $link->begin_transaction();

            if ($mapChoice === '__new__') {
                $newMapPayload = [
                    'name' => (string)($_POST['new_map_name'] ?? ($parseResult['document_name'] ?? '')),
                    'slug' => (string)($_POST['new_map_slug'] ?? ''),
                    'center_lat' => (float)($_POST['new_map_center_lat'] ?? ($parseResult['bounds']['center_lat'] ?? 0)),
                    'center_lng' => (float)($_POST['new_map_center_lng'] ?? ($parseResult['bounds']['center_lng'] ?? 0)),
                    'default_zoom' => (int)($_POST['new_map_default_zoom'] ?? ($parseResult['bounds']['default_zoom'] ?? 8)),
                    'min_zoom' => (int)($_POST['new_map_min_zoom'] ?? 3),
                    'max_zoom' => (int)($_POST['new_map_max_zoom'] ?? 17),
                    'bounds_sw_lat' => (float)($_POST['new_map_bounds_sw_lat'] ?? ($parseResult['bounds']['min_lat'] ?? 0)),
                    'bounds_sw_lng' => (float)($_POST['new_map_bounds_sw_lng'] ?? ($parseResult['bounds']['min_lng'] ?? 0)),
                    'bounds_ne_lat' => (float)($_POST['new_map_bounds_ne_lat'] ?? ($parseResult['bounds']['max_lat'] ?? 0)),
                    'bounds_ne_lng' => (float)($_POST['new_map_bounds_ne_lng'] ?? ($parseResult['bounds']['max_lng'] ?? 0)),
                    'default_tile' => (string)($_POST['new_map_default_tile'] ?? 'carto-dark'),
                ];
                $targetMapId = hg_kmz_import_create_map($link, $newMapPayload);
                $targetMapName = (string)$newMapPayload['name'];
                $mapCreated = true;
            } else {
                $targetMapId = (int)$mapChoice;
                if ($targetMapId <= 0) {
                    throw new RuntimeException('Selecciona un mapa de destino válido o elige crear uno nuevo.');
                }
                $targetMapName = hg_kmz_import_map_name_by_id($maps, $targetMapId);
                if ($targetMapName === '') {
                    throw new RuntimeException('El mapa de destino seleccionado ya no existe.');
                }
                $mapCreated = false;
            }

            $folderMappings = (array)($_POST['folder_category'] ?? []);
            $reportFolders = [];
            $poisInserted = 0;
            $poisSkipped = 0;
            $areasInserted = 0;
            $areasSkipped = 0;

            foreach ((array)$parseResult['folders'] as $folder) {
                $folderKey = (string)($folder['key'] ?? '');
                $folderName = (string)($folder['name'] ?? 'Sin carpeta');
                $selectedCategoryId = isset($folderMappings[$folderKey]) ? (int)$folderMappings[$folderKey] : 0;
                if ($selectedCategoryId <= 0) {
                    $selectedCategoryId = hg_kmz_import_default_category_for_folder($folder, $categories, $defaultCategoryId);
                }
                if ($selectedCategoryId <= 0) {
                    throw new RuntimeException('La carpeta "' . $folderName . '" necesita una categoría válida para importar sus POIs.');
                }

                $folderPoisInserted = 0;
                $folderPoisSkipped = 0;
                $folderAreasInserted = 0;
                $folderAreasSkipped = 0;

                foreach ((array)($folder['points'] ?? []) as $poi) {
                    $poiName = trim((string)($poi['name'] ?? ''));
                    if ($poiName === '') {
                        $poiName = '(Sin nombre)';
                    }
                    $lat = (float)($poi['latitude'] ?? 0);
                    $lng = (float)($poi['longitude'] ?? 0);
                    if ($skipExisting && hg_kmz_import_poi_exists($link, $targetMapId, $poiName, $lat, $lng)) {
                        $folderPoisSkipped++;
                        $poisSkipped++;
                        continue;
                    }

                    hg_kmz_import_insert_poi($link, [
                        'name' => $poiName,
                        'map_id' => $targetMapId,
                        'category_id' => $selectedCategoryId,
                        'description' => (string)($poi['description'] ?? ''),
                        'latitude' => $lat,
                        'longitude' => $lng,
                    ]);
                    $folderPoisInserted++;
                    $poisInserted++;
                }

                foreach ((array)($folder['areas'] ?? []) as $area) {
                    $areaName = trim((string)($area['name'] ?? ''));
                    if ($areaName === '') {
                        $areaName = '(Sin nombre)';
                    }
                    $geometryJson = json_encode((array)($area['geometry'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if ($geometryJson === false || $geometryJson === '[]') {
                        throw new RuntimeException('No se pudo serializar el área "' . $areaName . '".');
                    }
                    if ($skipExisting && hg_kmz_import_area_exists($link, $targetMapId, $areaName, $geometryJson)) {
                        $folderAreasSkipped++;
                        $areasSkipped++;
                        continue;
                    }

                    hg_kmz_import_insert_area($link, [
                        'map_id' => $targetMapId,
                        'category_id' => $selectedCategoryId,
                        'name' => $areaName,
                        'description' => (string)($area['description'] ?? ''),
                        'color_hex' => (string)($area['color_hex'] ?? '#3388ff'),
                        'geometry_json' => $geometryJson,
                    ], $hasAreaCategoryId);
                    $folderAreasInserted++;
                    $areasInserted++;
                }

                $reportFolders[] = [
                    'name' => $folderName,
                    'pois_inserted' => $folderPoisInserted,
                    'pois_skipped' => $folderPoisSkipped,
                    'areas_inserted' => $folderAreasInserted,
                    'areas_skipped' => $folderAreasSkipped,
                    'unsupported' => count((array)($folder['unsupported'] ?? [])),
                ];
            }

            $link->commit();

            $importReport = [
                'map_id' => $targetMapId,
                'map_name' => $targetMapName,
                'map_created' => $mapCreated,
                'pois_inserted' => $poisInserted,
                'pois_skipped' => $poisSkipped,
                'areas_inserted' => $areasInserted,
                'areas_skipped' => $areasSkipped,
                'unsupported' => (int)($parseResult['unsupported_total'] ?? 0),
                'folders' => $reportFolders,
            ];
            $flash[] = [
                'type' => 'ok',
                'msg' => 'Importación completada en el mapa "' . $targetMapName . '" (ID ' . $targetMapId . ').',
            ];
            $maps = hg_kmz_import_fetch_maps($link);
        } catch (Throwable $e) {
            $link->rollback();
            $flash[] = ['type' => 'error', 'msg' => $e->getMessage()];
        }
    }
}

$selectedMapChoice = (string)($_POST['target_map_choice'] ?? '__new__');
$defaultNewMapName = trim((string)($_POST['new_map_name'] ?? ($parseResult['document_name'] ?? '')));
$defaultNewMapSlug = trim((string)($_POST['new_map_slug'] ?? hg_kmz_import_slug($defaultNewMapName)));
$defaultCenterLat = (string)($_POST['new_map_center_lat'] ?? ($parseResult['bounds']['center_lat'] ?? '0'));
$defaultCenterLng = (string)($_POST['new_map_center_lng'] ?? ($parseResult['bounds']['center_lng'] ?? '0'));
$defaultZoom = (string)($_POST['new_map_default_zoom'] ?? ($parseResult['bounds']['default_zoom'] ?? '8'));
$defaultMinZoom = (string)($_POST['new_map_min_zoom'] ?? '3');
$defaultMaxZoom = (string)($_POST['new_map_max_zoom'] ?? '17');
$defaultTile = (string)($_POST['new_map_default_tile'] ?? 'carto-dark');
$defaultSwLat = (string)($_POST['new_map_bounds_sw_lat'] ?? ($parseResult['bounds']['min_lat'] ?? '0'));
$defaultSwLng = (string)($_POST['new_map_bounds_sw_lng'] ?? ($parseResult['bounds']['min_lng'] ?? '0'));
$defaultNeLat = (string)($_POST['new_map_bounds_ne_lat'] ?? ($parseResult['bounds']['max_lat'] ?? '0'));
$defaultNeLng = (string)($_POST['new_map_bounds_ne_lng'] ?? ($parseResult['bounds']['max_lng'] ?? '0'));
$skipExistingChecked = isset($_POST['skip_existing']) ? (string)$_POST['skip_existing'] === '1' : true;
?>

<div class="bioSeccion">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <div>
            <h2>Importar KMZ/KML a mapas</h2>
            <p class="adm-color-muted">Lee un fichero exportado de Google My Maps y crea POIs y áreas en <code>fact_map_pois</code> y <code>fact_map_areas</code>.</p>
        </div>
        <div class="adm-flex-8-center">
            <a class="btn" href="/talim?s=admin_pois">Gestionar Mapas</a>
            <a class="btn" href="/talim">Panel</a>
        </div>
    </div>

    <?php foreach ($flash as $message): ?>
        <div style="margin-top:10px;padding:10px 12px;border-radius:8px;border:1px solid <?= $message['type'] === 'ok' ? '#2c8a61' : '#b31111' ?>;background:<?= $message['type'] === 'ok' ? '#082b1d' : '#3a0d0d' ?>;color:#f5f7ff;">
            <?= hg_kmz_import_h($message['msg']) ?>
        </div>
    <?php endforeach; ?>

    <form method="post" class="adm-mt-10">
        <input type="hidden" name="csrf" value="<?= hg_kmz_import_h($ADMIN_CSRF_TOKEN) ?>">
        <input type="hidden" name="crud_action" value="import_kmz">

        <div class="adm-grid-1-2" style="gap:12px;">
            <label>Archivo fuente</label>
            <input class="inp" type="text" name="source_file" value="<?= hg_kmz_import_h($sourceFile) ?>" placeholder="Mapa Ad Arcanum.kmz">

            <label>Mapa destino</label>
            <select class="select" name="target_map_choice" id="kmz_target_map_choice">
                <option value="__new__" <?= $selectedMapChoice === '__new__' ? 'selected' : '' ?>>Crear mapa nuevo</option>
                <?php foreach ($maps as $map): ?>
                    <?php $mapId = (int)($map['id'] ?? 0); ?>
                    <option value="<?= $mapId ?>" <?= $selectedMapChoice === (string)$mapId ? 'selected' : '' ?>>
                        <?= hg_kmz_import_h((string)($map['name'] ?? 'Mapa #' . $mapId)) ?> (#<?= $mapId ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="kmz_new_map_fields" class="adm-mt-10" style="<?= $selectedMapChoice === '__new__' ? '' : 'display:none;' ?>">
            <fieldset class="bioSeccion" style="margin:0;">
                <legend>&nbsp;Nuevo mapa&nbsp;</legend>
                <div class="adm-grid-1-2" style="gap:12px;">
                    <label>Nombre</label>
                    <input class="inp" type="text" name="new_map_name" id="kmz_new_map_name" value="<?= hg_kmz_import_h($defaultNewMapName) ?>" maxlength="120">

                    <label>Slug</label>
                    <input class="inp" type="text" name="new_map_slug" id="kmz_new_map_slug" value="<?= hg_kmz_import_h($defaultNewMapSlug) ?>" maxlength="120">

                    <label>Centro lat</label>
                    <input class="inp" type="text" name="new_map_center_lat" value="<?= hg_kmz_import_h($defaultCenterLat) ?>">

                    <label>Centro lng</label>
                    <input class="inp" type="text" name="new_map_center_lng" value="<?= hg_kmz_import_h($defaultCenterLng) ?>">

                    <label>Zoom por defecto</label>
                    <input class="inp" type="number" name="new_map_default_zoom" value="<?= hg_kmz_import_h($defaultZoom) ?>" min="1" max="20">

                    <label>Zoom mínimo</label>
                    <input class="inp" type="number" name="new_map_min_zoom" value="<?= hg_kmz_import_h($defaultMinZoom) ?>" min="1" max="20">

                    <label>Zoom máximo</label>
                    <input class="inp" type="number" name="new_map_max_zoom" value="<?= hg_kmz_import_h($defaultMaxZoom) ?>" min="1" max="20">

                    <label>Tile por defecto</label>
                    <input class="inp" type="text" name="new_map_default_tile" value="<?= hg_kmz_import_h($defaultTile) ?>" maxlength="120">

                    <label>Bounds SW lat</label>
                    <input class="inp" type="text" name="new_map_bounds_sw_lat" value="<?= hg_kmz_import_h($defaultSwLat) ?>">

                    <label>Bounds SW lng</label>
                    <input class="inp" type="text" name="new_map_bounds_sw_lng" value="<?= hg_kmz_import_h($defaultSwLng) ?>">

                    <label>Bounds NE lat</label>
                    <input class="inp" type="text" name="new_map_bounds_ne_lat" value="<?= hg_kmz_import_h($defaultNeLat) ?>">

                    <label>Bounds NE lng</label>
                    <input class="inp" type="text" name="new_map_bounds_ne_lng" value="<?= hg_kmz_import_h($defaultNeLng) ?>">
                </div>
                <div class="adm-help-text" style="margin-top:8px;">Los valores se rellenan automáticamente a partir del fichero importado, pero puedes afinarlos antes de crear el mapa.</div>
            </fieldset>
        </div>

        <div class="adm-mt-10">
            <label style="display:inline-flex;align-items:center;gap:8px;">
                <input type="checkbox" name="skip_existing" value="1" <?= $skipExistingChecked ? 'checked' : '' ?>>
                Omitir duplicados exactos si vuelvo a importar el mismo archivo
            </label>
        </div>

        <?php if ($parseResult['ok']): ?>
            <div class="adm-summary-band adm-mt-10">
                <span class="adm-summary-pill">Documento: <?= hg_kmz_import_h((string)($parseResult['document_name'] ?? '(Sin nombre)')) ?></span>
                <span class="adm-summary-pill">POIs: <?= (int)($parseResult['points_total'] ?? 0) ?></span>
                <span class="adm-summary-pill">Áreas: <?= (int)($parseResult['areas_total'] ?? 0) ?></span>
                <span class="adm-summary-pill">No soportados: <?= (int)($parseResult['unsupported_total'] ?? 0) ?></span>
            </div>

            <div class="adm-help-text adm-mt-10">
                Fuente: <code><?= hg_kmz_import_h((string)($parseResult['relative_path'] ?? '')) ?></code>
                <?php if (!empty($parseResult['entry_name'])): ?>
                    · Entrada: <code><?= hg_kmz_import_h((string)$parseResult['entry_name']) ?></code>
                <?php endif; ?>
                <?php if (!empty($parseResult['extract_method'])): ?>
                    · Lectura: <code><?= hg_kmz_import_h((string)$parseResult['extract_method']) ?></code>
                <?php endif; ?>
            </div>

            <?php if (!empty($parseResult['document_description'])): ?>
                <p class="adm-color-muted adm-mt-10"><?= nl2br(hg_kmz_import_h((string)$parseResult['document_description'])) ?></p>
            <?php endif; ?>

            <fieldset class="bioSeccion adm-mt-10" style="margin-bottom:0;">
                <legend>&nbsp;Mapeo de carpetas a categorías&nbsp;</legend>
                <div class="adm-help-text" style="margin-bottom:10px;">Cada carpeta del KMZ se importará con la categoría que selecciones. Ese mapeo se aplica tanto a POIs como a áreas.</div>
                <div class="adm-table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Carpeta</th>
                                <th>POIs</th>
                                <th>Áreas</th>
                                <th>No soportados</th>
                                <th>Categoría destino</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ((array)$parseResult['folders'] as $folder): ?>
                                <?php
                                $folderKey = (string)($folder['key'] ?? '');
                                $folderDefaultCategory = hg_kmz_import_default_category_for_folder($folder, $categories, $defaultCategoryId);
                                $selectedFolderCategory = isset($_POST['folder_category'][$folderKey])
                                    ? (int)$_POST['folder_category'][$folderKey]
                                    : $folderDefaultCategory;
                                ?>
                                <tr>
                                    <td><?= hg_kmz_import_h((string)($folder['name'] ?? 'Sin carpeta')) ?></td>
                                    <td><?= count((array)($folder['points'] ?? [])) ?></td>
                                    <td><?= count((array)($folder['areas'] ?? [])) ?></td>
                                    <td><?= count((array)($folder['unsupported'] ?? [])) ?></td>
                                    <td>
                                        <select class="select" name="folder_category[<?= hg_kmz_import_h($folderKey) ?>]">
                                            <option value="0">Selecciona categoría...</option>
                                            <?php foreach ($categories as $category): ?>
                                                <?php $catId = (int)($category['id'] ?? 0); ?>
                                                <option value="<?= $catId ?>" <?= $selectedFolderCategory === $catId ? 'selected' : '' ?>>
                                                    <?= hg_kmz_import_h((string)($category['name'] ?? 'Categoría #' . $catId)) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($parseResult['folders'])): ?>
                                <tr><td colspan="5" class="adm-color-muted">(No se encontraron carpetas ni placemarks importables)</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </fieldset>
        <?php else: ?>
            <div class="adm-mt-10" style="padding:10px 12px;border-radius:8px;border:1px solid #b31111;background:#3a0d0d;color:#f5f7ff;">
                <?= hg_kmz_import_h((string)($parseResult['error'] ?? 'No se pudo preparar el fichero fuente.')) ?>
            </div>
        <?php endif; ?>

        <div class="adm-flex-8-center adm-mt-10">
            <button class="btn btn-green" type="submit">Importar al mapa</button>
            <a class="btn" href="/talim?s=admin_map_kmz_import">Recargar</a>
        </div>
    </form>
</div>

<?php if (is_array($importReport)): ?>
    <div class="bioSeccion">
        <h3>Resultado de la importación</h3>
        <div class="adm-summary-band">
            <span class="adm-summary-pill">Mapa: <?= hg_kmz_import_h((string)$importReport['map_name']) ?> (#<?= (int)$importReport['map_id'] ?>)</span>
            <span class="adm-summary-pill"><?= !empty($importReport['map_created']) ? 'Mapa nuevo' : 'Mapa existente' ?></span>
            <span class="adm-summary-pill">POIs insertados: <?= (int)$importReport['pois_inserted'] ?></span>
            <span class="adm-summary-pill">POIs omitidos: <?= (int)$importReport['pois_skipped'] ?></span>
            <span class="adm-summary-pill">Áreas insertadas: <?= (int)$importReport['areas_inserted'] ?></span>
            <span class="adm-summary-pill">Áreas omitidas: <?= (int)$importReport['areas_skipped'] ?></span>
        </div>

        <div class="adm-table-wrap adm-mt-10">
            <table class="table">
                <thead>
                    <tr>
                        <th>Carpeta</th>
                        <th>POIs insertados</th>
                        <th>POIs omitidos</th>
                        <th>Áreas insertadas</th>
                        <th>Áreas omitidas</th>
                        <th>No soportados</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ((array)$importReport['folders'] as $folderReport): ?>
                        <tr>
                            <td><?= hg_kmz_import_h((string)$folderReport['name']) ?></td>
                            <td><?= (int)$folderReport['pois_inserted'] ?></td>
                            <td><?= (int)$folderReport['pois_skipped'] ?></td>
                            <td><?= (int)$folderReport['areas_inserted'] ?></td>
                            <td><?= (int)$folderReport['areas_skipped'] ?></td>
                            <td><?= (int)$folderReport['unsupported'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script>
(function(){
  var choice = document.getElementById('kmz_target_map_choice');
  var fields = document.getElementById('kmz_new_map_fields');
  var nameInput = document.getElementById('kmz_new_map_name');
  var slugInput = document.getElementById('kmz_new_map_slug');
  function slugify(value){
    return String(value || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }
  function syncFields(){
    if (!choice || !fields) return;
    fields.style.display = choice.value === '__new__' ? '' : 'none';
  }
  if (choice) {
    choice.addEventListener('change', syncFields);
    syncFields();
  }
  if (nameInput && slugInput) {
    nameInput.addEventListener('input', function(){
      if (!slugInput.value || slugInput.dataset.autofill === '1') {
        slugInput.value = slugify(nameInput.value);
        slugInput.dataset.autofill = '1';
      }
    });
    slugInput.addEventListener('input', function(){
      slugInput.dataset.autofill = slugInput.value ? '0' : '1';
    });
  }
})();
</script>
