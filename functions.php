<?php

function humn_size($size, $rounder = null, $min = null, $space = '&nbsp;')
{
	static $sizes = array(
			'B',
			'KB',
			'MB',
			'GB',
			'TB',
			'PB',
			'EB',
			'ZB',
			'YB'
	);
	static $rounders = array(
			0,
			0,
			0,
			2,
			3,
			3,
			3,
			3,
			3
	);
	
	$size = (float)$size;
	$ext = $sizes[0];
	$rnd = $rounders[0];
	
	if ($min == 'KB' && $size < 1024)
	{
		$size = $size / 1024;
		$ext = 'KB';
		$rounder = 1;
	}
	else
	{
		for($i = 1, $cnt = count($sizes); ($i < $cnt && $size >= 1024); $i++)
		{
			$size = $size / 1024;
			$ext = $sizes[$i];
			$rnd = $rounders[$i];
		}
	}
	if (!$rounder)
	{
		$rounder = $rnd;
	}
	
	return round($size, $rounder) . $space . $ext;
}

$lang['Next'] = 'След.';
$lang['Previous'] = 'Пред.';

$lang['datetime']['Sunday'] = 'Воскресенье';
$lang['datetime']['Monday'] = 'Понедельник';
$lang['datetime']['Tuesday'] = 'Вторник';
$lang['datetime']['Wednesday'] = 'Среда';
$lang['datetime']['Thursday'] = 'Четверг';
$lang['datetime']['Friday'] = 'Пятница';
$lang['datetime']['Saturday'] = 'Суббота';
$lang['datetime']['Sun'] = 'Вс';
$lang['datetime']['Mon'] = 'Пн';
$lang['datetime']['Tue'] = 'Вт';
$lang['datetime']['Wed'] = 'Ср';
$lang['datetime']['Thu'] = 'Чт';
$lang['datetime']['Fri'] = 'Пт';
$lang['datetime']['Sat'] = 'Сб';
$lang['datetime']['January'] = 'Январь';
$lang['datetime']['February'] = 'Февраль';
$lang['datetime']['March'] = 'Март';
$lang['datetime']['April'] = 'Апрель';
$lang['datetime']['May'] = 'Май';
$lang['datetime']['June'] = 'Июнь';
$lang['datetime']['July'] = 'Июль';
$lang['datetime']['August'] = 'Август';
$lang['datetime']['September'] = 'Сентябрь';
$lang['datetime']['October'] = 'Октябрь';
$lang['datetime']['November'] = 'Ноябрь';
$lang['datetime']['December'] = 'Декабрь';
$lang['datetime']['Jan'] = 'Янв';
$lang['datetime']['Feb'] = 'Фев';
$lang['datetime']['Mar'] = 'Мар';
$lang['datetime']['Apr'] = 'Апр';
$lang['datetime']['May'] = 'Май';
$lang['datetime']['Jun'] = 'Июн';
$lang['datetime']['Jul'] = 'Июл';
$lang['datetime']['Aug'] = 'Авг';
$lang['datetime']['Sep'] = 'Сен';
$lang['datetime']['Oct'] = 'Окт';
$lang['datetime']['Nov'] = 'Ноя';
$lang['datetime']['Dec'] = 'Дек';

// Create date/time from format and timezone
function create_date($format, $gmepoch, $tz = null)
{
	global $lang;
	
	if (is_null($tz))
	{
		$tz = '4';
	}
	
	$date = gmdate($format, $gmepoch + (3600 * $tz));
	
	return strtr($date, $lang['datetime']);
}

function create_magnet($dn, $xl = false, $btih = '', $tr = '')
{
	$magnet = 'magnet:?';
	if ($dn)
	{
		$magnet .= 'dn=' . $dn; // download name
	}
	if ($xl)
	{
		$magnet .= '&xl=' . $xl; // size
	}
	if ($btih)
	{
		$magnet .= '&xt=urn:btih:' . $btih; // bittorrent info_hash (Base32)
	}
	if ($tr)
	{
		$magnet .= '&tr=' . $tr; // gnutella sha1 (base32)
	}
	return $magnet;
}

