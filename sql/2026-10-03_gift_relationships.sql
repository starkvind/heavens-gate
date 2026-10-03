START TRANSACTION;

CREATE TABLE IF NOT EXISTS `bridge_gifts_relations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gift_id` int(10) unsigned NOT NULL,
  `related_gift_id` int(10) unsigned NOT NULL,
  `relation_type` enum('mechanics_from','variant_of') NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bgr_source_type` (`gift_id`,`relation_type`),
  KEY `idx_bgr_related_gift` (`related_gift_id`),
  CONSTRAINT `fk_bgr_gift`
    FOREIGN KEY (`gift_id`)
    REFERENCES `fact_gifts` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_bgr_related_gift`
    FOREIGN KEY (`related_gift_id`)
    REFERENCES `fact_gifts` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

/*
 * Ishin Deshin (Kitsune) -> Habla Mental (Galliard)
 * Source text: "Como el Don Galliard Habla Mental."
 *
 * mechanics_from means:
 * - keep Ishin Deshin as its own Gift identity;
 * - inherit roll/mechanics when the local fields are empty;
 * - expose Habla Mental as the mechanical source on the Gift page.
 */
INSERT INTO `bridge_gifts_relations`
    (`gift_id`, `related_gift_id`, `relation_type`, `notes`)
VALUES
    (632, 13, 'mechanics_from', 'Ishin Deshin usa la mecánica del Don Galliard Habla Mental')
ON DUPLICATE KEY UPDATE
    `related_gift_id` = VALUES(`related_gift_id`),
    `notes` = VALUES(`notes`);

/* Verification: expected one row for Ishin Deshin. */
SELECT
    r.id,
    r.gift_id,
    g.name AS gift_name,
    r.related_gift_id,
    rg.name AS related_gift_name,
    r.relation_type,
    r.notes
FROM bridge_gifts_relations r
INNER JOIN fact_gifts g ON g.id = r.gift_id
INNER JOIN fact_gifts rg ON rg.id = r.related_gift_id
WHERE r.gift_id = 632;

/* Safety audit: source and target must never be the same Gift. Expected 0 rows. */
SELECT *
FROM bridge_gifts_relations
WHERE gift_id = related_gift_id;

COMMIT;
