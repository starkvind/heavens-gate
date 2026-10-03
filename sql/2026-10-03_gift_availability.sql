-- Gift learnability / availability bridge.
-- Production-compatible MariaDB schema.
--
-- Separates Gift identity (fact_gifts) from who may learn it.
-- Canonical Werecreature scopes are race / auspice / tribe; UI labels such as
-- Senda or Aspecto do not change the stored semantic scope.

CREATE TABLE IF NOT EXISTS `bridge_gifts_availability` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,

  `gift_id` int(10) unsigned NOT NULL,
  `system_id` int(10) unsigned NOT NULL,

  `scope_type` enum(
    'system',
    'race',
    'auspice',
    'tribe',
    'organization'
  ) NOT NULL,

  `scope_id` int(10) unsigned NOT NULL,

  `rank_override` varchar(25) DEFAULT NULL,
  `bibliography_id` int(10) unsigned DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,

  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),

  PRIMARY KEY (`id`),

  UNIQUE KEY `uq_bga_gift_scope`
    (`gift_id`, `system_id`, `scope_type`, `scope_id`),

  KEY `idx_bga_gift` (`gift_id`),
  KEY `idx_bga_system` (`system_id`),
  KEY `idx_bga_scope` (`system_id`, `scope_type`, `scope_id`),
  KEY `idx_bga_bibliography` (`bibliography_id`),

  CONSTRAINT `fk_bga_gift`
    FOREIGN KEY (`gift_id`)
    REFERENCES `fact_gifts` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

  CONSTRAINT `fk_bga_system`
    FOREIGN KEY (`system_id`)
    REFERENCES `dim_systems` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,

  CONSTRAINT `fk_bga_bibliography`
    FOREIGN KEY (`bibliography_id`)
    REFERENCES `dim_bibliographies` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Deliberately no CHECK(scope_type <> 'system' OR scope_id = system_id).
-- Production MariaDB rejected that CHECK because system_id participates in an
-- ON UPDATE CASCADE foreign key (error 1901). The invariant is enforced by
-- import/application validation instead.