function create_comment($comment)
{
	if (substr($comment, 0, 4) == 'http')
	{
		$comment = "<a class=\"med\" href=\"$comment\">$comment</a>";
	}
	return $comment;
}

function make_url($comment)
{
	$cary = explode(' ', $comment);
	$comment = '';
	foreach ($cary as $part)
	{
		if (is_url($part))
		{
			$part = "<a class=\"med\" href=\"$part\">$part</a>";
		}
		$comment .= $part . ' ';
	}
	return $comment;
}

function is_url($url)
{
	$url = substr($url, -1) == "/" ? substr($url, 0, -1) : $url;
	if (!$url || $url == "")
		return false;
	
	$empty = array(
			'scheme' => "",
			'path' => "",
			'query' => ""
	);
	
	if (!($parts = @parse_url($url)))
		return false;
	else
	{
		$regs = NULL;
		$parts = array_merge($empty, $parts);
		if (!isset($parts['scheme']) || ($parts['scheme'] != "http" && $parts['scheme'] != "https" && $parts['scheme'] != "ftp"))
			return false;
		else if (!eregi("^[0-9a-z]([-.]?[0-9a-z])*.[a-z]{2,4}$", $parts['host'], $regs))
			return false;
		else //else if ( !eregi( "^([0-9a-z-]|[_])*$", $parts['pass'], $regs ) ) return false;
if (!eregi("^[0-9a-z/_.@~-]*$", $parts['path'], $regs))
			return false;
		else if (!eregi("^[0-9a-z?&=#,]*$", $parts['query'], $regs))
			return false;
	}
	return true;
}

function city_select($selected_id = 0)
{
	$out = '';
	
	$i = 0;
	foreach (GetCitys() as $id => $name)
	{
		$i++;
		$out .= "<option value=\"$i\"" . (($i == $selected_id) ? " selected=\"selected\"" : '') . ">" . $name . "</option>\n";
	}
	
	return $out;
}

function isp_select($city = 0, $selected_id = 0)
{
	$out = '<option value="0">&raquo; Select ISP</option>';
	$i = 0;
	foreach (GetProviders($city) as $id => $name)
	{
		$i++;
		$out .= "<option value=\"$i\"" . (($i == $selected_id) ? " selected=\"selected\"" : '') . ">" . $name . "</option>\n";
	}
	
	return $out;
}

function tr_list($tr_ary)
{
	$out = '';
	
	for($i = 1; $i <= ($tr_ary['Количество']); $i++)
	{
		if (!strpos($tr_ary[$i], 're-tracker.ru') && !strpos($tr_ary[$i], 'retracker.local'))
			$out .= $tr_ary[$i] . "\n";
	}
	
	return $out;
}

function get_trackers($string = false)
{
	global $cache;
	$trackers = $cache->get('new_trackers');
	if (!empty($trackers) && sizeof($trackers) > 3)
	{
		return $trackers;
	}
	
	$trackers = array();
	$citys = GetCitys();
	$array = array();
	
	$trackers["city"][] = '; Created ' . date("d.m.Y");
	$trackers["city"][] = '';
	$trackers["city"][] = '[Город]';
	$trackers["city"][] = 'Количество=' . sizeof($citys);
	$array['Город']["Количество"] = sizeof($citys);
	$i = $j = $k = 0;
	foreach ($citys as $id_city => $city)
	{
		$i++;
		$city_name = $city;
		$trackers["city"][] = $i . "=" . $city_name;
		$array['Город'][$i] = $city_name;
		$isps = GetProviders($id_city);
		$trackers["isp_" . $id_city][] = '[Провайдеры ' . $city_name . ']';
		$trackers["isp_" . $id_city][] = 'Количество=' . sizeof($isps);
		$array['Провайдеры ' . $city_name . '']["Количество"] = sizeof($isps);
		$j = 0;
		foreach ($isps as $id_isp => $isp)
		{
			$j++;
			$isp_name = $isp;
			$trackers["isp_" . $id_city][] = $j . '=' . $isp_name;
			$array['Провайдеры ' . $city_name][$j] = $isp_name;
			$retrackers = GetRetrackers($id_city, $id_isp);
			$trackers["ret_" . $id_city . '_' . $id_isp][] = '[Ретрекеры ' . $city_name . ' ' . $isp_name . ']';
			$trackers["ret_" . $id_city . '_' . $id_isp][] = 'Количество=' . sizeof($retrackers);
			$array['Ретрекеры ' . $city_name . ' ' . $isp_name]["Количество"] = sizeof($retrackers);
			$k = 0;
			foreach ($retrackers as $id_ret => $ret)
			{
				$k++;
				$ret_name = $ret['retracker'];
				$trackers["ret_" . $id_city . '_' . $id_isp][] = $k . '=' . $ret_name;
				$array['Ретрекеры ' . $city_name . ' ' . $isp_name][$k] = $ret_name;
			}
		}
	}
	$out = '';
	foreach ($trackers as $key => $list)
	{
		$out .= implode("\r\n", $trackers[$key]) . "\r\n\r\n";
	}
	$out = iconv("UTF-8", "UTF-16", $out);
	
	$cache->set('trackers_list', $out, TRACKERS_CACHE_EXPIRE);
	$cache->set('new_trackers', $array, TRACKERS_CACHE_EXPIRE);
	
	return $string ? $out : $array;
}

