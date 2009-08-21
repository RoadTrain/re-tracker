<?php

class CheckMe
{
	public $timeout = 10;

	public $one_shot = 10;

	public $user_agent = NULL;

	/** List of private trackers, we can`t get torrent name from that trackers */
	protected $blacklist = array();

	public function __construct()
	{
		if (!defined('TIMENOW'))
		{
			define('TIMENOW', time());
		}
		
		if ($this->user_agent == NULL)
		{
			$this->user_agent = isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT'] ? $_SERVER['HTTP_USER_AGENT'] : "Mozilla/5.0 (X11; U; Linux x86_64; en-US; rv:1.9.0.10) Gecko/2009042523 Ubuntu/9.04 (jaunty) Firefox/3.0.10";
		}
		
		$this->blacklist[] = "beetorrent.homeip.net";
		$this->blacklist[] = "download.kanet.ru";
		$this->blacklist[] = "bt.od.ua";
		//$this->blacklist[] = "free-torrents.org";
		//$this->blacklist[] = "tseed.ru";
		$this->blacklist[] = "www.nnm-club.org";
		$this->blacklist[] = "torrent.elcomnet.ru";
		$this->blacklist[] = "torrent.dml";
		$this->blacklist[] = "etorrent.ru";
	}

	public function updateName($torrent_id, $update = true, $return = false)
	{
		global $db;
		
		$torrent_id = intval($torrent_id);
		if (!$torrent_id)
		{
			return $return ? 'Invalid request' : FALSE;
		}
		
		$sql = "SELECT `comment`, `last_check` FROM `tracker_stats` WHERE `torrent_id` = $torrent_id LIMIT 1";
		$row = $db->fetch_row($sql);
		
		if (empty($row))
		{
			return $return ? 'Torrent not exist' : FALSE;
		}
		
		$comment = trim($row['comment']);
		if (is_url($comment))
		{
			$name = "";
			
			$parts = parse_url($comment);
			$host = $parts['host'];
			if (array_search($host, $this->blacklist))
			{
				$this->updateData($torrent_id);
				return $return ? 'Could not obtain torrent name from url (tracker is down or not supported)' : FALSE;
			}
			
			@ini_set('user_agent', $this->user_agent);
			$html = @file_get_contents($comment, 0, stream_context_create(array('http'=>array('timeout'=>$this->timeout))));
			$html = str_replace('<wbr>', '', str_replace('</wbr>', '', $html));
			
			$obj = str_get_html($html);
			unset($html);
			if (!is_object($obj))
			{
				return $return ? 'Could not obtain torrent name from url (tracker is down or not supported)' : FALSE;
			}
			
			$b = NULL;
			if (strpos($comment, 'interfilm.'))
			{
				$b = $obj->find('table.tbt font', 0);
			}
			elseif (strpos($comment, 'kinozal.tv'))
			{
				$b = $obj->find('b font[color=green]', 1);
			}
			/*elseif (strpos($comment, 'etorrent.ru'))
			{
				$b = $obj->find('#det_name', 0);
			}*/
			elseif (strpos($comment, 'netlab.e2k.ru'))
			{
				$b = $obj->find('td.cattop', 0);
			}
			else
			{
				$b = $obj->find('.maintitle', 0);
			}
			
			if (is_object($b))
			{
				$name = strval($b->plaintext);
			}
			else
			{
				$name = "";
			}
			
			if ($update)
			{
				$this->updateData($torrent_id, $name);
			}
			
			if (!$name)
			{
				return $return ? 'Could not obtain torrent name from url (tracker is down or not supported)' : FALSE;
			}
			
			if ($return)
			{
				$name = iconv('CP1251', 'UTF-8', $name);
				return '<a class="genmed" href="' . $comment . '" target="_blank"><b>' . $name . '</b></a>';
			}
			return TRUE;
		}
		else
		{
			if ($update)
			{
				$this->updateData($torrent_id);
			}
			return $return ? 'This is not a URL' : FALSE;
		}
	}

	public function batchUpdate()
	{
		global $db;
		
		ini_set("max_execution_time", 3600);
		
		$sql = "SELECT `comment`, `last_check`, `torrent_id`
				FROM
					`tracker_stats`
				WHERE
					`last_check` = 0
				AND
					`name`=''
				AND
					`comment`!=''
				ORDER BY `torrent_id` DESC
				LIMIT " . $this->one_shot;
		$rowset = $db->fetch_rowset($sql);
		$count = 0;
		foreach ($rowset as $row)
		{
			//			echo $res['torrent_id'] . "|" . $res['comment'];
			if ($this->updateName($row['torrent_id'], TRUE, FALSE))
			{
				//				echo " - IT";
				$count++;
			}
			//			echo "<br>";
		}
		return $count;
	}

	public function updateData($torrent_id, $name = "")
	{
		global $db;
		
//		if (detect_encoding($name)=='windows-1251') {
//			$name = iconv("windows-1251","utf-8",$name);
//		}
		
		$name = $db->escape(trim($name));
		$sql = "UPDATE `tracker_stats` SET
					" . ($name ? "name = '" . $name . "'," : "") . "
					last_check = " . TIMENOW . "
				WHERE torrent_id = " . $torrent_id . "
				LIMIT 1";
		$db->query($sql);
	}
}
