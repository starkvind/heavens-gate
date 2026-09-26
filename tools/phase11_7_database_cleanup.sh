#!/usr/bin/env bash
set -euo pipefail
umask 077

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
AUDIT_SQL="$ROOT/sql/phase11_7_retired_schema_audit.sql"
CLEANUP_SQL="$ROOT/sql/phase11_7_retired_schema_cleanup.sql"
BACKUP_DIR="${HG_DB_BACKUP_DIR:-$HOME/heavens-gate-db-backups}"
EXPECTED_DB="${HG_EXPECTED_DB:-u807926597_hg}"
MODE="${1:---audit}"

die() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

info() {
    printf '%s\n' "$*"
}

usage() {
    cat <<'EOF'
Usage:
  bash tools/phase11_7_database_cleanup.sh --audit
  bash tools/phase11_7_database_cleanup.sh --backup-only
  bash tools/phase11_7_database_cleanup.sh --apply

--audit       Read-only production audit. No backup, no writes.
--backup-only Run audit, then create and verify a full compressed backup.
--apply       Run audit, create+verify backup, apply cleanup, verify, dump post-clean schema,
              then run the Phase 11 HTTP smoke.

Environment:
  HG_DB_BACKUP_DIR  Backup directory. Default: ~/heavens-gate-db-backups
  HG_EXPECTED_DB    Expected database name. Default: u807926597_hg
EOF
}

case "$MODE" in
    --audit|--backup-only|--apply) ;;
    -h|--help) usage; exit 0 ;;
    *) usage; die "Unknown mode: $MODE" ;;
esac

[ -f "$AUDIT_SQL" ] || die "Missing $AUDIT_SQL"
[ -f "$CLEANUP_SQL" ] || die "Missing $CLEANUP_SQL"

if command -v git >/dev/null 2>&1 && [ -d "$ROOT/.git" ]; then
    CURRENT_BRANCH="$(git -C "$ROOT" branch --show-current)"
    [ "$CURRENT_BRANCH" = "master" ] || die "Run Phase 11.7 from master, not '$CURRENT_BRANCH'."
    if [ -n "$(git -C "$ROOT" status --porcelain)" ]; then
        die "Working tree is not clean. Commit/stash local changes before database maintenance."
    fi
    info "Git branch: master"
    info "Git HEAD:   $(git -C "$ROOT" rev-parse HEAD)"
fi

CONFIG_ENV=""
for candidate in "$ROOT/../config.env" "$ROOT/config.env" "$ROOT/app/config.env"; do
    if [ -f "$candidate" ]; then
        CONFIG_ENV="$candidate"
        break
    fi
done
[ -n "$CONFIG_ENV" ] || die "config.env not found in expected locations."

command -v php >/dev/null 2>&1 || die "php CLI is required."
DB_NAME="$(
    php -r '
        $env = @parse_ini_file($argv[1]);
        if (!is_array($env) || empty($env["MYSQL_BDD"])) {
            fwrite(STDERR, "MYSQL_BDD missing in config.env\n");
            exit(2);
        }
        echo $env["MYSQL_BDD"];
    ' "$CONFIG_ENV"
)"
[[ "$DB_NAME" =~ ^[A-Za-z0-9_]+$ ]] || die "Unexpected database identifier: $DB_NAME"
[ "$DB_NAME" = "$EXPECTED_DB" ] || die "Refusing database '$DB_NAME'; expected '$EXPECTED_DB'."

MARIADB_BIN="$(command -v mariadb || true)"
[ -n "$MARIADB_BIN" ] || die "mariadb client not found."

DUMP_BIN="$(command -v mariadb-dump || command -v mysqldump || true)"
[ -n "$DUMP_BIN" ] || die "mariadb-dump/mysqldump not found."

command -v gzip >/dev/null 2>&1 || die "gzip is required."
command -v sha256sum >/dev/null 2>&1 || die "sha256sum is required."
command -v python3 >/dev/null 2>&1 || die "python3 is required."

# DDL is intentionally impossible with the least-privilege web account.
# Phase 11.7 therefore uses the local administrative socket through sudo.
sudo -v

db_scalar() {
    sudo "$MARIADB_BIN" --batch --skip-column-names "$DB_NAME" -e "$1"
}

run_audit() {
    local log="$1"
    info ""
    info "=== Phase 11.7 read-only audit ==="
    sudo "$MARIADB_BIN" --table "$DB_NAME" < "$AUDIT_SQL" | tee "$log"
}

KNOWN_OBJECTS_SQL="'dim_game_card_materials','dim_game_card_moves','dim_game_card_pack_types','dim_game_card_rarities','dim_game_card_settings','dim_game_card_shop_products','dim_game_card_types','dim_game_card_ui_texts','fact_game_card_collection','fact_game_card_move_learn_rules','fact_game_card_pack_rarity_weights','fact_game_card_pack_type_filters','fact_sim_battles','fact_sim_character_scores','fact_sim_characters_talk','fact_sim_item_usage','fact_sim_seasons','fact_sim_tournaments','bridge_battle_sim_characters_seasons','vw_game_card_collection','vw_sim_characters','vw_sim_forms','vw_sim_items'"
RETIRED_TABLES_SQL="'dim_game_card_materials','dim_game_card_moves','dim_game_card_pack_types','dim_game_card_rarities','dim_game_card_settings','dim_game_card_shop_products','dim_game_card_types','dim_game_card_ui_texts','fact_game_card_collection','fact_game_card_move_learn_rules','fact_game_card_pack_rarity_weights','fact_game_card_pack_type_filters','fact_sim_battles','fact_sim_character_scores','fact_sim_characters_talk','fact_sim_item_usage','fact_sim_seasons','fact_sim_tournaments','bridge_battle_sim_characters_seasons'"