//
// Pagination routine, generates
// page number sequence
//
function generate_pagination($base_url, $num_items, $per_page, $start_item, $add_prevnext_text = TRUE)
{
	global $lang;
	
	// Pagination Mod
	$begin_end = 3;
	$from_middle = 1;
	/*
	By default, $begin_end is 3, and $from_middle is 1, so on page 6 in a 12 page view, it will look like this:

	a, d = $begin_end = 3
	b, c = $from_middle = 1

 "begin"        "middle"           "end"
    |              |                 |
    |     a     b  |  c     d        |
    |     |     |  |  |     |        |
    v     v     v  v  v     v        v
    1, 2, 3 ... 5, 6, 7 ... 10, 11, 12

	Change $begin_end and $from_middle to suit your needs appropriately
*/
	
	$total_pages = ceil($num_items / $per_page);
	
	if ($total_pages == 1)
	{
		return '';
	}
	
	$on_page = floor($start_item / $per_page) + 1;
	
	$page_string = '';
	if ($total_pages > ((2 * ($begin_end + $from_middle)) + 2))
	{
		$init_page_max = ($total_pages > $begin_end) ? $begin_end : $total_pages;
		for($i = 1; $i < $init_page_max + 1; $i++)
		{
			$page_string .= ($i == $on_page) ? '<b>' . $i . '</b>' : '<a href="' . ($base_url . "&amp;start=" . (($i - 1) * $per_page)) . '">' . $i . '</a>';
			if ($i < $init_page_max)
			{
				$page_string .= ", ";
			}
		}
		if ($total_pages > $begin_end)
		{
			if ($on_page > 1 && $on_page < $total_pages)
			{
				$page_string .= ($on_page > ($begin_end + $from_middle + 1)) ? ' ... ' : ', ';
				
				$init_page_min = ($on_page > ($begin_end + $from_middle)) ? $on_page : ($begin_end + $from_middle + 1);
				
				$init_page_max = ($on_page < $total_pages - ($begin_end + $from_middle)) ? $on_page : $total_pages - ($begin_end + $from_middle);
				
				for($i = $init_page_min - $from_middle; $i < $init_page_max + ($from_middle + 1); $i++)
				{
					$page_string .= ($i == $on_page) ? '<b>' . $i . '</b>' : '<a href="' . ($base_url . "&amp;start=" . (($i - 1) * $per_page)) . '">' . $i . '</a>';
					if ($i < $init_page_max + $from_middle)
					{
						$page_string .= ', ';
					}
				}
				$page_string .= ($on_page < $total_pages - ($begin_end + $from_middle)) ? ' ... ' : ', ';
			}
			else
			{
				$page_string .= '&nbsp;...&nbsp;';
			}
			for($i = $total_pages - ($begin_end - 1); $i < $total_pages + 1; $i++)
			{
				$page_string .= ($i == $on_page) ? '<b>' . $i . '</b>' : '<a href="' . ($base_url . "&amp;start=" . (($i - 1) * $per_page)) . '">' . $i . '</a>';
				if ($i < $total_pages)
				{
					$page_string .= ", ";
				}
			}
		}
	}
	else
	{
		for($i = 1; $i < $total_pages + 1; $i++)
		{
			$page_string .= ($i == $on_page) ? '<b>' . $i . '</b>' : '<a href="' . ($base_url . "&amp;start=" . (($i - 1) * $per_page)) . '">' . $i . '</a>';
			if ($i < $total_pages)
			{
				$page_string .= ', ';
			}
		}
	}
	
	if ($add_prevnext_text)
	{
		if ($on_page > 1)
		{
			$page_string = ' <a href="' . ($base_url . "&amp;start=" . (($on_page - 2) * $per_page)) . '">' . $lang['Previous'] . '</a>&nbsp;&nbsp;' . $page_string;
		}
		
		if ($on_page < $total_pages)
		{
			$page_string .= '&nbsp;&nbsp;<a href="' . ($base_url . "&amp;start=" . ($on_page * $per_page)) . '">' . $lang['Next'] . '</a>';
		}
	
	}
	
	$return = ($page_string) ? '<b align="right">Страницы:</b>&nbsp;&nbsp;' . $page_string : '';
	
	return str_replace('&amp;start=0', '', $return);
}

