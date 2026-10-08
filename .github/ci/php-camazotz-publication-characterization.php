<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/http/request_context.php';
require_once __DIR__ . '/../../app/domains/bibliography/publication_context.php';

function hg_cam_pub_expect(bool $passed, string $explanation): void {
    if (!$passed) { fwrite(STDERR, $explanation . PHP_EOL); exit(1); }
}

$base = '/powers/gift/a-gift';
hg_cam_pub_expect(
    hg_camazotz_publication_link($base)
        === '/powers/gift/a-gift?edition=heavens-gate-camazotz',
    'Edition link should preserve public pretty path'
);
hg_cam_pub_expect(
    hg_camazotz_publication_link('/powers/gift/123?view=mobile')
        === '/powers/gift/123?view=mobile&edition=heavens-gate-camazotz',
    'Edition link should preserve mobile query'
);
hg_cam_pub_expect(
    hg_camazotz_publication_link('https://example.com/a') === 'https://example.com/a',
    'External links must not acquire editorial context'
);
hg_cam_pub_expect(
    hg_camazotz_publication_link('//example.com/a') === '//example.com/a',
    'Protocol-relative links must remain untouched'
);
hg_cam_pub_expect(
    hg_camazotz_publication_eligible(14, false), 'Camazotz-owned material must be eligible'
);
hg_cam_pub_expect(
    !hg_camazotz_publication_eligible(2, false),
    'Other-system Gift must not receive Camazotz publication globally'
);
hg_cam_pub_expect(
    hg_camazotz_publication_eligible(2, true),
    'Explicit Camazotz member may request publication'
);
hg_cam_pub_expect(
    !hg_camazotz_publication_requested(['query' => ['edition' => 'other-book']]),
    'Other editions must not activate Camazotz attribution'
);
hg_cam_pub_expect(
    hg_camazotz_publication_requested(['query' => ['edition' => 'heavens-gate-camazotz']]),
    'Approved contextual edition should be selectable'
);
hg_cam_pub_expect(
    hg_camazotz_publication_label(['name' => "Heaven's Gate: Camazotz"])
        === "Heaven's Gate: Camazotz", 'Edition label mismatch'
);
echo "Camazotz publication context characterization: PASS\n";
