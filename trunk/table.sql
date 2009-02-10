DROP TABLE IF EXISTS `tracker`;
CREATE TABLE `tracker` (
  `info_hash` char(40) collate utf8_bin NOT NULL,
  `seeders` mediumint(8) NOT NULL default '0',
  `leechers` mediumint(8) NOT NULL default '0',
  `peer_hash` char(32) collate utf8_bin NOT NULL,
  `ip` char(15) collate utf8_bin NOT NULL,
  `port` int(11) NOT NULL,
  `seeder` tinyint(1) NOT NULL,
  `update_time` int(11) NOT NULL,
  `name` varchar(255) collate utf8_bin default NULL,
  `size` bigint(20) NOT NULL,
  `tracker` varchar(255) collate utf8_bin default NULL,
  `comment` varchar(255) collate utf8_bin default NULL,
  `pleft` bigint(16) default NULL,
  `downloaded` bigint(16) NOT NULL,
  `city` mediumint(2) NOT NULL,
  `isp` mediumint(2) NOT NULL,
  PRIMARY KEY  (`peer_hash`),
  KEY `info_hash` (`info_hash`),
  FULLTEXT KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;