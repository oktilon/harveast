CREATE TABLE crop_years (
  id int UNSIGNED NOT NULL AUTO_INCREMENT,
  guid varchar(36) DEFAULT '00000000-0000-0000-0000-000000000000',
  name varchar(512) DEFAULT '',
  crop int UNSIGNED DEFAULT 0,
  parent int UNSIGNED DEFAULT 0,
  `year` smallint UNSIGNED DEFAULT 0,
  active tinyint UNSIGNED DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY UK_crop_years (guid),
  KEY IDX_crop_years_year (`year`),
  KEY IDX_crop_years_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;