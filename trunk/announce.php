<?php
ini_set("display_errors", "Off");
ini_set("log_errors", "Off");

if (0)
{
	$query = http_build_query($_GET);
	header("HTTP/1.1 301 Moved Permanently");
	header("Location: http://open.tracker.thepiratebay.org:80/announce?$query");
	exit();
}

include_once (dirname(realpath(__FILE__)) . '/common.php');

$announce_interval = $cfg['announce_interval'];

if (!$cache->used || ($cache->get('next_cleanup') < TIMENOW))
{
	cleanup();
}

// Recover info_hash
if (isset($_GET['?info_hash']) && !isset($_GET['info_hash']))
{
	$_GET['info_hash'] = $_GET['?info_hash'];
}

// Input var names
// String
$input_vars_str = array(
		'info_hash',
		'peer_id',
		'ipv4',
		'ipv6',
		'event',
		'name',
		'comment',
		'isp'
);
// Numeric
$input_vars_num = array(
		'port',
		'numwant',
		'left',
		'compact',
		'size'
);

// Init received data
// String
foreach ($input_vars_str as $var_name)
{
	$$var_name = isset($_GET[$var_name]) ? (string)$_GET[$var_name] : null;
}
// Numeric
foreach ($input_vars_num as $var_name)
{
	$$var_name = isset($_GET[$var_name]) ? (float)$_GET[$var_name] : null;
}

// Verify required request params (info_hash, peer_id, port, uploaded, downloaded, left)
if (!isset($info_hash) || strlen($info_hash) != 20)
{
	// Redirect for browsers
	msg_die("Invalid info_hash: '" . bin2hex($info_hash) . "', length " . strlen($info_hash) . "
			<meta http-equiv=refresh content=0;url=http://re-tracker.ru/>");
}
if (!isset($peer_id) || strlen($peer_id) != 20)
{
	msg_die('Invalid peer_id');
}
if (!isset($port) || $port < 0 || $port > 0xFFFF)
{
	msg_die('Invalid port');
}
if (!isset($left) || $left < 0)
{
	msg_die('Invalid left value');
}

// IP
$ip = $_SERVER['REMOTE_ADDR'];

if (!$cfg['ignore_reported_ip'] && isset($_GET['ip']) && $ip !== $_GET['ip'] && !strpos($_GET['ip'], ':'))
{
	if (!$cfg['verify_reported_ip'])
	{
		$ip = $_GET['ip'];
	}
	else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches))
	{
		foreach ($matches[0] as $x_ip)
		{
			if ($x_ip === $_GET['ip'])
			{
				if (!$cfg['allow_internal_ip'] && preg_match("#^(10|172\.16|192\.168)\.#", $x_ip))
				{
					break;
				}
				$ip = $x_ip;
				break;
			}
		}
	}
}

// Check that IP format is valid
if (!$iptype = verify_ip($ip))
{
	msg_die("Invalid IP: $ip");
}

// ----------------------------------------------------------------------------
// Start announcer
//
$info_hash_hex = bin2hex($info_hash);
$info_hash_sql = rtrim($db->escape($info_hash), ' ');

// Peer unique id
$peer_hash = md5(rtrim($info_hash, ' ') . $peer_id . $ip . $port);

// Get cached peer info from previous announce (last peer info)
$lp_info = $cache->get(PEER_HASH_PREFIX . $peer_hash);

// Drop fast announce
if ($lp_info && (!isset($event) || $event !== 'stopped'))
{
	drop_fast_announce($lp_info);
}

// It's seeder?
$seeder = ($left == 0) ? 1 : 0;

// Stopped event
if ($event === 'stopped')
{
	$db->query("DELETE FROM $tracker WHERE peer_hash = '$peer_hash'");
	$cache->rm(PEER_HASH_PREFIX . $peer_hash);
	die();
}

// Escape strings.
//if (detect_encoding($name)=='windows-1251') {
//	$name = iconv("windows-1251","utf-8",$name);
//}
$name = $db->escape($name);
//if (detect_encoding($comment)=='windows-1251') {
//	$comment = iconv("windows-1251","utf-8",$comment);
//}
$comment = $db->escape($comment);

$torrent_id = isset($lp_info['torrent_id']) ? $lp_info['torrent_id'] : 0;

