CREATE TABLE production_rate_parents (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(250) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY UK_production_rate_parents (`name`)
) ENGINE=INNODB DEFAULT CHARSET=utf8;