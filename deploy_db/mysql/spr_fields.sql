CREATE TABLE spr_fields (
  id int UNSIGNED NOT NULL AUTO_INCREMENT,
  name varchar(512) DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY UK_spr_fields (name),
) ENGINE=InnoDB DEFAULT CHARSET=utf8;