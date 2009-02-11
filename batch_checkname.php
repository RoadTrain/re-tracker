<?php

if (!defined("CRON"))
{
	die();
}

require ('./common.php');
require ('./functions.php');
require ('./simple_html_dom.php'); // HTML parser
require ('./checkme.class.php'); // HTML parser
ini_set("display_errors", "On");

$work = $cache->get("batch_checkname", true, 600);
if ($work)
{
	return;
}
$cache->set("batch_checkname", true, 600);
$check = new CheckMe();
ini_set("max_execution_time", 600);
$check->batchUpdate();
$cache->rm("batch_checkname");