if (!$torrent_id)
{
	$row = $db->fetch_row(" SELECT torrent_id
							FROM $tracker_stats
							WHERE info_hash = '$info_hash_hex'
							LIMIT 1");
	$torrent_id = (int)$row['torrent_id'];
}

if (!$torrent_id)
{
	$db->query("INSERT INTO $tracker_stats
				(info_hash, reg_time, update_time, name, size, comment)
				VALUES
				('$info_hash_hex', '" . TIMENOW . "', '" . TIMENOW . "', '$name', '$size', '$comment')
				");
	
	$torrent_id = mysql_insert_id();
}

$isp = explode(' ', $isp);

$ipv6 = ($iptype == 'ipv6') ? encode_ip($ip) : ((verify_ip($ipv6) == 'ipv6') ? encode_ip($ipv6) : null);
$ipv4 = ($iptype == 'ipv4') ? encode_ip($ip) : ((verify_ip($ipv4) == 'ipv4') ? encode_ip($ipv4) : null);

$sql_data = array(
		'torrent_id' 	=> $torrent_id,
		'peer_hash' 	=> $peer_hash,
		'ip' 			=> $ipv4,
		'ipv6' 			=> $ipv6,
		'port' 			=> $port,
		'seeder' 		=> $seeder,
		'update_time' 	=> TIMENOW,
		'city' 			=> !empty($isp[0]) ? $isp[0] : null,
		'isp' 			=> !empty($isp[1]) ? $isp[1] : null
);

$columns = $values = $dupdate = array();

foreach ($sql_data as $column => $value)
{
	$columns[] = $column;
	$values[] = "'" . $db->escape($value) . "'";
}

$columns_sql = implode(', ', $columns);
$values_sql = implode(', ', $values);

// Update peer info
$db->query("REPLACE INTO $tracker ($columns_sql) VALUES ($values_sql)");

// Store peer info in cache
$lp_info = array(
		'torrent_id' => (int)$torrent_id,
		'update_time' => (int)TIMENOW
);

$lp_info_cached = $cache->set(PEER_HASH_PREFIX . $peer_hash, $lp_info, PEER_HASH_EXPIRE);

unset($sql_data, $columns, $values, $columns_sql, $values_sql);

// Select peers
$output = $cache->get(PEERS_LIST_PREFIX . $torrent_id);

if (!$output)
{
	$limit = (int)(($numwant > $cfg['peers_limit']) ? $cfg['peers_limit'] : $numwant);
	$compact_mode = ($cfg['compact_always'] || !empty($compact));
	
	$rowset = $db->fetch_rowset("
		SELECT ip, ipv6, port
		FROM $tracker
		WHERE torrent_id = $torrent_id
		ORDER BY " . $db->random_fn . "
		LIMIT $limit
	");
	
	// Pack peers if compact mode
	if ($compact_mode)
	{
		$peerset = $peerset6 = '';
		
		foreach ($rowset as $peer)
		{
			if (!empty($peer['ip']))
			{
				$peerset .= pack('Nn', ip2long(decode_ip($peer['ip'])), $peer['port']);
			}
			if (!empty($peer['ipv6']))
			{
				$peerset6 .= pack('H32n', $peer['ipv6'], $peer['port']);
			}
		}
	}
	else
	{
		$peerset = $peerset6 = array();
		
		foreach ($rowset as $peer)
		{
			if (!empty($peer['ip']))
			{
				$peerset[] = array(
						'ip' 	=> decode_ip($peer['ip']),
						'port' 	=> intval($peer['port'])
				);
			}
			if (!empty($peer['ipv6']))
			{
				$peerset6[] = array(
						'ip' 	=> decode_ip($peer['ipv6']),
						'port' 	=> intval($peer['port'])
				);
			}
		}
	}
	
	$row = $db->fetch_row("SELECT SUM(seeder) AS seeders, COUNT(*) AS peers
						   FROM $tracker
						   WHERE torrent_id = '$torrent_id' ");
	
	$seeders = (int)$row['seeders'];
	$peers = (int)$row['peers'];
	$leechers = $peers - $seeders;
	
	$db->query("UPDATE $tracker_stats SET
					seeders     = $seeders,
					leechers    = $leechers,
					update_time = '" . TIMENOW . "',
					name        = IF(name = '', '$name', name),
					size        = IF(size = '', '$size', size),
					comment     = IF(comment = '', '$comment', comment)
				 WHERE torrent_id = $torrent_id
				") or msg_die("MySQL error: " . mysql_error() . ' line ' . __LINE__);
	
	// Generate output
	$output = array(
			'interval' 		=> (int)$announce_interval,  // tracker config: announce interval (sec?)
			'min interval' 	=> (int)1,  // tracker config: min interval (sec?)
			'peers' 		=> $peerset,
			'peers6' 		=> $peerset6,
			'complete' 		=> (int)$seeders,
			'incomplete' 	=> (int)$leechers
	);
	
	$peers_list_cached = $cache->set(PEERS_LIST_PREFIX . $torrent_id, $output, PEERS_LIST_EXPIRE);
}

// Return data to client
echo bencode($output);

exit();