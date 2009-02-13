<?php

if (!defined("CRON"))
{
	die();
}

include_once (dirname(realpath(__FILE__)) . '/common.php');
include_once (dirname(realpath(__FILE__)) . '/functions.php');
include_once (dirname(realpath(__FILE__)) . '/simple_html_dom.php'); // HTML parser
include_once (dirname(realpath(__FILE__)) . '/checkme.class.php'); // HTML parser


$work = $cache->get("batch_checkname");
if ($work)
{
	return;
}
$cache->set("batch_checkname", true, 120);
$check = new CheckMe();
ini_set("max_execution_time", 600);
$check->batchUpdate();
$cache->rm("batch_checkname");
