DROP TABLE IF EXISTS `tracker`;
CREATE TABLE `tracker` (
  `info_hash` char(40) collate utf8_bin NOT NULL,
  `peer_hash` char(32) collate utf8_bin NOT NULL,
  `ip` char(15) collate utf8_bin NOT NULL,
  `ipv6` char(39) collate utf8_bin NOT NULL,
  `port` int(11) NOT NULL,
  `seeder` tinyint(1) NOT NULL,
  `update_time` int(11) NOT NULL,
  `name` varchar(255) collate utf8_bin default NULL,
  `size` bigint(20) NOT NULL,
  `comment` varchar(255) collate utf8_bin default NULL,
  `pleft` bigint(16) default NULL,
  `downloaded` bigint(16) NOT NULL,
  `uploaded` bigint(16) NOT NULL default '0',
  `city` mediumint(2) NOT NULL,
  `isp` mediumint(2) NOT NULL,
  PRIMARY KEY  (`peer_hash`),
  KEY `info_hash` (`info_hash`),
  FULLTEXT KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `tracker_stats`;
CREATE TABLE `tracker_stats` (
  `info_hash` char(40) NOT NULL,
  `seeders` mediumint(8) NOT NULL default '0',
  `leechers` mediumint(8) NOT NULL default '0',
  `reg_time` int(11) NOT NULL default '0',
  `update_time` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `size` bigint(20) NOT NULL,
  `comment` varchar(255) NOT NULL,
  `check_name` tinyint(1) NOT NULL default '0',
  `last_check` int(11) NOT NULL default '0',
  PRIMARY KEY  (`info_hash`),
  KEY `reg_time` (`reg_time`),
  KEY `update_time` (`update_time`),
  KEY `seeders` (`seeders`),
  KEY `leechers` (`leechers`),
  FULLTEXT KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;