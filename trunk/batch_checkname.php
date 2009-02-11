<?php

if(!defined("CRON")) {
	die();
}

require ('./common.php');
require ('./functions.php');
require ('./simple_html_dom.php'); // HTML parser
require ('./checkme.class.php'); // HTML parser
ini_set("display_errors","On");

$check = new CheckMe();
ini_set("max_execution_time",600);
$check->batchUpdate();
