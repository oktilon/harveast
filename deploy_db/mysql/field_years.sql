CREATE TABLE field_years (
  id int UNSIGNED NOT NULL AUTO_INCREMENT,
  guid varchar(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  name varchar(512) NOT NULL DEFAULT '',
  num varchar(155) NOT NULL DEFAULT '',
  parent int UNSIGNED NOT NULL DEFAULT 0,
  fld int UNSIGNED NOT NULL DEFAULT 0,
  period datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
  ar numeric(10,4) NOT NULL DEFAULT 0.0000,
  `year` smallint UNSIGNED NOT NULL DEFAULT 0,
  crop_by int UNSIGNED NOT NULL DEFAULT 0,
  active tinyint UNSIGNED NOT NULL DEFAULT 0,
  upd timestamp NOT NULL default current_timestamp on update current_timestamp,
  PRIMARY KEY (id),
  UNIQUE KEY UK_field_years (guid),
  KEY IDX_field_years_fy (fld,`year`),
  KEY IDX_field_years_upd (upd),
  KEY IDX_field_years_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;