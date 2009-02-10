<?php
	
require ('./common.php');
require ('./functions.php');
require ('./simple_html_dom.php'); // HTML parser
	
db_init();

if (!$info_hash = mysql_real_escape_string(@$_REQUEST['info_hash']))
{
	die('Invalid request');
}

$r = mysql_query("
			SELECT comment, last_check FROM $tracker_stats WHERE info_hash = '$info_hash' LIMIT 1
	");

if (!$r)
{
	die('Torrent not exist');
}

$a = mysql_fetch_assoc($r);
/*
if (($a['last_check'] + $min_check_intrv) > TIMENOW)
{
	die('Torrent already checked. Wait '. ($a['last_check'] + $min_check_intrv) - TIMENOW) .' seconds for next check';
}
*/
$comment = trim($a['comment']);

if (is_url($comment))
{
	@ini_set('user_agent', $_SERVER['HTTP_USER_AGENT']);
	$html = @file_get_contents($comment, 0, stream_context_create(array('http' => array('timeout' => 10))));
	$html = str_replace('<wbr>', '', str_replace('</wbr>', '', $html));
	$obj  = str_get_html($html);	
	
	if (strpos($comment, 'interfilm.'))
	{
		//$name = strval($obj->find('tr td[align=left] font', 0)->plaintext);
		$name = strval($obj->find('table.tbt font', 0)->plaintext);
	}
	elseif (strpos($comment, 'kinozal.tv'))
	{
		$name = strval($obj->find('b font[color=green]', 1)->plaintext);
	}
	else
	{
		$name = strval($obj->find('.maintitle', 0)->plaintext);
	}
	
	if(!$name)
	{
		die('Could not obtain torrent name from url (tracker is down or not supported)');
	}
	
	$name = mysql_real_escape_string($name);
	
	mysql_query("UPDATE $tracker_stats SET 
					name       = '$name',
					last_check = ". TIMENOW ."
				WHERE info_hash = '$info_hash' 
				LIMIT 1");

	if (isset($_REQUEST['return']))
	{
		$name = iconv('CP1251', 'UTF-8', $name);
		echo "<a class=\"genmed\" href=\"$comment\" target=\"_blank\"><b>$name</b></a>";
	}
}
else die('This is not a URL');