function base32_encode($inString)
{
	$outString = "";
	$compBits = "";
	$BASE32_TABLE = array(
			'00000' => 0x61,
			'00001' => 0x62,
			'00010' => 0x63,
			'00011' => 0x64,
			'00100' => 0x65,
			'00101' => 0x66,
			'00110' => 0x67,
			'00111' => 0x68,
			'01000' => 0x69,
			'01001' => 0x6a,
			'01010' => 0x6b,
			'01011' => 0x6c,
			'01100' => 0x6d,
			'01101' => 0x6e,
			'01110' => 0x6f,
			'01111' => 0x70,
			'10000' => 0x71,
			'10001' => 0x72,
			'10010' => 0x73,
			'10011' => 0x74,
			'10100' => 0x75,
			'10101' => 0x76,
			'10110' => 0x77,
			'10111' => 0x78,
			'11000' => 0x79,
			'11001' => 0x7a,
			'11010' => 0x32,
			'11011' => 0x33,
			'11100' => 0x34,
			'11101' => 0x35,
			'11110' => 0x36,
			'11111' => 0x37
	);
	
	/* Turn the compressed string into a string that represents the bits as 0 and 1. */
	for($i = 0; $i < strlen($inString); $i++)
	{
		$compBits .= str_pad(decbin(ord(substr($inString, $i, 1))), 8, '0', STR_PAD_LEFT);
	}
	
	/* Pad the value with enough 0's to make it a multiple of 5 */
	if ((strlen($compBits) % 5) != 0)
	{
		$compBits = str_pad($compBits, strlen($compBits) + (5 - (strlen($compBits) % 5)), '0', STR_PAD_RIGHT);
	}
	
	/* Create an array by chunking it every 5 chars */
	$fiveBitsArray = split("\n", rtrim(chunk_split($compBits, 5, "\n")));
	
	/* Look-up each chunk and add it to $outstring */
	foreach ($fiveBitsArray as $fiveBitsString)
	{
		$outString .= chr($BASE32_TABLE[$fiveBitsString]);
	}
	
	return $outString;
}

function hex2bin($h)
{
	if (!is_string($h))
		return null;
	$r = '';
	for($a = 0; $a < strlen($h); $a += 2)
	{
		$r .= chr(hexdec($h{$a} . $h{($a + 1)}));
	}
	return $r;
}

function sqlwildcardesc($x)
{
	return str_replace(array(
			"%",
			"_"
	), array(
			"\\%",
			"\\_"
	), $x);
}

// bdecode: based on OpenTracker [http://whitsoftdev.com/opentracker]
function bdecode_file($filename)
{
	if (!$fp = fopen($filename, 'rb'))
	{
		return null;
	}
	$fc = fread($fp, filesize($filename));
	fclose($fp);
	
	return bdecode($fc);
}

