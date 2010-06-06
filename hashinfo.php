<?php
ini_set("display_errors", "Off");
ini_set("log_errors", "Off");

header('Content-Type: text/plain; charset=UTF-8', true);

if (0)
{
	echo "Temporary Disabled";
	die();
}

include_once (dirname(realpath(__FILE__)) . '/common.php');

// Recover info_hash
if (isset($_GET['?info_hash']) && !isset($_GET['info_hash']))
{
	$_GET['info_hash'] = $_GET['?info_hash'];
}
// Recover info_hex
if (isset($_GET['?info_hex']) && !isset($_GET['info_hex']))
{
	$_GET['info_hex'] = $_GET['?info_hex'];
}

// Input vars
// Init received data
$info_hash = isset($_GET['info_hash']) ? trim((string)$_GET['info_hash']) : NULL;
$info_hex = isset($_GET['info_hex']) ? trim((string)$_GET['info_hex']) : NULL;
$json = isset($_GET['json']);

// Verify required request params
if (strlen($info_hash) != 20)
{
	if (!$info_hex)
	{
		// Redirect for browsers
		echo "Wrong InfoHash";
		die();
	}
}
else
{
	$info_hex = bin2hex($info_hash);
}

if (strlen($info_hex) != 40)
{
	// Redirect for browsers
	echo "Wrong InfoHex";
	die();
}
// ----------------------------------------------------------------------------
// Start search
//
$info_hex_sql = rtrim($db->escape(strtolower($info_hex)), ' ');

$torrent_info = $cache->get('torrent_' . md5($info_hex));

if (!is_array($torrent_info) || !sizeof($torrent_info))
{
	$row = $db->fetch_row("SELECT `name`,`comment`,`size`
							FROM `" . $tracker_stats . "`
							WHERE `info_hash` = '" . $info_hex_sql . "'
							LIMIT 1");
	$torrent_info['name'] = isset($row['name']) ? $row['name'] : '';
	$torrent_info['comment'] = isset($row['comment']) ? $row['comment'] : '';
	$torrent_info['size'] = isset($row['size']) ? (int)$row['size'] : 0;
	
	$cache->set('torrent_' . md5($info_hex), $torrent_info);
}

// Return data to client
if ($json)
{
	echo json_encode($torrent_info);
}
else
{
	echo bencode($torrent_info);
}

exit();