DROP TABLE IF EXISTS `tracker`;
CREATE TABLE IF NOT EXISTS `tracker` (
  `torrent_id` mediumint(9) NOT NULL,
  `peer_hash` varchar(32) COLLATE utf8_bin NOT NULL DEFAULT '',
  `ip` varchar(8) COLLATE utf8_bin NOT NULL DEFAULT '',
  `ipv6` char(32) COLLATE utf8_bin NOT NULL,
  `port` smallint(5) unsigned NOT NULL DEFAULT '0',
  `seeder` tinyint(1) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  `city` mediumint(2) NOT NULL DEFAULT '0',
  `isp` mediumint(2) NOT NULL DEFAULT '0',
  PRIMARY KEY (`peer_hash`),
  KEY `torrent_id` (`torrent_id`),
  KEY `isp` (`city`,`isp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

DROP TABLE IF EXISTS `tracker_stats`;
CREATE TABLE IF NOT EXISTS `tracker_stats` (
  `torrent_id` mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `info_hash` char(40) NOT NULL DEFAULT '',
  `seeders` mediumint(8) NOT NULL DEFAULT '0',
  `leechers` mediumint(8) NOT NULL DEFAULT '0',
  `reg_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL DEFAULT '',
  `size` bigint(20) NOT NULL DEFAULT '0',
  `comment` varchar(255) NOT NULL DEFAULT '',
  `last_check` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`torrent_id`),
  UNIQUE KEY `info_hash` (`info_hash`),
  KEY `reg_time` (`reg_time`),
  KEY `seeders` (`seeders`),
  KEY `leechers` (`leechers`),
  FULLTEXT KEY `name` (`name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tracker_city`;
CREATE TABLE IF NOT EXISTS `tracker_city` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tracker_provider`;
CREATE TABLE IF NOT EXISTS `tracker_provider` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tracker_retrackers`;
CREATE TABLE IF NOT EXISTS `tracker_retrackers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_city` int(11) NOT NULL,
  `id_prov` int(11) NOT NULL,
  `date` int(11) NOT NULL,
  `retracker` varchar(255) NOT NULL,
  `allow` int(11) NOT NULL DEFAULT '0',
  `new_retracker` varchar(255) NOT NULL,
  `uid` varchar(255) NOT NULL,
  `email` varchar(50) NOT NULL,
  `key` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_city` (`id_city`,`id_prov`,`retracker`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;