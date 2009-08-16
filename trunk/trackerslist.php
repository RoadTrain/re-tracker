<?php
ob_start();
include_once (dirname(realpath(__FILE__)) . '/common.php');
$list = $cache->get("trackers_list");
if (empty($list))
{
	include_once (dirname(realpath(__FILE__)) . '/functions.php');
	get_trackers();
	$list = $cache->get("trackers_list");
}
ob_clean();
header('Content-Type: text/plain; charset=UTF-16', true);
header('Content-Length: ' . strlen($list));
die($list);
?>