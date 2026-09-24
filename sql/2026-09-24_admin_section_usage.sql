-- Heaven's Gate: aggregate Admin section usage telemetry.
-- Run manually once with a schema-capable database account.
-- Privacy model: route/day aggregates only. No IP, user-agent or user identity.

CREATE TABLE IF NOT EXISTS `fact_admin_section_usage_daily` (
  `section_key` varchar(100) NOT NULL,
  `access_date` date NOT NULL,
  `view_count` int unsigned NOT NULL DEFAULT 0,
  `first_seen_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`section_key`, `access_date`),
  KEY `idx_admin_section_usage_date` (`access_date`),
  KEY `idx_admin_section_usage_last_seen` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
