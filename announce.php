<?php

require ('./common.php');

$announce_interval = $cfg['announce_interval'];

//dummy_exit ($announce_interval); // Включение/отключение анонсера

db_init();

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
	'isp',
);
// Numeric
$input_vars_num = array(
	'port',
	'uploaded',
	'downloaded',
	'left',
	'numwant',
	'compact',
	'size',
);

// Init received data
// String
foreach ($input_vars_str as $var_name)
{
	$$var_name = isset($_GET[$var_name]) ? (string) $_GET[$var_name] : null;
}
// Numeric
foreach ($input_vars_num as $var_name)
{
	$$var_name = isset($_GET[$var_name]) ? (float) $_GET[$var_name] : null;
}

// Verify required request params (info_hash, peer_id, port, uploaded, downloaded, left)
if (!isset($info_hash) || strlen($info_hash) != 20)
{
	// Похоже, к нам зашли через браузер.
	// Вежливо отправим человека на инструкцию по псевдотрекеру.
	//echo ;
	//die;
	msg_die("Invalid info_hash: '$info_hash' length ".strlen($info_hash)." <meta http-equiv=refresh content=0;url=http://re-tracker.ru/>");
}
if (!isset($peer_id) || strlen($peer_id) != 20)
{
	msg_die('Invalid peer_id');
}
if (!isset($port) || $port < 0 || $port > 0xFFFF)
{
	msg_die('Invalid port');
}
if (!isset($uploaded) || $uploaded < 0)
{
	msg_die('Invalid uploaded value');
}
if (!isset($downloaded) || $downloaded < 0)
{
	msg_die('Invalid downloaded value');
}
if (!isset($left) || $left < 0)
{
	msg_die('Invalid left value');
}

// IP
$ip = $_SERVER['REMOTE_ADDR'];

if (!$cfg['ignore_reported_ip'] && isset($_GET['ip']) && $ip !== $_GET['ip'])
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
// Convert IP to HEX format
//$ip_sql = encode_ip($ip);

// ----------------------------------------------------------------------------
// Start announcer
//
$info_hash_hex = bin2hex($info_hash);
$info_hash_sql = rtrim(mysql_real_escape_string($info_hash), ' ');

// Peer unique id
$peer_hash = md5(
	rtrim($info_hash, ' ') . $peer_id . $ip . $port
);

// Get cached peer info from previous announce (last peer info)
$lp_info = $cache->get(PEER_HASH_PREFIX . $peer_hash);

// Drop fast announce
if ($lp_info && (!isset($event) || $event !== 'stopped'))
{
	drop_fast_announce($lp_info);
}

// It's seeder?
$seeder  = ($left == 0) ? 1 : 0;

// Stopped event
if ($event === 'stopped')
{
	mysql_query("DELETE FROM tracker WHERE peer_hash = '$peer_hash'") or msg_die("MySQL error: " . mysql_error());
	$cache->rm(PEER_HASH_PREFIX . $peer_hash);
	die();
}

// Escape strings
$name = mysql_real_escape_string($name);
$comment = mysql_real_escape_string($comment);

$torrent_id = 0;

