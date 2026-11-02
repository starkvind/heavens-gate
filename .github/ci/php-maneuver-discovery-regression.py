#!/usr/bin/env python3
"""Regression for issue #45: System Maneuver availability is a deduplicated union.

Uses SQLite fixtures to exercise the production SELECT without a production DB.
No migrations or application data are changed.
"""
from pathlib import Path
import re
import sqlite3

ROOT = Path(__file__).resolve().parents[2]
SOURCE = (ROOT / "app/domains/systems/maneuver_availability.php").read_text(encoding="utf-8")


def query_for(function_name: str) -> str:
    pattern = (
        r"function\s+" + re.escape(function_name)
        + r"\b.*?\$stmt\s*=\s*\$link->prepare\(\s*\"(.*?)\"\s*\)"
    )
    match = re.search(pattern, SOURCE, flags=re.S)
    assert match is not None, f"Cannot extract SQL for {function_name}"
    return match.group(1)


connection = sqlite3.connect(":memory:")
connection.row_factory = sqlite3.Row
connection.executescript("""
CREATE TABLE dim_systems (id INTEGER PRIMARY KEY, name TEXT);
CREATE TABLE dim_forms (id INTEGER PRIMARY KEY, system_id INTEGER);
CREATE TABLE fact_combat_maneuvers (
    id INTEGER PRIMARY KEY, pretty_id TEXT, name TEXT, image_url TEXT,
    roll TEXT, difficulty TEXT, damage TEXT, actions TEXT, system_id INTEGER
);
CREATE TABLE bridge_maneuvers_systems (maneuver_id INTEGER, system_id INTEGER);
CREATE TABLE bridge_maneuvers_forms (maneuver_id INTEGER, form_id INTEGER);
INSERT INTO dim_systems VALUES (1, 'Abraxas'), (2, 'Otro sistema');
INSERT INTO dim_forms VALUES (100, 1), (101, 1), (200, 2);
INSERT INTO fact_combat_maneuvers
(id, pretty_id, name, image_url, roll, difficulty, damage, actions, system_id)
VALUES
(10, 'general', 'General', '', '', '', '', '', 2),
(11, 'patada', 'Patada', '', '', '', '', '', 2),
(12, 'solo-forma', 'Solo otra Forma', '', '', '', '', '', 1),
(13, 'repetida', 'Repetida', '', '', '', '', '', 1),
(14, 'ajena', 'Ajena', '', '', '', '', '', 2),
(15, 'origen', 'Solo origen', '', '', '', '', '', 1);
INSERT INTO bridge_maneuvers_systems VALUES (10, 1), (13, 1), (14, 2);
INSERT INTO bridge_maneuvers_forms VALUES
(11, 100), (11, 101), (12, 101), (13, 100), (14, 200);
""")

system_query = query_for("hg_systems_fetch_system_maneuvers_normalized")
system_rows = connection.execute(system_query, (1, 1)).fetchall()
system_ids = [row["id"] for row in system_rows]
assert set(system_ids) == {10, 11, 12, 13}, system_ids
assert len(system_ids) == len(set(system_ids)), "Duplicate Maneuver in System overview"
assert system_ids == sorted(system_ids, key=lambda id: next(
    row["name"] for row in system_rows if row["id"] == id
)), "System maneuvers must retain name ordering"
assert next(row for row in system_rows if row["id"] == 10)["origin_system_name"] == "Otro sistema"
assert connection.execute(system_query, (2, 2)).fetchall()[0]["id"] == 14

form_query = query_for("hg_systems_fetch_form_maneuvers_normalized")
form_100 = {row["id"] for row in connection.execute(form_query, (1, 100))}
form_101 = {row["id"] for row in connection.execute(form_query, (1, 101))}
assert form_100 == {10, 11, 13}, form_100
assert form_101 == {10, 11, 12, 13}, form_101

overview = (ROOT / "app/controllers/systems/system_overview_page.php").read_text(encoding="utf-8")
mobile = (ROOT / "app/mobile/controllers/systems.php").read_text(encoding="utf-8")
table = (ROOT / "app/controllers/docs/maneuver_list.php").read_text(encoding="utf-8")
assert overview.index("$systemResources =") < overview.index("$systemManeuvers =")
assert mobile.index("$resources = hg_systems_fetch_mobile_resources") < mobile.index("$systemManeuvers =")
assert "hg_systems_fetch_system_maneuvers_normalized($link, $systemId)" in mobile
assert "id=\"tabla-maniobras\"" in table
assert "datatable_assets.php" in table
assert "hg-maneuvers-system-filter" in table
print("Issue #45 Maneuver discovery regression: OK")
