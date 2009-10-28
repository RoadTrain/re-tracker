<?php

$cfg = array();
$tracker_tbl = 'tracker';

// DB
$cfg['tr_db_type'] = 'mysql';                   // Available db types: sqlite, mysql
// Support of sqlite was not tested.

// DB - MySQL
$cfg['tr_db']['mysql'] = array(
	'dbhost'   => 'localhost',
	'dbuser'   => 'root',
	'dbpasswd' => 'root',
	'dbname'   => 'retracker',
	'pconnect' => false,
	'log_name' => 'MySQL',
);

// DB - SQLite
$cfg['tr_db']['sqlite'] = array(
	'db_file_path' => 'C:\Program Files\VertrigoServ\Sqlitemanager\tr_db.sqlite',       // preferable on tmpfs
	'table_schema' => "CREATE TABLE $tracker_tbl (
						peer_hash CHAR(32),
						info_hash CHAR(20),
						ip        CHAR(8),
						ipv6      CHAR(32),
						port      SMALLINT(5),
						seeder    TINYINT(1) DEFAULT '0',
						update_time INT(11),
						PRIMARY KEY (peer_hash, info_hash)
						)",
	'table_index'  => "CREATE INDEX tracker_info_hash ON $tracker_tbl(info_hash);"
);

$tracker = 'tracker';
$tracker_stats = 'tracker_stats';
$search_intrv  = 10; // interval for search attempts
$search_opt_keep = 15 * 86400; // seconds to keep search options in cookie
$min_check_intrv = 14400; // min inverval to check torrent name

// Tracker
$cfg['announce_interval']  = 3600;
$cfg['expire_factor']      = 2;
$cfg['peers_limit']        = 100; // Limit peers to select from DB
$cfg['cleanup_interval']   = 2400; // Interval to execute cleanup
$cfg['compact_always']     = false; // Enable compact mode always (don't check clien capability)
$cfg['ignore_reported_ip'] = false; // Ignore IP from GET query
$cfg['allow_internal_ip']  = true; // Allow IP from local, etc
$cfg['verify_reported_ip'] = false; // Verify reported IP?
$cfg['base_path']		   = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : dirname(realpath(__FILE__)); // Without end slash
$cfg['base_url']		   = "/"; // Base url where retracker is located. End slash required. Example: /my_own_path/
$cfg['skip_empty']		   = true; // Do not accept announce if statistic data is empty (name, comment, size)

// Cache
$cfg['cache_type'] = 'memcached'; // Available cache types: none, memcached
// Cache type "none" is not recommended for use. Xcache support is planned.
$cfg['cache']['memcached'] = array(
	'host'         => '127.0.0.1',
	'port'         => 11211,
	'pconnect'     => true, // use persistent connection
	'con_required' => true
); // exit script if can't connect

define('DUMMY_PEER', pack('Nn', ip2long('10.254.254.247'), 64765));

define('PEER_HASH_PREFIX', 'peer_');
define('PEERS_LIST_PREFIX', 'peers_list_');
define('PEERS_DATA_PREFIX', 'peers_data_');

define('PEER_HASH_EXPIRE', round($cfg['announce_interval'] * (0.85 * $cfg['expire_factor']))); // sec
define('PEERS_LIST_EXPIRE', round($cfg['announce_interval'] * 0.6)); // sec
define('PEERS_DATA_EXPIRE', 1500); // sec
define('TRACKERS_CACHE_EXPIRE', 3600); // sec
define('TORRENTS_EXPIRE', 30 * 86400); // 30 days

// Misc
define('DBG_LOG', false); // Debug log

// Torrents list
define('STATS_EXPIRE', 1200);  // sec
	
// Redefine config variables from $cfg in that file for safe upgrade from svn
@include_once (dirname(realpath(__FILE__)).'/config.local.php');