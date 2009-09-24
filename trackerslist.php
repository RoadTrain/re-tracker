<?php
ob_start();
include_once (dirname(realpath(__FILE__)) . '/common.php');
if (!isset($_GET['export']))
{
	$list = $cache->get("trackers_list");
	if (empty($list))
	{
		include_once (dirname(realpath(__FILE__)) . '/functions.php');
		$list = get_trackers(TRUE);
	}
	ob_clean();
	header('Content-Type: text/plain; charset=UTF-16', true);
	header('Content-Length: ' . strlen($list));
	die($list);
}
else
{
	header('Content-Type: text/plain; charset=UTF-8', true);
	$sql = $cache->get("trackers_export");
	if (empty($sql))
	{
		include_once (dirname(realpath(__FILE__)) . '/functions.php');
		$citys = GetCitys();
		$sql = array();
		$sql[] = "-- Updated once of day";
		$sql[] = "-- Last update: ".date("d.m.Y H:i");
		$sql[] = "-- Codepage is UTF-8";
		$sql[] = "";
		$sql[] = "TRUNCATE TABLE `tracker_city`;";
		
		foreach ($citys as $id => $city)
		{
			$sql[] = "INSERT INTO `tracker_city` VALUES(" . $id . ",'" . $city . "');";
		}
		
		$sql[] = "";
		$sql[] = "TRUNCATE TABLE `tracker_provider`;";
		$providers = GetProviders();
		foreach ($providers as $id => $isp)
		{
			$sql[] = "INSERT INTO `tracker_provider` VALUES(" . $id . ",'" . $isp . "');";
		}
		
		$sql[] = "";
		$sql[] = "TRUNCATE TABLE `tracker_retrackers`;";
		$retrackers = GetRetrackers(NULL, NULL, FALSE);
		foreach ($retrackers as $retracker)
		{
			if (isset($retracker['id']))
			{ // re-tracker.ru is not needed
				$sql[] = "INSERT INTO `tracker_retrackers` VALUES(NULL, " . $retracker['id_city'] . ", " . $retracker['id_prov'] . ", " . $retracker['date'] . ", '" . $db->escape($retracker['retracker']) . "', 1, '', '', '', '');";
			}
		}
		$sql = implode("\r\n", $sql);
		$cache->set("trackers_export", $sql, 86400);
	}
	
	header('Content-Length: ' . strlen($sql));
	die($sql);
}