function bdecode($str)
{
	$pos = 0;
	return bdecode_r($str, $pos);
}

function bdecode_r($str, &$pos)
{
	$strlen = strlen($str);
	
	if (($pos < 0) || ($pos >= $strlen))
	{
		return null;
	}
	else if ($str{$pos} == 'i')
	{
		$pos++;
		$numlen = strspn($str, '-0123456789', $pos);
		$spos = $pos;
		$pos += $numlen;
		
		if (($pos >= $strlen) || ($str{$pos} != 'e'))
		{
			return null;
		}
		else
		{
			$pos++;
			return floatval(substr($str, $spos, $numlen));
		}
	}
	else if ($str{$pos} == 'd')
	{
		$pos++;
		$ret = array();
		
		while ($pos < $strlen)
		{
			if ($str{$pos} == 'e')
			{
				$pos++;
				return $ret;
			}
			else
			{
				$key = bdecode_r($str, $pos);
				
				if ($key === null)
				{
					return null;
				}
				else
				{
					$val = bdecode_r($str, $pos);
					
					if ($val === null)
					{
						return null;
					}
					else if (!is_array($key))
					{
						$ret[$key] = $val;
					}
				}
			}
		}
		return null;
	}
	else if ($str{$pos} == 'l')
	{
		$pos++;
		$ret = array();
		
		while ($pos < $strlen)
		{
			if ($str{$pos} == 'e')
			{
				$pos++;
				return $ret;
			}
			else
			{
				$val = bdecode_r($str, $pos);
				
				if ($val === null)
				{
					return null;
				}
				else
				{
					$ret[] = $val;
				}
			}
		}
		return null;
	}
	else
	{
		$numlen = strspn($str, '0123456789', $pos);
		$spos = $pos;
		$pos += $numlen;
		
		if (($pos >= $strlen) || ($str{$pos} != ':'))
		{
			return null;
		}
		else
		{
			$vallen = intval(substr($str, $spos, $numlen));
			$pos++;
			$val = substr($str, $pos, $vallen);
			
			if (strlen($val) != $vallen)
			{
				return null;
			}
			else
			{
				$pos += $vallen;
				return $val;
			}
		}
	}
}

/**
 *  Функция возвращает файл и строку вызова (трассировка назад по вызовам функций).
 *  $step == 0 - файл и строка вызова этой функции
 *  @access public
 *  @return array Структура: array( '/home/site/filename.inc', 222 )
 *  @param integer $step Шаг назад
 */
function LastFileLine($step = 0)
{
	$export = array(
			'undefined',
			0
	);
	if (function_exists('debug_backtrace'))
	{
		$bt = debug_backtrace();
		if (isset($bt[$step]['file']) && $bt[$step]['line'])
		{
			$export = array(
					$bt[$step]['file'],
					$bt[$step]['line']
			);
		}
		if (isset($_SERVER['WINDIR']))
		{
			$export[0] = preg_replace('/\\\\/', '/', $export[0]);
		}
	}
	else
	{
		die('[{Версия PHP4 должна быть равна 4.3.1 или выше}]');
	}
	
	unset($bt, $step);
	return $export;
}

/**
 *  Временная функция для тестирования скриптов.
 *  Вывод текста.
 *  @access public
 *  @param mixed $text Текст для записи
 *  @param boolean $die Остановить скрипты
 *  @param boolean $tofile Вывести в файл debug
 *  @return void
 */
function debug($text, $die = true, $tofile = false)
{
	$text = print_r($text, true);
	
	list($file, $line) = LastFileLine(1);
	
	if ($tofile)
	{
		$text = $text . "  " . $file . ': ' . $line . "\r\n\r\n";
		$fp = fopen($_SERVER['DOCUMENT_ROOT'] . '/debug.inc', 'a+');
		fwrite($fp, $text);
		fclose($fp);
		
		if ($die)
			die();
	}
	else
	{
		$text = '<pre>|' . htmlspecialchars($text) . '|</pre><br><b>' . $file . ': ' . $line . '</b>';
		if ($die)
			die($text);
		else
			echo $text;
	}
}

