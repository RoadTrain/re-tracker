<?php

class CheckMe
{

	public $timeout = 10;

	public $one_shot = 100;

	public function __construct()
	{

		if (!defined('TIMENOW'))
		{
			define('TIMENOW', time());
		}
		db_init();
	}

	public function updateName($torrent_id, $update = true, $return = false)
	{

		$torrent_id = intval($torrent_id);
		if (!$torrent_id)
		{
			return $return ? 'Invalid request' : FALSE;
		}

		$sql = "SELECT `comment`, `last_check` FROM `tracker_stats` WHERE `torrent_id` = $torrent_id LIMIT 1";
		$r = mysql_query($sql);

		if (!$r)
		{
			return $return ? 'Torrent not exist' : FALSE;
		}

		$a = mysql_fetch_assoc($r);
		$comment = trim($a['comment']);
		if (is_url($comment))
		{
			$user_aget = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9.0.6) Gecko/2009020911 Ubuntu/8.10 (intrepid) Firefox/3.0.6";

			@ini_set('user_agent', $_SERVER['HTTP_USER_AGENT']);
			$html = @file_get_contents($comment, 0, stream_context_create(array('http'=>array('timeout'=>$this->timeout))));
			$html = str_replace('<wbr>', '', str_replace('</wbr>', '', $html));

			$obj = str_get_html($html);

			if (strpos($comment, 'interfilm.'))
			{
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

			if (!$name)
			{
				return $return ? 'Could not obtain torrent name from url (tracker is down or not supported)' : FALSE;
			}

			if ($update)
			{
				$name = mysql_real_escape_string($name);

				$sql = "UPDATE `tracker_stats` SET
					name       = '" . $name . "',
					last_check = " . TIMENOW . "
				WHERE torrent_id = " . $torrent_id . "
				LIMIT 1";
				mysql_query($sql);
			}

			if ($return)
			{
				$name = iconv('CP1251', 'UTF-8', $name);
				return '<a class="genmed" href="' . $comment . '" target="_blank"><b>' . $name . '</b></a>';
			}

		}
		else
		{
			return $return ? 'This is not a URL' : FALSE;
		}
		return FALSE;
	}
}
