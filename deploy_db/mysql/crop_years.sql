CREATE TABLE `crop_years` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guid` varchar(36) DEFAULT '',
  `name` varchar(512) DEFAULT '',
  `parent_name` varchar(512) DEFAULT '',
  `active` smallint(6) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `unq_guid` (`guid`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;