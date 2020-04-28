SOURCE crop_years.sql

CREATE TABLE `data_index` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tbl` varchar(55) NOT NULL DEFAULT '',
  `fld` varchar(55) NOT NULL DEFAULT 'name',
  `fld_ix` varchar(55) NOT NULL DEFAULT 'ix',
  `hash` varchar(32) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8;

CREATE TABLE `data_index_usage` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `di` int(10) unsigned NOT NULL DEFAULT 0,
  `tbl` varchar(55) NOT NULL DEFAULT '',
  `fld` varchar(55) NOT NULL DEFAULT '',
  `fld_ix` varchar(55) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `IX_di` (`di`),
  KEY `IX_tbl` (`tbl`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `equipment_model_parent`
--

DROP TABLE IF EXISTS `equipment_model_parent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipment_model_parent` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(250) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `equipment_models`
--

DROP TABLE IF EXISTS `equipment_models`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipment_models` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guid` varchar(36) DEFAULT '',
  `name` varchar(512) DEFAULT '',
  `parent_name` varchar(512) DEFAULT '',
  `name_eng` varchar(512) DEFAULT '',
  `wd` decimal(7,2) DEFAULT 0.00,
  `nomen_guid` varchar(36) DEFAULT '',
  `nomen_name` varchar(512) DEFAULT '',
  `active` smallint(6) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unq_guid` (`guid`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=677 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `field_states`
--

DROP TABLE IF EXISTS `field_states`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `field_states` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `field` varchar(36) DEFAULT '',
  `period` datetime DEFAULT '0000-00-00 00:00:00',
  `year` datetime DEFAULT '0000-00-00 00:00:00',
  `crop` varchar(36) DEFAULT '',
  `active` smallint(6) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `unq_guid` (`field`),
  KEY `idx_active` (`active`),
  KEY `idx_crop` (`crop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `field_years`
--

DROP TABLE IF EXISTS `field_years`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `field_years` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guid` varchar(36) DEFAULT '',
  `name` varchar(512) DEFAULT '',
  `num` varchar(512) DEFAULT '',
  `parent_name` varchar(512) DEFAULT '',
  `period` datetime DEFAULT '0000-00-00 00:00:00',
  `ar` decimal(20,6) DEFAULT 0.000000,
  `year` datetime DEFAULT '0000-00-00 00:00:00',
  `crop` varchar(36) DEFAULT '',
  `active` smallint(6) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `unq_guid` (`guid`),
  KEY `idx_active` (`active`),
  KEY `idx_crop` (`crop`),
  KEY `idx_year` (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gps_car_lock`
--

DROP TABLE IF EXISTS `gps_car_lock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gps_car_lock` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(10) unsigned NOT NULL DEFAULT 0,
  `uid` int(10) unsigned NOT NULL DEFAULT 0,
  `dt` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_open` tinyint(3) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gps_car_log`
--

DROP TABLE IF EXISTS `gps_car_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gps_car_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gps` int(10) unsigned NOT NULL DEFAULT 0,
  `car` int(10) unsigned NOT NULL DEFAULT 0,
  `dt` date NOT NULL DEFAULT '2000-01-01',
  `flags` int(10) unsigned NOT NULL DEFAULT 0,
  `note` varchar(255) NOT NULL DEFAULT '',
  `ord_line` int(10) unsigned NOT NULL DEFAULT 0,
  `ctype` int(10) unsigned NOT NULL DEFAULT 0,
  `top` int(10) unsigned NOT NULL DEFAULT 0,
  `firm` int(10) unsigned NOT NULL DEFAULT 0,
  `tr` int(10) unsigned NOT NULL DEFAULT 0,
  `div` int(10) unsigned NOT NULL DEFAULT 0,
  `rate` int(10) unsigned NOT NULL DEFAULT 6,
  `mv_beg` int(10) unsigned NOT NULL DEFAULT 0,
  `mv_end` int(10) unsigned NOT NULL DEFAULT 0,
  `mv_calc` int(10) unsigned NOT NULL DEFAULT 0,
  `car_ix` int(10) unsigned NOT NULL DEFAULT 0,
  `car_in` int(10) unsigned NOT NULL DEFAULT 0,
  `ctype_ix` int(10) unsigned NOT NULL DEFAULT 0,
  `top_ix` int(10) unsigned NOT NULL DEFAULT 0,
  `firm_ix` int(10) unsigned NOT NULL DEFAULT 0,
  `firm_ixc` int(10) unsigned NOT NULL DEFAULT 0,
  `tr_ix` int(10) unsigned NOT NULL DEFAULT 0,
  `div_ix` int(10) unsigned NOT NULL DEFAULT 0,
  `dt_beg` datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
  `dt_end` datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UK_gps_dt` (`dt`,`gps`),
  KEY `IX_car` (`car`),
  KEY `IX_dt` (`dt`),
  KEY `IX_ord` (`ord_line`),
  KEY `IX_ctype` (`ctype`),
  KEY `IX_top` (`top`),
  KEY `IX_firm` (`firm`),
  KEY `IX_tr` (`tr`),
  KEY `IX_div` (`div`),
  KEY `IX_car_ix` (`car_ix`),
  KEY `IX_car_ixn` (`car_in`),
  KEY `IX_ctype_ix` (`ctype_ix`),
  KEY `IX_top_ix` (`top_ix`),
  KEY `IX_firm_ix` (`firm_ix`),
  KEY `IX_firm_ixc` (`firm_ixc`),
  KEY `IX_tr_ix` (`tr_ix`),
  KEY `IX_div_ix` (`div_ix`),
  KEY `IX_rate` (`rate`)
) ENGINE=InnoDB AUTO_INCREMENT=6701 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gps_car_log_history`
--

DROP TABLE IF EXISTS `gps_car_log_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gps_car_log_history` (
  `log_id` int(10) unsigned NOT NULL,
  `dt` datetime NOT NULL,
  `user` int(10) unsigned NOT NULL,
  `note` varchar(255) NOT NULL,
  PRIMARY KEY (`log_id`,`dt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gps_car_log_item`
--

DROP TABLE IF EXISTS `gps_car_log_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gps_car_log_item` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `log_id` int(10) unsigned NOT NULL DEFAULT 0,
  `tm` int(10) unsigned NOT NULL DEFAULT 0,
  `order_line` int(10) unsigned NOT NULL DEFAULT 0,
  `geo` int(11) NOT NULL DEFAULT 0,
  `dst` int(10) unsigned NOT NULL DEFAULT 0,
  `flags` int(10) unsigned NOT NULL DEFAULT 0,
  `tm_tot` int(10) unsigned NOT NULL DEFAULT 0,
  `tm_move` int(10) unsigned NOT NULL DEFAULT 0,
  `spd_sum` int(10) unsigned NOT NULL DEFAULT 0,
  `reason` int(10) unsigned NOT NULL DEFAULT 0,
  `tm_last` int(10) unsigned NOT NULL DEFAULT 0,
  `tm_eng` int(10) unsigned NOT NULL DEFAULT 0,
  `user` int(10) unsigned NOT NULL DEFAULT 0,
  `dt_last` datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
  `note` varchar(255) NOT NULL DEFAULT '',
  `note_spd` varchar(255) NOT NULL DEFAULT '',
  `geos` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UQ_log_tm` (`log_id`,`tm`),
  KEY `IX_work` (`log_id`),
  KEY `IX_tm` (`tm`),
  KEY `IX_ord_ln` (`order_line`),
  KEY `IX_reason` (`reason`),
  KEY `IX_last` (`tm_last`),
  KEY `IX_geo` (`geo`)
) ENGINE=InnoDB AUTO_INCREMENT=352910 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gps_car_log_item_history`
--

DROP TABLE IF EXISTS `gps_car_log_item_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gps_car_log_item_history` (
  `item_id` int(10) unsigned NOT NULL,
  `dt` datetime NOT NULL,
  `spd` int(10) unsigned NOT NULL DEFAULT 0,
  `user` int(10) unsigned NOT NULL DEFAULT 0,
  `reason` int(10) unsigned NOT NULL DEFAULT 0,
  `note` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`item_id`,`dt`,`spd`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gps_car_log_types`
--

DROP TABLE IF EXISTS `gps_car_log_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gps_car_log_types` (
  `car_type` int(10) unsigned NOT NULL,
  PRIMARY KEY (`car_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gps_car_status`
--

DROP TABLE IF EXISTS `gps_car_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gps_car_status` (
  `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL,
  `obsolete` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Не используется = 1',
  `color` varchar(6) NOT NULL DEFAULT '333333',
  `icon` varchar(20) NOT NULL DEFAULT 'question',
  `status` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gps_car_type`
--

DROP TABLE IF EXISTS `gps_car_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gps_car_type` (
  `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  `min_speed` int(11) NOT NULL DEFAULT 1 COMMENT 'Минимальная скорость, для определения движения в алертах',
  `ico` varchar(50) NOT NULL DEFAULT '',
  `code` varchar(10) NOT NULL DEFAULT '',
  `code2` varchar(10) NOT NULL DEFAULT '',
  `techop_group` int(10) unsigned NOT NULL DEFAULT 0,
  `upd` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ix` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8 COMMENT='Типы транспортных средств для контроля GPS';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gps_car_type_speed`
--

DROP TABLE IF EXISTS `gps_car_type_speed`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gps_car_type_speed` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type_id` int(10) unsigned NOT NULL,
  `spd` int(10) unsigned NOT NULL DEFAULT 0,
  `color` varchar(6) NOT NULL DEFAULT 'FFFFFF',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gps_carlist`
--

DROP TABLE IF EXISTS `gps_carlist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gps_carlist` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `firm` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Кто использует',
  `owner` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Владелец',
  `ts_name` varchar(255) NOT NULL DEFAULT '' COMMENT 'Наименование ТС',
  `ts_number` varchar(20) NOT NULL COMMENT 'Гос.номер',
  `ts_vin` varchar(30) NOT NULL DEFAULT '' COMMENT 'Номер кузова',
  `ts_gps_name` varchar(100) NOT NULL DEFAULT '',
  `ts_inv` varchar(50) NOT NULL DEFAULT '',
  `device` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Прибор',
  `date_create` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_modify` timestamp NOT NULL DEFAULT current_timestamp(),
  `techop_grp` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `ts_status` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'Статус ТС',
  `ts_type` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Тип ТС',
  `ts_hdr_width` decimal(10,2) NOT NULL DEFAULT 0.00,
  `note` varchar(255) NOT NULL DEFAULT '' COMMENT 'Примечания',
  `flags` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT 'Признаки (0x1=Удалено 0x2=Без нарядов)',
  `model` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Код модели',
  `nfc` int(10) unsigned NOT NULL DEFAULT 0,
  `contr` int(10) unsigned NOT NULL DEFAULT 0,
  `phone` int(10) unsigned NOT NULL DEFAULT 0,
  `upd` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ix` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'index by name',
  `ixn` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'index by number',
  PRIMARY KEY (`id`),
  KEY `IX_firm` (`firm`),
  KEY `IX_owner` (`owner`),
  KEY `IX_num` (`ts_number`),
  KEY `IX_type` (`ts_type`),
  KEY `IX_mdl_name` (`ts_name`),
  KEY `IX_mdl_id` (`model`),
  KEY `IX_device` (`device`),
  KEY `IX_contr` (`contr`),
  KEY `IX_nfc` (`nfc`),
  KEY `IX_phone` (`phone`),
  KEY `IX_status` (`ts_status`),
  KEY `IX_inv` (`ts_inv`)
) ENGINE=InnoDB AUTO_INCREMENT=692 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gps_carlist_changes`
--

DROP TABLE IF EXISTS `gps_carlist_changes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gps_carlist_changes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item` int(10) unsigned NOT NULL,
  `dt` datetime NOT NULL,
  `user` int(10) unsigned NOT NULL DEFAULT 0,
  `changes` longtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IX_item` (`item`),
  KEY `IX_date` (`dt`)
) ENGINE=InnoDB AUTO_INCREMENT=1924 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gps_device_types`
--

DROP TABLE IF EXISTS `gps_device_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gps_device_types` (
  `id` int(10) unsigned NOT NULL DEFAULT 0,
  `name` varchar(155) NOT NULL DEFAULT '',
  `uid2` int(10) unsigned NOT NULL DEFAULT 0,
  `hw_category` varchar(55) NOT NULL DEFAULT '',
  `tp` varchar(50) NOT NULL DEFAULT '',
  `up` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='HW Types (Wialon)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gps_devices`
--

DROP TABLE IF EXISTS `gps_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gps_devices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `flags` int(10) unsigned NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `imei` varchar(15) NOT NULL,
  `imei2` varchar(15) NOT NULL DEFAULT '',
  `dt` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'car cache',
  `tm` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'car cache',
  `gps_id` int(10) unsigned NOT NULL DEFAULT 0,
  `gps_guid` varchar(50) NOT NULL DEFAULT '',
  `type` int(10) unsigned NOT NULL DEFAULT 0,
  `phone` varchar(25) NOT NULL DEFAULT '',
  `phone2` varchar(25) NOT NULL DEFAULT '',
  `upd` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `type_ix` int(10) unsigned NOT NULL DEFAULT 0,
  `ixn` int(10) unsigned NOT NULL DEFAULT 0,
  `ixi` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UK_imei` (`imei`),
  KEY `inv` (`imei2`)
) ENGINE=InnoDB AUTO_INCREMENT=758 DEFAULT CHARSET=utf8 AVG_ROW_LENGTH=166 ROW_FORMAT=DYNAMIC COMMENT='Справочник устройств GPS';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gps_devices_changes`
--

DROP TABLE IF EXISTS `gps_devices_changes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gps_devices_changes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item` int(10) unsigned NOT NULL,
  `dt` datetime NOT NULL,
  `user` int(10) unsigned NOT NULL DEFAULT 0,
  `changes` longtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IX_item` (`item`),
  KEY `IX_date` (`dt`)
) ENGINE=InnoDB AUTO_INCREMENT=794 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gps_geofence`
--

DROP TABLE IF EXISTS `gps_geofence`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gps_geofence` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `res_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'resource_id',
  `gf_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'geofence_id',
  `ct` datetime NOT NULL DEFAULT '2000-01-01 00:00:00' COMMENT 'created',
  `n` varchar(255) NOT NULL DEFAULT '' COMMENT 'name',
  `t` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '1-line,2-poly,3-circle',
  `mt` datetime NOT NULL DEFAULT '2000-01-01 00:00:00' COMMENT 'last modified',
  `min_lng` decimal(13,10) NOT NULL DEFAULT 0.0000000000 COMMENT 'min_x',
  `min_lat` decimal(13,10) NOT NULL DEFAULT 0.0000000000 COMMENT 'min_y',
  `max_lng` decimal(13,10) NOT NULL DEFAULT 0.0000000000 COMMENT 'max_x',
  `max_lat` decimal(13,10) NOT NULL DEFAULT 0.0000000000 COMMENT 'max_y',
  `c` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'color ARGB',
  `tc` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'text color RGB',
  `ar` decimal(20,6) unsigned NOT NULL DEFAULT 0.000000 COMMENT 'area',
  `pr` decimal(20,6) unsigned NOT NULL DEFAULT 0.000000 COMMENT 'perimeter',
  `del` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'deleted',
  `d` varchar(512) NOT NULL DEFAULT '' COMMENT 'desc',
  `f` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `upd` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fld` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UK_res_gf_ct` (`res_id`,`gf_id`,`ct`),
  KEY `IX_upd` (`upd`),
  KEY `IX_f` (`f`)
) ENGINE=InnoDB AUTO_INCREMENT=2711 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gps_geofence_changes`
--

DROP TABLE IF EXISTS `gps_geofence_changes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gps_geofence_changes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item` int(10) unsigned NOT NULL,
  `dt` datetime NOT NULL,
  `user` int(10) unsigned NOT NULL DEFAULT 0,
  `changes` longtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IX_item` (`item`),
  KEY `IX_date` (`dt`)
) ENGINE=InnoDB AUTO_INCREMENT=4048 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gps_models`
--

DROP TABLE IF EXISTS `gps_models`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gps_models` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `brand` int(10) unsigned NOT NULL,
  `name` varchar(64) NOT NULL DEFAULT '',
  `tmp` varchar(64) NOT NULL DEFAULT '',
  `txt` varchar(155) NOT NULL DEFAULT '',
  `guid` varchar(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UK_guid` (`guid`),
  KEY `IX_brand` (`brand`)
) ENGINE=InnoDB AUTO_INCREMENT=673 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hrv_spr_modules`
--

DROP TABLE IF EXISTS `hrv_spr_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hrv_spr_modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dt_update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `function` longtext DEFAULT NULL,
  `module` varchar(32) DEFAULT '',
  `type` int(11) DEFAULT 1,
  `dm_roles` varchar(512) DEFAULT '',
  `dm_users` varchar(512) DEFAULT '',
  `status` int(11) DEFAULT 0,
  `m` varchar(5) DEFAULT '*',
  `h` varchar(5) DEFAULT '*',
  `d` varchar(5) DEFAULT '*',
  `mon` varchar(5) DEFAULT '*',
  `dow` varchar(5) DEFAULT '*',
  `cron_job` int(1) DEFAULT 0,
  `start` int(11) DEFAULT 0,
  `stop` int(11) DEFAULT 0,
  `pid` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_module` (`module`),
  KEY `idx_dt_update` (`dt_update`),
  KEY `idx_type` (`type`),
  KEY `idx_cron_job` (`cron_job`)
) ENGINE=InnoDB AUTO_INCREMENT=2943 DEFAULT CHARSET=cp1251;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kernel_navigator`
--

DROP TABLE IF EXISTS `kernel_navigator`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kernel_navigator` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT 0,
  `icon` varchar(128) DEFAULT '',
  `value` varchar(50) DEFAULT '',
  `role` varchar(50) DEFAULT '',
  `ord` int(11) DEFAULT 0,
  `project` int(11) DEFAULT 0,
  `module` varchar(64) DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `ord` (`ord`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=cp1251;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `measure_units`
--

DROP TABLE IF EXISTS `measure_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `measure_units` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guid` varchar(36) DEFAULT '',
  `name` varchar(512) DEFAULT '',
  `active` smallint(6) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `unq_guid` (`guid`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `production_rates`
--

DROP TABLE IF EXISTS `production_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `production_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guid` varchar(36) DEFAULT '',
  `name` varchar(512) DEFAULT '',
  `parent_name` varchar(512) DEFAULT '',
  `vehicle_model` varchar(36) DEFAULT '',
  `equipment_model` varchar(36) DEFAULT '',
  `active` smallint(6) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `unq_guid` (`guid`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `spr_brands`
--

DROP TABLE IF EXISTS `spr_brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `spr_brands` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(25) NOT NULL,
  `name_trans` varchar(25) NOT NULL DEFAULT '',
  `short` varchar(25) NOT NULL DEFAULT '',
  `short_trans` varchar(25) NOT NULL DEFAULT '',
  `extra` varchar(25) NOT NULL DEFAULT '',
  `extra_trans` varchar(25) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `spr_clusters`
--

DROP TABLE IF EXISTS `spr_clusters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `spr_clusters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '',
  `short` varchar(20) NOT NULL DEFAULT '',
  `flags` int(10) unsigned NOT NULL DEFAULT 0,
  `code` varchar(5) NOT NULL DEFAULT '',
  `guid` varchar(36) NOT NULL DEFAULT '',
  `upd` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ix` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'index by name',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `spr_firms`
--

DROP TABLE IF EXISTS `spr_firms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `spr_firms` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
  `guid` varchar(36) NOT NULL DEFAULT '' COMMENT 'GUID_1С',
  `okpo` char(9) NOT NULL DEFAULT '' COMMENT 'ОКПО_Предприятия',
  `name` varchar(100) NOT NULL COMMENT 'Нименование_Предприятия',
  `short` varchar(50) NOT NULL DEFAULT 'Краткое название для фильтров',
  `fullname` varchar(255) NOT NULL DEFAULT '' COMMENT 'Полное наименование',
  `cluster` int(11) NOT NULL DEFAULT 0 COMMENT 'Код Региона',
  `flags` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '1-Удал',
  `code` varchar(7) DEFAULT '' COMMENT 'Сокр.для Виалон',
  `code_en` varchar(7) NOT NULL DEFAULT '' COMMENT 'Сокр.латиницей',
  `code_1c` varchar(10) NOT NULL DEFAULT '' COMMENT 'Код_1С',
  `ws_name` varchar(100) NOT NULL DEFAULT '' COMMENT 'Имя WebService в 1С',
  `adr` varchar(255) NOT NULL DEFAULT '',
  `note` varchar(255) NOT NULL DEFAULT '' COMMENT 'Комментарии',
  `upd` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cluster_ix` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'order by cluster name',
  `ix` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'index by name',
  `ixc` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'index by cluster name',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `spr_rights`
--

DROP TABLE IF EXISTS `spr_rights`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `spr_rights` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `comment` varchar(255) NOT NULL DEFAULT '',
  `url` varchar(1024) DEFAULT '',
  `module` varchar(256) DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `spr_users`
--

DROP TABLE IF EXISTS `spr_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `spr_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `login` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL DEFAULT 'password',
  `inn` char(10) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL DEFAULT '',
  `middle_name` varchar(1000) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `gphone` varchar(50) NOT NULL DEFAULT '',
  `date_create` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_logon_time` datetime NOT NULL DEFAULT '2000-01-01 00:00:00',
  `state` tinyint(2) unsigned NOT NULL DEFAULT 0 COMMENT '0-активный,1-заблокированный',
  `kod1c` varchar(15) NOT NULL DEFAULT '',
  `author` int(10) unsigned NOT NULL DEFAULT 0,
  `address` varchar(255) NOT NULL DEFAULT '',
  `lette_reg` tinyint(2) unsigned NOT NULL DEFAULT 0,
  `flags` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '1 - отправка по e-mail,2 - СМС, 3 - Sender',
  `theme` varchar(25) NOT NULL DEFAULT 'redmond',
  `snd_lang` char(2) NOT NULL DEFAULT 'uk',
  `telegram_id` int(10) unsigned NOT NULL DEFAULT 0,
  `upd` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `UK_login` (`login`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `spr_users_rights`
--

DROP TABLE IF EXISTS `spr_users_rights`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `spr_users_rights` (
  `user_id` int(10) unsigned NOT NULL,
  `right_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`right_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `st_buffer_1c`
--

DROP TABLE IF EXISTS `st_buffer_1c`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `st_buffer_1c` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dt` datetime DEFAULT current_timestamp(),
  `obj` longtext DEFAULT NULL,
  `parse` smallint(6) DEFAULT 0,
  `err` longtext DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1964 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `techops`
--

DROP TABLE IF EXISTS `techops`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `techops` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guid` varchar(36) DEFAULT '',
  `name` varchar(512) DEFAULT '',
  `parent_name` varchar(512) DEFAULT '',
  `measure_unit` varchar(36) DEFAULT '',
  `active` smallint(6) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `vehicle_models`
--

DROP TABLE IF EXISTS `vehicle_models`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicle_models` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guid` varchar(36) DEFAULT '',
  `parent_name` varchar(512) DEFAULT '',
  `nomen_guid` varchar(36) DEFAULT '',
  `nomen_name` varchar(512) DEFAULT '',
  `is_special` smallint(6) DEFAULT 0,
  `car_type` varchar(512) DEFAULT '',
  `vechile_type_guid` varchar(36) DEFAULT '',
  `vechile_type_name` varchar(512) DEFAULT '',
  `active` smallint(6) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `unq_guid` (`guid`),
  KEY `idx_is_special` (`is_special`),
  KEY `idx_active` (`active`),
  KEY `idx_vechile_type_guid` (`vechile_type_guid`),
  KEY `idx_nomen_guid` (`nomen_guid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wialon_car_errors`
--

DROP TABLE IF EXISTS `wialon_car_errors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wialon_car_errors` (
  `imei` varchar(15) NOT NULL,
  `nm` varchar(512) NOT NULL DEFAULT '',
  `lic` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `frm` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `typ` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`imei`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wialon_car_undeviced`
--

DROP TABLE IF EXISTS `wialon_car_undeviced`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wialon_car_undeviced` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `n` varchar(512) NOT NULL DEFAULT '',
  `o` varchar(20000) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wialon_gps_items`
--

DROP TABLE IF EXISTS `wialon_gps_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wialon_gps_items` (
  `id` int(11) NOT NULL DEFAULT 0,
  `nm` varchar(64) DEFAULT '',
  `cls` int(11) DEFAULT 0,
  `gd` varchar(32) DEFAULT '',
  `mu` int(11) DEFAULT 0,
  `uid` varchar(32) DEFAULT '',
  `uid2` varchar(32) DEFAULT '',
  `hw` int(11) DEFAULT 0,
  `ph` varchar(13) DEFAULT '',
  `ph2` varchar(13) DEFAULT '',
  `psw` varchar(64) DEFAULT '',
  `act` int(11) DEFAULT 0,
  `dactt` int(11) DEFAULT 0,
  `prefix_1` varchar(7) DEFAULT '',
  `prefix_2` varchar(7) DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wialon_model_errors`
--

DROP TABLE IF EXISTS `wialon_model_errors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wialon_model_errors` (
  `bid` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `cnt` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`bid`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wialon_msg_error`
--

DROP TABLE IF EXISTS `wialon_msg_error`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wialon_msg_error` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'gps_id',
  `gps_id` int(10) unsigned NOT NULL DEFAULT 0,
  `dt` timestamp NOT NULL DEFAULT current_timestamp(),
  `err` varchar(512) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `IX_gps` (`gps_id`),
  KEY `IX_dt` (`dt`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wialon_resources`
--

DROP TABLE IF EXISTS `wialon_resources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wialon_resources` (
  `id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wialon_token`
--

DROP TABLE IF EXISTS `wialon_token`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wialon_token` (
  `host` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expire` datetime NOT NULL,
  PRIMARY KEY (`host`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `work_types`
--

DROP TABLE IF EXISTS `work_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `work_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guid` varchar(36) DEFAULT '',
  `name` varchar(512) DEFAULT '',
  `parent_name` varchar(512) DEFAULT '',
  `processing_type` varchar(36) DEFAULT '',
  `work_group` varchar(36) DEFAULT '',
  `measure_unit` varchar(36) DEFAULT '',
  `active` smallint(6) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `unq_guid` (`guid`),
  KEY `idx_processing_type` (`processing_type`),
  KEY `idx_work_group` (`work_group`),
  KEY `idx_measure_unit` (`measure_unit`),
  KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wx_server_log`
--

DROP TABLE IF EXISTS `wx_server_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wx_server_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dt` timestamp NULL DEFAULT current_timestamp(),
  `ip` varchar(32) DEFAULT '',
  `module` varchar(124) DEFAULT '',
  `status` varchar(64) DEFAULT '',
  `user_id` int(11) DEFAULT 0,
  `request` longtext DEFAULT NULL,
  `err` varchar(2048) DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=51070 DEFAULT CHARSET=cp1251;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wx_table_cfg`
--

DROP TABLE IF EXISTS `wx_table_cfg`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wx_table_cfg` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cid` int(11) NOT NULL DEFAULT 0,
  `col_id` varchar(32) NOT NULL DEFAULT '',
  `col_idx` int(11) NOT NULL DEFAULT 0,
  `width` int(11) NOT NULL DEFAULT 100,
  `visible` int(11) NOT NULL DEFAULT 1,
  `user_id` int(10) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `cid` (`cid`),
  KEY `col_id` (`col_id`)
) ENGINE=MyISAM AUTO_INCREMENT=27 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2020-04-27 10:31:31