$r = mysql_query("
			SELECT torrent_id FROM $tracker_stats WHERE info_hash = '$info_hash_hex' LIMIT 1
	") or msg_die("MySQL error: k" . mysql_error());
$c = @mysql_fetch_assoc($r);
$torrent_id = (int) $c['torrent_id'];

if (!$torrent_id)
{
	mysql_query("INSERT INTO $tracker_stats 
				(info_hash, reg_time, update_time, name, size, comment)
				VALUES 
				('$info_hash_hex', '". TIMENOW ."', '". TIMENOW ."', '$name', '$size', '$comment')
				") or msg_die("MySQL error: " . mysql_error() .' line '. __LINE__);	
	
	$torrent_id = mysql_insert_id();
}

$isp = explode(' ', $isp);
	
$ipv6type = verify_ip($ipv6);
if ($ipv6type !== 'ipv6') $ipv6 = null;

$sql_data = array(
	'torrent_id'   => $torrent_id,
	'peer_hash'    => $peer_hash,
	'ip'           => ($iptype == 'ipv4') ? $ip : $ipv4,
	'ipv6'         => ($iptype == 'ipv6') ? $ip : $ipv6,
	'port'         => $port,
	'seeder'       => $seeder,
	'update_time'  => TIMENOW,
	'name'         => $name,
	'comment'      => $comment,
	'pleft'        => $left,
	'downloaded'   => $downloaded,
	'uploaded'     => $uploaded,
	'size'         => $size,
	'city'         => !empty($isp[0]) ? $isp[0] : null,
	'isp'          => !empty($isp[1]) ? $isp[1] : null,
);

$columns = $values = $dupdate = array();

foreach ($sql_data as $column => $value)
{
	$columns[] = $column;
	$values[]  = "'" . mysql_real_escape_string($value) . "'";
	$dupdate[] = $column . " = '" . mysql_real_escape_string($value) . "'";
}

$columns_sql = implode(', ', $columns);
$values_sql = implode(', ', $values);
$dupdate_sql = implode(', ', $dupdate);

// Update peer info
mysql_query("INSERT INTO tracker ($columns_sql) VALUES ($values_sql)
			ON DUPLICATE KEY UPDATE $dupdate_sql") or msg_die("MySQL error: " . mysql_error() .' line '. __LINE__);

// Store peer info in cache
$lp_info = array(
	'downloaded'  => (float) $downloaded,
	'seeder'      => (int)   $seeder,
	'update_time' => (int)   TIMENOW,
	'uploaded'    => (float) $uploaded,
);

$lp_info_cached = $cache->set(PEER_HASH_PREFIX . $peer_hash, $lp_info, PEER_HASH_EXPIRE);

unset($sql_data, $columns, $values, $dupdate, $columns_sql, $values_sql, $dupdate_sql);

// Select peers
$output = $cache->get(PEERS_LIST_PREFIX . $torrent_id);

if (!$output)
{
	$limit = (int) (($numwant > $cfg['peers_limit']) ? $cfg['peers_limit'] : $numwant);
	
	$result = mysql_query("SELECT ip, ipv6, port, seeder
						   FROM $tracker 
						   WHERE torrent_id = '$torrent_id' 
						   LIMIT 200") 
	or msg_die("MySQL error: " . mysql_error() .' line '. __LINE__);
	
	$peerset  = array();
	$peerset6 = array();
	$seeders  = $leechers = $peers = 0;

	while ($row = mysql_fetch_assoc($result))
	{
		$peers++;
		
		if($row['seeder'])
		{
			$seeders++;
		}
		unset($row['seeder']);		
		
		if(!empty($row['ipv6']))
		{
			$row['ip'] = $row['ipv6'];
			$peerset6[] = $row;
		}
		else
		{
			$peerset[] = $row;
		}
	}
	$leechers = $peers - $seeders;
	
	mysql_query("UPDATE $tracker_stats SET
					seeders     = $seeders,
					leechers    = $leechers,
					update_time = '". TIMENOW ."',
					name        = IF(name = '', '$name', name),
					size        = IF(size = '', '$size', size),
					comment     = IF(comment = '', '$comment', comment)
				 WHERE torrent_id = $torrent_id
				") or msg_die("MySQL error: " . mysql_error() .' line '. __LINE__);		


	$output = array(
		'interval'     => (int) $announce_interval, // tracker config: announce interval (sec?)
		'min interval' => (int) 1, // tracker config: min interval (sec?)
		'peers'        => $peerset,
		'peers6'       => $peerset6,
		'complete'     => (int) $seeders,
		'incomplete'   => (int) $leechers,
	);
	
	$peers_list_cached = $cache->set(PEERS_LIST_PREFIX . $torrent_id, $output, PEERS_LIST_EXPIRE);
}

// Generate output
$compact_mode = ($cfg['compact_always'] || !empty($compact));

if ($compact_mode)
{
	$peers = '';

	foreach ($output['peers'] as $peer)
	{
		$peers .= pack('Nn', ip2long($peer['ip']), $peer['port']);
	}
	
	$output['peers'] = $peers;
	
	/*
	$peers6 = '';

	foreach ($output['peers6'] as $peer)
	{
		$peers6 .= pack('Nn', ip2long($peer['ip']), $peer['port']);
	}
	
	$output['peers6'] = $peers6;
	*/
}
/*
else
{
	$peers = array();

	foreach ($output['peers'] as $peer)
	{
		$peers[] = array(
			'ip'   => decode_ip($peer['ip']),
			'port' => intval($peer['port']),
		);
	}
}
*/

// Return data to client
echo bencode($output);

exit;