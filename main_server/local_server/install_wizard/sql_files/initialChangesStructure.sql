CREATE TABLE `%%PREFIX%%_comdef_changes` (
  `id_bigint` bigint(20) unsigned NOT NULL auto_increment,
  `user_id_bigint` bigint(20) unsigned NOT NULL,
  `service_body_id_bigint` bigint(20) unsigned NOT NULL,
  `lang_enum` varchar(7) NOT NULL,
  `change_date` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `object_class_string` varchar(64) NOT NULL,
  `change_name_string` tinytext,
  `change_description_text` text,
  `before_id_bigint` bigint(20) unsigned default NULL,
  `before_lang_enum` varchar(7) default NULL,
  `after_id_bigint` bigint(20) unsigned default NULL,
  `after_lang_enum` varchar(7) default NULL,
  `change_type_enum` varchar(32) NOT NULL,
  `before_object` blob,
  `after_object` blob,
  PRIMARY KEY  (`id_bigint`),
  KEY `user_id_bigint` (`user_id_bigint`),
  KEY `service_body_id_bigint` (`service_body_id_bigint`),
  KEY `lang_enum` (`lang_enum`),
  KEY `change_type_enum` (`change_type_enum`),
  KEY `change_date` (`change_date`),
  KEY `before_id_bigint` (`before_id_bigint`),
  KEY `after_id_bigint` (`after_id_bigint`),
  KEY `before_lang_enum` (`before_lang_enum`),
  KEY `after_lang_enum` (`after_lang_enum`),
  KEY `object_class_string` (`object_class_string`)
) DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
