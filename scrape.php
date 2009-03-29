<?php

include_once (dirname(realpath(__FILE__)).'/common.php');

db_init();

$info_hash = isset($_GET['info_hash']) ? $_GET['info_hash'] : '';
$info_hash_hex = bin2hex($info_hash);

$result = mysql_query("
	SELECT seeders, leechers FROM $tracker_stats WHERE info_hash = '$info_hash_hex' LIMIT 1
") or msg_die("MySQL error: k" . mysql_error());

$torrent = mysql_fetch_assoc($result);

$output['files'][$info_hash] = array(
		'complete'    => (int) @$torrent['seeders'],
		'incomplete'  => (int) @$torrent['leechers'],
);

echo bencode($output);
exit;