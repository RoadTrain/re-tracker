<?php

include_once (dirname(realpath(__FILE__)) . '/common.php');
include_once (dirname(realpath(__FILE__)) . '/functions.php');
include_once (dirname(realpath(__FILE__)) . '/simple_html_dom.php'); // HTML parser
include_once (dirname(realpath(__FILE__)) . '/checkme.class.php'); // HTML parser


if (!$torrent_id = intval(@$_REQUEST['torrent_id']))
{
	die('Invalid request');
}

$check = new CheckMe();
header('Content-Type: text/html; charset=utf-8', true);
die($check->updateName($torrent_id, TRUE, isset($_REQUEST['return'])));