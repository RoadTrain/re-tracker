<?php

include_once (dirname(realpath(__FILE__)).'/common.php');

$info_hash = isset($_GET['info_hash']) ? $_GET['info_hash'] : '';
$info_hash_hex = bin2hex($info_hash);

$torrent = array('seeders' = 0, 'leechers' = 0);

$on = true;

if ($on)
{
	$from_db = $db->fetch_row("
		SELECT seeders, leechers FROM $tracker_stats WHERE info_hash = '$info_hash_hex' LIMIT 1
	");
	
	$torrent = array_merge($torrent, $from_db);
}

$output['files'][$info_hash] = array(
		'complete'    => (int) $torrent['seeders'],
		'incomplete'  => (int) $torrent['leechers'],
);

echo bencode($output);
exit;