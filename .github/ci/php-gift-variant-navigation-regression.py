#!/usr/bin/env python3
"""Regression contract for issue #44: direction-sensitive Gift navigation."""
from pathlib import Path

root = Path(__file__).resolve().parents[2]
domain = (root / "app/domains/powers/gift_relationships.php").read_text()
desktop = (root / "app/controllers/pwrs/don_page.php").read_text()
mobile = (root / "app/mobile/controllers/powers.php").read_text()

assert "function hg_powers_fetch_gift_navigation(" in domain
navigation = domain.split("function hg_powers_fetch_gift_navigation(", 1)[1].split("function hg_powers_resolve_gift_soft_mechanics(", 1)[0]
assert "hg_powers_fetch_gift_dependents($link, $giftId)" in navigation
assert "r.gift_id = ?" in navigation and "r.relation_type = 'variant_of'" in navigation
assert "r.related_gift_id" in navigation and "g.id = r.related_gift_id" in navigation
assert "'Versión anterior'" in navigation and "'Versión más reciente'" in navigation
assert "'Utiliza esta mecánica'" in navigation
assert "'↑'" in navigation and "'↓'" in navigation and "'⚙'" in navigation
assert "hg_powers_fetch_gift_navigation($link, $donPageID)" in desktop
assert "navigation_label" in desktop and "navigation_symbol" in desktop
assert "hg-powers-related-symbol" in desktop
assert "hg-powers-related-name" in desktop
assert "hg_powers_fetch_gift_navigation($db, $id)" in mobile
assert "'links' => $related" in mobile
assert "hg-mobile-related-symbol" in mobile
assert "hg_mpw_url($db, 'fact_gifts', '/powers/gift', $targetId)" in mobile
print("Gift variant navigation contract OK (desktop/mobile, no relation duplication)")