check_blockers() {
    local unknown_objects type_mismatches inbound_fks live_views live_triggers live_events live_routines unknown_game_menu

    unknown_objects="$(db_scalar "
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND (
            TABLE_NAME LIKE '%game_card%'
            OR TABLE_NAME LIKE 'fact_sim_%'
            OR TABLE_NAME LIKE '%battle_sim%'
            OR TABLE_NAME LIKE 'vw_sim_%'
            OR TABLE_NAME LIKE '%combat_sim%'
          )
          AND TABLE_NAME NOT IN ($KNOWN_OBJECTS_SQL);
    ")"

    type_mismatches="$(db_scalar "
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND (
            (TABLE_NAME IN ('vw_game_card_collection','vw_sim_characters','vw_sim_forms','vw_sim_items')
             AND TABLE_TYPE <> 'VIEW')
            OR
            (TABLE_NAME IN ($RETIRED_TABLES_SQL,'admin_webp_image_migration_backup','_id_unsigned_audit')
             AND TABLE_TYPE <> 'BASE TABLE')
          );
    ")"

    inbound_fks="$(db_scalar "
        SELECT COUNT(*)
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND REFERENCED_TABLE_SCHEMA = DATABASE()
          AND REFERENCED_TABLE_NAME IN ($RETIRED_TABLES_SQL)
          AND TABLE_NAME NOT IN ($RETIRED_TABLES_SQL);
    ")"

    live_views="$(db_scalar "
        SELECT COUNT(*)
        FROM information_schema.VIEWS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME NOT IN ('vw_game_card_collection','vw_sim_characters','vw_sim_forms','vw_sim_items')
          AND LOWER(COALESCE(VIEW_DEFINITION, '')) REGEXP 'game_card|fact_sim_|battle_sim|combat_sim|vw_sim_';
    ")"

    live_triggers="$(db_scalar "
        SELECT COUNT(*)
        FROM information_schema.TRIGGERS
        WHERE TRIGGER_SCHEMA = DATABASE()
          AND EVENT_OBJECT_TABLE NOT IN ($RETIRED_TABLES_SQL)
          AND LOWER(ACTION_STATEMENT) REGEXP 'game_card|fact_sim_|battle_sim|combat_sim|vw_sim_';
    ")"

    live_events="$(db_scalar "
        SELECT COUNT(*)
        FROM information_schema.EVENTS
        WHERE EVENT_SCHEMA = DATABASE()
          AND LOWER(COALESCE(EVENT_DEFINITION, '')) REGEXP 'game_card|fact_sim_|battle_sim|combat_sim|vw_sim_';
    ")"

    live_routines="$(db_scalar "
        SELECT COUNT(*)
        FROM information_schema.ROUTINES
        WHERE ROUTINE_SCHEMA = DATABASE()
          AND ROUTINE_NAME <> 'audit_signed_id_columns'
          AND LOWER(COALESCE(ROUTINE_DEFINITION, '')) REGEXP 'game_card|fact_sim_|battle_sim|combat_sim|vw_sim_';
    ")"

    unknown_game_menu="$(db_scalar "
        SELECT COUNT(*)
        FROM dim_menu_items i
        LEFT JOIN dim_menu_items parent ON parent.id = i.parent_id
        WHERE (
            i.href LIKE '/games/%'
            OR parent.menu_key = 'gamesMenu'
        )
          AND COALESCE(i.href, '') NOT IN ('/games/card-game','/games/combat-simulator')
          AND COALESCE(i.menu_key, '') <> 'gamesMenu';
    ")"

    info ""
    info "Preflight blockers:"
    info "  unknown retired-looking objects : $unknown_objects"
    info "  unexpected object types         : $type_mismatches"
    info "  live inbound foreign keys       : $inbound_fks"
    info "  live dependent views            : $live_views"
    info "  live dependent triggers         : $live_triggers"
    info "  live dependent events           : $live_events"
    info "  live dependent routines         : $live_routines"
    info "  unknown /games menu entries     : $unknown_game_menu"

    if [ "$unknown_objects" != "0" ] ||
       [ "$type_mismatches" != "0" ] ||
       [ "$inbound_fks" != "0" ] ||
       [ "$live_views" != "0" ] ||
       [ "$live_triggers" != "0" ] ||
       [ "$live_events" != "0" ] ||
       [ "$live_routines" != "0" ] ||
       [ "$unknown_game_menu" != "0" ]; then
        die "Preflight found an unreviewed dependency. Nothing destructive has been executed."
    fi
}

