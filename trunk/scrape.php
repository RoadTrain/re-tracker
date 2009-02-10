<?php

require ('./common.php');

$info_hash = isset($_GET['info_hash']) ? $_GET['info_hash'] : '';
$info_hash_hex = bin2hex($info_hash);

$torrent = $cache->get(PEERS_LIST_PREFIX . $info_hash_hex);

$output['files'][$info_hash] = array(
		'complete'    => (int) @$torrent['complete'],
		'incomplete'  => (int) @$torrent['incomplete'],
);

echo bencode($output);

exit;