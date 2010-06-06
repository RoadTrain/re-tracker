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
		//$this->blacklist[] = "tseed.ru";
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
		
		$sql = "SELECT `comment`, `name`, `info_hash`, `last_check` FROM `tracker_stats` WHERE `torrent_id` = $torrent_id LIMIT 1";
		$row = $db->fetch_row($sql);
		
		if (empty($row))
		{
			return $return ? 'Torrent not exist' : FALSE;
		}
		
		$comment = trim($row['comment']);
		$name = trim($row['name']);
		if (empty($comment) && empty($name))
		{
			$comment = "http://isohunt.com/torrents/?ihq=" . $row['info_hash'];
		}
		if (is_url($comment))
		{
			$name = $this->getNameFromUrl($comment);
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
				if (detect_encoding($name) == 'windows-1251')
				{
					$name = iconv("windows-1251", "utf-8", $name);
				}
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

	public function getNameFromUrl($comment)
	{
		$name = "";
		
		$parts = parse_url($comment);
		$host = $parts['host'];
		if (array_search($host, $this->blacklist))
		{
			return FALSE;
		}
		
		@ini_set('user_agent', $this->user_agent);
		$html = @file_get_contents($comment, 0, stream_context_create(array(
				'http' => array(
						'timeout' => $this->timeout
				)
		)));
		if (strpos($html, "<title>") === FALSE)
		{
			// Try ungzip
			$html = $this->gzdecode($html);
		}
		
		$html = str_replace('<wbr>', '', str_replace('</wbr>', '', $html));
		
		$obj = str_get_html($html);
		unset($html);
		if (!is_object($obj))
		{
			return FALSE;
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
		elseif (strpos($comment, 'netlab.e2k.ru'))
		{
			$b = $obj->find('td.cattop', 0);
		}
		elseif (strpos($comment, 'tfile.ru'))
		{
			$b = $obj->find('div#topicContainer h1', 0);
		}
		elseif (strpos($comment, 'animereactor.ru'))
		{
			$b = $obj->find('div.maintitle table tbody tr td div', 0);
		}
		elseif (strpos($comment, 'pornoshara.com'))
		{
			$b = $obj->find('div.center_col h1 b', 0);
		}
		elseif (strpos($comment, 'animelayer.ru'))
		{
			$b = $obj->find('h1.details_h1', 0);
		}
		elseif (strpos($comment, 'bigfangroup.org'))
		{
			$b = $obj->find('a.index', 0);
		}
		elseif (strpos($comment, 'losslessclub.com'))
		{
			$b = $obj->find('a.index b', 0);
		}
		elseif (strpos($comment, 'nntt.org'))
		{
			$b = $obj->find('h2 a.titles', 0);
		}
		elseif (strpos($comment, 'isohunt.com'))
		{
			$b = $obj->find('a#link1', 0);
			if (is_object($b))
			{
				$b->plaintext = preg_replace("/(.*)<br\/?>/sim", "", $b->innertext);
			}
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
		
		return trim(str_replace("&nbsp;", " ", $name));
	}

	public function batchUpdate($update_empty = false)
	{
		global $db;
		
		ini_set("max_execution_time", 600);
		
		$sql = "SELECT `comment`, `last_check`, `torrent_id`
				FROM
					`tracker_stats`
				WHERE
					`last_check` = 0
				" . ($update_empty ? "" : " AND `comment`!=''") . "
				ORDER BY `torrent_id` " . ($update_empty ? "ASC" : "DESC") . "
				LIMIT " . $this->one_shot;
		$rowset = $db->fetch_rowset($sql);
		$count = 0;
		foreach ($rowset as $row)
		{
			if ($this->updateName($row['torrent_id'], TRUE, FALSE))
			{
				$count++;
			}
		}
		return $count;
	}

	public function updateData($torrent_id, $name = "")
	{
		global $db;
		
		if (detect_encoding($name) == 'windows-1251')
		{
			$name = iconv("windows-1251", "utf-8", $name);
		}
		
		$name = $db->escape(trim($name));
		$sql = "UPDATE `tracker_stats` SET
					" . ($name ? "name = '" . $name . "'," : "") . "
					last_check = " . TIMENOW . "
				WHERE torrent_id = " . $torrent_id . "
				LIMIT 1";
		$db->query($sql);
	}

	/**
	 * Decodes a gzip compressed string
	 *
	 * @author katzlbtjunk at hotmail dot com
	 *
	 * @link http://www.php.net/manual/en/function.gzdecode.php
	 */
	public function gzdecode($data, &$filename = '', &$error = '', $maxlength = null)
	{
		$len = strlen($data);
		if ($len < 18 || strcmp(substr($data, 0, 2), "\x1f\x8b"))
		{
			$error = "Not in GZIP format.";
			return null; // Not GZIP format (See RFC 1952)
		}
		$method = ord(substr($data, 2, 1)); // Compression method
		$flags = ord(substr($data, 3, 1)); // Flags
		if ($flags & 31 != $flags)
		{
			$error = "Reserved bits not allowed.";
			return null;
		}
		// NOTE: $mtime may be negative (PHP integer limitations)
		$mtime = unpack("V", substr($data, 4, 4));
		$mtime = $mtime[1];
		$xfl = substr($data, 8, 1);
		$os = substr($data, 8, 1);
		$headerlen = 10;
		$extralen = 0;
		$extra = "";
		if ($flags & 4)
		{
			// 2-byte length prefixed EXTRA data in header
			if ($len - $headerlen - 2 < 8)
			{
				return false; // invalid
			}
			$extralen = unpack("v", substr($data, 8, 2));
			$extralen = $extralen[1];
			if ($len - $headerlen - 2 - $extralen < 8)
			{
				return false; // invalid
			}
			$extra = substr($data, 10, $extralen);
			$headerlen += 2 + $extralen;
		}
		$filenamelen = 0;
		$filename = "";
		if ($flags & 8)
		{
			// C-style string
			if ($len - $headerlen - 1 < 8)
			{
				return false; // invalid
			}
			$filenamelen = strpos(substr($data, $headerlen), chr(0));
			if ($filenamelen === false || $len - $headerlen - $filenamelen - 1 < 8)
			{
				return false; // invalid
			}
			$filename = substr($data, $headerlen, $filenamelen);
			$headerlen += $filenamelen + 1;
		}
		$commentlen = 0;
		$comment = "";
		if ($flags & 16)
		{
			// C-style string COMMENT data in header
			if ($len - $headerlen - 1 < 8)
			{
				return false; // invalid
			}
			$commentlen = strpos(substr($data, $headerlen), chr(0));
			if ($commentlen === false || $len - $headerlen - $commentlen - 1 < 8)
			{
				return false; // Invalid header format
			}
			$comment = substr($data, $headerlen, $commentlen);
			$headerlen += $commentlen + 1;
		}
		$headercrc = "";
		if ($flags & 2)
		{
			// 2-bytes (lowest order) of CRC32 on header present
			if ($len - $headerlen - 2 < 8)
			{
				return false; // invalid
			}
			$calccrc = crc32(substr($data, 0, $headerlen)) & 0xffff;
			$headercrc = unpack("v", substr($data, $headerlen, 2));
			$headercrc = $headercrc[1];
			if ($headercrc != $calccrc)
			{
				$error = "Header checksum failed.";
				return false; // Bad header CRC
			}
			$headerlen += 2;
		}
		// GZIP FOOTER
		$datacrc = unpack("V", substr($data, -8, 4));
		$datacrc = sprintf('%u', $datacrc[1] & 0xFFFFFFFF);
		$isize = unpack("V", substr($data, -4));
		$isize = $isize[1];
		// decompression:
		$bodylen = $len - $headerlen - 8;
		if ($bodylen < 1)
		{
			// IMPLEMENTATION BUG!
			return null;
		}
		$body = substr($data, $headerlen, $bodylen);
		$data = "";
		if ($bodylen > 0)
		{
			switch ($method)
			{
				case 8:
					// Currently the only supported compression method:
					$data = gzinflate($body, $maxlength);
					break;
				default:
					$error = "Unknown compression method.";
					return false;
			}
		} // zero-byte body content is allowed
		// Verifiy CRC32
		$crc = sprintf("%u", crc32($data));
		$crcOK = $crc == $datacrc;
		$lenOK = $isize == strlen($data);
		if (!$lenOK || !$crcOK)
		{
			$error = ($lenOK ? '' : 'Length check FAILED. ') . ($crcOK ? '' : 'Checksum FAILED.');
			return false;
		}
		return $data;
	}
}