make_backup() {
    local timestamp="$1"
    local backup="$BACKUP_DIR/heavens-gate-pre-phase11-7-$timestamp.sql.gz"
    local tmp="$backup.tmp"

    mkdir -p "$BACKUP_DIR"
    chmod 700 "$BACKUP_DIR"

    info ""
    info "=== Full pre-cleanup backup ==="
    info "Writing: $backup"

    rm -f "$tmp"
    sudo "$DUMP_BIN"         --single-transaction         --quick         --routines         --triggers         --events         --hex-blob         --default-character-set=utf8mb4         --databases "$DB_NAME"         | gzip -9 > "$tmp"

    [ -s "$tmp" ] || die "Backup file is empty."
    gzip -t "$tmp"

    python3 - "$tmp" <<'PY'
import gzip
import sys

path = sys.argv[1]
required = {
    "fact_characters": False,
    "dim_web_configuration": False,
    "dim_menu_items": False,
}
with gzip.open(path, "rt", encoding="utf-8", errors="ignore") as fh:
    for line in fh:
        for table in list(required):
            if f"CREATE TABLE `{table}`" in line:
                required[table] = True

missing = [name for name, found in required.items() if not found]
if missing:
    raise SystemExit("Backup verification missing core tables: " + ", ".join(missing))
PY

    mv "$tmp" "$backup"
    sha256sum "$backup" | tee "$backup.sha256"
    chmod 600 "$backup" "$backup.sha256"

    BACKUP_PATH="$backup"
    export BACKUP_PATH
    info "Backup verified."
}

post_verify() {
    local remaining routine_count config_count menu_count

    remaining="$(db_scalar "
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME IN (
            $KNOWN_OBJECTS_SQL,
            'admin_webp_image_migration_backup',
            '_id_unsigned_audit'
          );
    ")"

    routine_count="$(db_scalar "
        SELECT COUNT(*)
        FROM information_schema.ROUTINES
        WHERE ROUTINE_SCHEMA = DATABASE()
          AND ROUTINE_NAME = 'audit_signed_id_columns';
    ")"

    config_count="$(db_scalar "
        SELECT COUNT(*)
        FROM dim_web_configuration
        WHERE LEFT(config_name, 17) = 'combat_simulator_';
    ")"

    menu_count="$(db_scalar "
        SELECT COUNT(*)
        FROM dim_menu_items
        WHERE href IN ('/games/card-game','/games/combat-simulator')
           OR menu_key = 'gamesMenu';
    ")"

    info ""
    info "Post-cleanup verification:"
    info "  retired tables/views left       : $remaining"
    info "  retired audit procedures left   : $routine_count"
    info "  combat config rows left         : $config_count"
    info "  retired game menu rows left     : $menu_count"

    [ "$remaining" = "0" ] || die "Retired database objects remain."
    [ "$routine_count" = "0" ] || die "Retired audit procedure remains."
    [ "$config_count" = "0" ] || die "Retired combat configuration remains."
    [ "$menu_count" = "0" ] || die "Retired Games menu rows remain."
}

make_post_schema_snapshot() {
    local timestamp="$1"
    local schema="$BACKUP_DIR/heavens-gate-post-phase11-7-schema-$timestamp.sql.gz"

    info ""
    info "=== Post-cleanup schema snapshot ==="
    sudo "$DUMP_BIN"         --no-data         --routines         --triggers         --events         --default-character-set=utf8mb4         --databases "$DB_NAME"         | gzip -9 > "$schema"

    [ -s "$schema" ] || die "Post-cleanup schema snapshot is empty."
    gzip -t "$schema"
    sha256sum "$schema" | tee "$schema.sha256"
    chmod 600 "$schema" "$schema.sha256"
    info "Schema snapshot: $schema"
}

TIMESTAMP="$(date '+%Y%m%d-%H%M%S')"
mkdir -p "$BACKUP_DIR"
AUDIT_LOG="$BACKUP_DIR/phase11-7-audit-$TIMESTAMP.txt"

run_audit "$AUDIT_LOG"
check_blockers

if [ "$MODE" = "--audit" ]; then
    info ""
    info "AUDIT PASS. No writes performed."
    exit 0
fi

make_backup "$TIMESTAMP"

if [ "$MODE" = "--backup-only" ]; then
    info ""
    info "BACKUP PASS. No database changes performed."
    info "Backup: $BACKUP_PATH"
    exit 0
fi

info ""
info "=== Applying Phase 11.7 cleanup ==="
sudo "$MARIADB_BIN" "$DB_NAME" < "$CLEANUP_SQL"
post_verify
make_post_schema_snapshot "$TIMESTAMP"

info ""
info "=== HTTP smoke ==="
if [ -x "$ROOT/tools/phase11_smoke.sh" ]; then
    "$ROOT/tools/phase11_smoke.sh"
else
    bash "$ROOT/tools/phase11_smoke.sh"
fi

info ""
info "PHASE 11.7 DATABASE CLEANUP PASS."
info "Backup: $BACKUP_PATH"
info "Restore command if ever required:"
printf "  gzip -dc %q | sudo mariadb\n" "$BACKUP_PATH"
