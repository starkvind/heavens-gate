<?php
/**
 * Phase 6C: editorial publication is NOT the mechanical/source bibliography.
 * A source is stored on the existing entity/availability; this domain returns
 * a separate book only when an explicit scoped membership has been approved.
 */
const HG_CAMAZOTZ_PUBLICATION_SLUG = 'heavens-gate-camazotz';
const HG_CAMAZOTZ_SYSTEM_ID = 14;

function hg_camazotz_publication_requested(array $request): bool
{
    return function_exists('hg_request_query_param')
        && hg_request_query_param($request, 'edition') === HG_CAMAZOTZ_PUBLICATION_SLUG;
}

function hg_camazotz_publication_link(string $url): string
{
    if ($url === '' || $url[0] !== '/' || substr($url, 0, 2) === '//') return $url;
    if (strpos($url, 'edition=') !== false) return $url;
    return $url . (strpos($url, '?') === false ? '?' : '&') .
        'edition=' . HG_CAMAZOTZ_PUBLICATION_SLUG;
}

/** True for a system-owned identity, never for a borrowed Gift by default. */
function hg_camazotz_publication_eligible(int $ownedSystemId, bool $explicitContext): bool
{
    return $ownedSystemId === HG_CAMAZOTZ_SYSTEM_ID || $explicitContext;
}

/**
 * Fail closed: no migration/record, no declared edition.
 * Member presence never globally changes an existing bibliography_id.
 */
function hg_camazotz_publication_for(
    mysqli $db,
    string $entityTable,
    int $entityId,
    bool $contextAllowed
): ?array {
    if (!$contextAllowed || $entityId <= 0) return null;
    $whitelist = [
        'dim_systems', 'dim_breeds', 'dim_auspices', 'dim_forms', 'dim_tribes',
        'fact_gifts', 'dim_totems', 'dim_traits', 'dim_merits_flaws',
    ];
    if (!in_array($entityTable, $whitelist, true)) return null;

    try {
        $stmt = $db->prepare(
            'SELECT b.id, b.name, b.year, b.publisher ' .
            'FROM bridge_bibliography_publications p ' .
            'INNER JOIN dim_bibliographies b ON b.id=p.bibliography_id ' .
            'WHERE p.entity_table=? AND p.entity_id=? AND p.context_system_id=? ' .
            'AND b.pretty_id=? LIMIT 1'
        );
        if (!$stmt) return null;
        $system = HG_CAMAZOTZ_SYSTEM_ID;
        $slug = HG_CAMAZOTZ_PUBLICATION_SLUG;
        $stmt->bind_param('siis', $entityTable, $entityId, $system, $slug);
        $ok = $stmt->execute();
        $rs = $ok ? $stmt->get_result() : false;
        $row = $rs ? $rs->fetch_assoc() : null;
        if ($rs) $rs->free();
        $stmt->close();
        return $row ?: null;
    } catch (Throwable $e) {
        // If the controlled migration has not run, leave public source output
        // untouched. Never infer publication from a title substring.
        return null;
    }
}

/**
 * Availability provenance can differ from the Gift's global source. Fetch
 * only for verified Camazotz publication membership, never on global views.
 */
function hg_camazotz_gift_availability_source(mysqli $db, int $giftId): string
{
    if ($giftId <= 0) return '';
    try {
        $stmt = $db->prepare(
            'SELECT b.name FROM bridge_gifts_availability a ' .
            'LEFT JOIN dim_bibliographies b ON b.id=a.bibliography_id ' .
            'WHERE a.system_id=? AND a.gift_id=? LIMIT 1'
        );
        if (!$stmt) return '';
        $system = HG_CAMAZOTZ_SYSTEM_ID;
        $stmt->bind_param('ii', $system, $giftId);
        $stmt->execute();
        $rs = $stmt->get_result();
        $row = $rs ? $rs->fetch_assoc() : null;
        if ($rs) $rs->free();
        $stmt->close();
        return trim((string)($row['name'] ?? ''));
    } catch (Throwable $e) {
        return '';
    }
}

function hg_camazotz_publication_label(?array $publication): string
{
    return trim((string)($publication['name'] ?? ''));
}
