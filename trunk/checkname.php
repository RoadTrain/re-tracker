<?php

require ('./common.php');
require ('./functions.php');
require ('./simple_html_dom.php'); // HTML parser
require ('./checkme.class.php'); // HTML parser

if (!$torrent_id = intval(@$_REQUEST['torrent_id']))
{
	die('Invalid request');
}

$check = new CheckMe();

die($check->updateName($torrent_id, TRUE, isset($_REQUEST['return'])));