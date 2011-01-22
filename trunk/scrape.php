<?php

include_once (dirname(realpath(__FILE__)) . '/common.php');

$info_hash = isset($_GET['info_hash']) ? $_GET['info_hash'] : '';

$torrent = array(
		'seeders' => 0,
		'leechers' => 0
);

$empty = 'd5:filesd0:d8:completei0e10:incompletei0eeee';

$on = true;

if ($on && strlen($info_hash) == 20)
{
	$info_hash_hex = bin2hex($info_hash);
	$from_db = $db->fetch_row("
		SELECT seeders, leechers FROM $tracker_stats WHERE info_hash = '$info_hash_hex' LIMIT 1
	");
	
	$torrent = array_merge($torrent, (array)$from_db);
}
else
{
	echo $empty;
	exit();
}

$output['files'][$info_hash] = array(
		'complete' => (int)$torrent['seeders'],
		'incomplete' => (int)$torrent['leechers']
);

echo bencode($output);
exit();