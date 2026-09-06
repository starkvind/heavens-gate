-- Heaven's Gate: configurable public DataTables columns.
-- Execute manually with a schema-capable database account.

CREATE TABLE IF NOT EXISTS `dim_datatable_columns` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `datatable_id` varchar(100) NOT NULL,
  `datatable_label` varchar(100) NOT NULL,
  `column_index` smallint unsigned NOT NULL,
  `column_label` varchar(100) NOT NULL,
  `visible_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_core` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_datatable_column` (`datatable_id`,`column_index`),
  KEY `idx_datatable_columns_table` (`datatable_id`,`column_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `dim_datatable_columns`
(`datatable_id`,`datatable_label`,`column_index`,`column_label`,`visible_default`,`is_core`) VALUES
('tabla-acciones','Acciones',0,'Acción',1,1),
('tabla-acciones','Acciones',1,'Categoría',0,0),
('tabla-acciones','Acciones',2,'Tirada',1,1),
('tabla-acciones','Acciones',3,'Dificultad',0,0),
('tabla-acciones','Acciones',4,'Origen',1,1),

('tabla-capitulos','Capítulos',0,'Episodio',1,1),
('tabla-capitulos','Capítulos',1,'Nº',1,1),
('tabla-capitulos','Capítulos',2,'Temporada',1,1),
('tabla-capitulos','Capítulos',3,'Crónica',0,0),
('tabla-capitulos','Capítulos',4,'Descripción',0,0),
('tabla-capitulos','Capítulos',5,'Nº personajes',0,0),

('tabla-meritos','Méritos y Defectos',0,'Nombre',1,1),
('tabla-meritos','Méritos y Defectos',1,'Tipo',1,1),
('tabla-meritos','Méritos y Defectos',2,'Sistema',0,0),
('tabla-meritos','Méritos y Defectos',3,'Categoría',0,0),
('tabla-meritos','Méritos y Defectos',4,'Coste',0,0),
('tabla-meritos','Méritos y Defectos',5,'Origen',1,1),

('tabla-rasgos','Rasgos',0,'Nombre',1,1),
('tabla-rasgos','Rasgos',1,'Personajes',0,0),
('tabla-rasgos','Rasgos',2,'Tipo',1,1),
('tabla-rasgos','Rasgos',3,'Clasificación',0,0),
('tabla-rasgos','Rasgos',4,'Origen',1,1),

('tabla-dones','Dones',0,'Nombre',1,1),
('tabla-dones','Dones',1,'Fêra',0,0),
('tabla-dones','Dones',2,'Tipo',0,0),
('tabla-dones','Dones',3,'Grupo',0,0),
('tabla-dones','Dones',4,'Rango',1,1),
('tabla-dones','Dones',5,'Tirada',0,0),
('tabla-dones','Dones',6,'Origen',1,1),

('tabla-ritos','Ritos',0,'Nombre',1,1),
('tabla-ritos','Ritos',1,'Fêra',0,0),
('tabla-ritos','Ritos',2,'Tipo',0,0),
('tabla-ritos','Ritos',3,'Nivel',1,1),
('tabla-ritos','Ritos',4,'Origen',1,1),

('tabla-disciplinas','Disciplinas',0,'Nombre',1,1),
('tabla-disciplinas','Disciplinas',1,'Disciplina',0,0),
('tabla-disciplinas','Disciplinas',2,'Nivel',1,1),
('tabla-disciplinas','Disciplinas',3,'Tirada',0,0),
('tabla-disciplinas','Disciplinas',4,'Origen',1,1),

('tabla-personajes','Personajes',0,'ID',0,0),
('tabla-personajes','Personajes',1,'Nombre',1,1),
('tabla-personajes','Personajes',2,'Grupo',0,0),
('tabla-personajes','Personajes',3,'Organización',0,0),
('tabla-personajes','Personajes',4,'Sistema',0,0),
('tabla-personajes','Personajes',5,'Tipo',1,1),
('tabla-personajes','Personajes',6,'Estado',1,1)
ON DUPLICATE KEY UPDATE
  `datatable_label` = VALUES(`datatable_label`),
  `column_label` = VALUES(`column_label`),
  `visible_default` = VALUES(`visible_default`),
  `is_core` = VALUES(`is_core`),
  `updated_at` = current_timestamp();