/**
 *  Ассоциирует значения массива по содержимому указаного поля.
 *  Порядок элементов сохраняется.
 *  @access public
 *  @param string $field Название существующего в массиве поля
 *  @param array $array Неассоциированный массив
 *  @return array Ассоциированный массив
 */
function assoc($field, $array)
{
	
	$array = (array)$array;
	
	if (!sizeof($array) || key($array) === false)
		return $array;
	
	$result = array();
	foreach ($array as $row)
		$result[$row[$field]] = $row;
	
	unset($array, $row);
	
	return $result;
}

function GetCitys($from_cache = true)
{
	global $db, $cache, $cfg;
	
	$out = $cache->get("citys");
	if (!empty($out) && $from_cache)
	{
		return $out;
	}
	else
	{
		$out = array();
	}
	
	$list = $db->fetch_rowset("SELECT `id`,`name` FROM `tracker_city` ORDER BY `id` ASC");
	
	foreach ($list as $row)
	{
		if ($row['name'])
		{
			$out[$row['id']] = $row['name'];
		}
	}
	$from_cache ? $cache->set("citys", $out, TRACKERS_CACHE_EXPIRE) : FALSE;
	return $out;
}

function GetProviders($by_city = null, $from_cache = true)
{
	global $db, $cache, $cfg;
	
	$cache_tag = $cache->get("providers_tag");
	if (empty($cache_tag))
	{
		$cache_tag = time();
		$cache->set("providers_tag", $cache_tag, 86400);
	}
	
	$cache_key = md5($cache_tag . "providers_" . $by_city);
	$out = $cache->get($cache_key);
	if (!empty($out) && $from_cache)
	{
		return $out;
	}
	else
	{
		$out = array();
	}
	
	$citys = GetCitys();
	if ($by_city !== NULL && isset($citys[$by_city]))
	{
		$list = $db->fetch_rowset("SELECT DISTINCT(`tracker_provider`.`id`),`tracker_provider`.`name` FROM `tracker_provider`,`tracker_retrackers` WHERE `tracker_provider`.`id`=`tracker_retrackers`.`id_prov` AND `tracker_retrackers`.`id_city`=" . intval($by_city) . " ORDER BY `tracker_provider`.`id` ASC");
	}
	else
	{
		$list = $db->fetch_rowset("SELECT `id`,`name` FROM `tracker_provider` ORDER BY `id` ASC");
	}
	
	foreach ($list as $row)
	{
		$out[$row['id']] = $row['name'];
	}
	
	$from_cache ? $cache->set($cache_key, $out, TRACKERS_CACHE_EXPIRE) : FALSE;
	return $out;
}

function GetRetrackers($by_city = null, $by_prov = null, $from_cache = true)
{
	global $db, $cache, $cfg;
	
	$by_prov = intval($by_prov);
	$by_city = intval($by_city);
	
	$cache_tag = $cache->get("retrackers_tag");
	if (empty($cache_tag))
	{
		$cache_tag = time();
		$cache->set("retrackers_tag", $cache_tag, 86400);
	}
	
	$cache_key = md5($cache_tag . "retrackers_" . $by_city . "_" . $by_prov);
	$list = $cache->get($cache_key);
	if (!empty($list) && $from_cache)
	{
		return $list;
	}
	else
	{
		$list = array();
	}
	
	$provs = GetProviders();
	if ($by_prov > 0 && !isset($provs[$by_prov]))
	{
		return array();
	}
	
	$list = $db->fetch_rowset("SELECT * FROM `tracker_retrackers` WHERE " . ($by_city > 0 ? "`id_city`=" . $by_city . " AND " : "") . ($by_prov > 0 ? "`id_prov`=" . $by_prov . " AND " : "") . "`allow`=1 ORDER BY `id` ASC");
	
	$list[] = array(
			"retracker" => "http://retracker.local:80/announce"
	);
	$list[] = array(
			"retracker" => "http://re-tracker.ru:80/announce.php"
	);
	
	$from_cache ? $cache->set($cache_tag, $list, TRACKERS_CACHE_EXPIRE) : FALSE;
	return $list;
}