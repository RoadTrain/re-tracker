<?php

define('TIMESTART', utime());
define('TIMENOW', time());

set_magic_quotes_runtime(0); // Disable magic_quotes_runtime

include_once (dirname(realpath(__FILE__)) . '/config.php');
include_once (dirname(realpath(__FILE__)) . '/cache.class.php');

if (get_magic_quotes_gpc())
{
	array_deep($_GET, 'stripslashes');
}

switch ($cfg['cache_type'])
{
	case 'APC':
		$cache = new cache_apc();
		break;
	
	case 'memcached':
		$cache = new cache_memcached($cfg['cache']['memcached']);
		break;
	
	case 'sqlite':
		$cache = new cache_sqlite($cfg['cache']['sqlite']);
		break;
	
	case 'filecache':
		$cache = new cache_file($cfg['cache']['filecache']['path']);
		break;
	
	default:
		$cache = new cache_common();
}

// Functions & classes
function db_init()
{

	global $cfg;
	
	@mysql_pconnect($cfg['dbhost'], $cfg['dbuser'], $cfg['dbpass']) or msg_die("Could not connect: " . mysql_error());
	@mysql_select_db($cfg['dbname']);
}

function cleanup()
{
	global $cache, $cfg, $tracker, $tracker_stats;
	
	$cache->gc();
	$cache->set('next_cleanup', TIMENOW + $cfg['cleanup_interval']);
	
	$peer_expire_time = TIMENOW - floor($cfg['announce_interval'] * $cfg['expire_factor']);
	mysql_query("DELETE LOW_PRIORITY FROM $tracker WHERE update_time < $peer_expire_time") or msg_die("MySQL error: " . mysql_error());
	
	$torrent_expire_time = TIMENOW - TORRENTS_EXPIRE;
	mysql_query("DELETE LOW_PRIORITY FROM $tracker_stats WHERE update_time < $torrent_expire_time") or msg_die("MySQL error: " . mysql_error());
}

function utime()
{
	return array_sum(explode(' ', microtime()));
}

function drop_fast_announce($lp_info)
{
	global $announce_interval;
	
	if ($lp_info['update_time'] < (TIMENOW - $announce_interval + 60))
	{
		return; // if announce interval correct
	}
	
	if (DBG_LOG)
		dbg_log(' ', 'drop_fast_announce-' . (!empty($GLOBALS['db']) ? 'DB' : 'CACHE'));
	
	$new_ann_intrv = $lp_info['update_time'] + $announce_interval - TIMENOW;
	
	dummy_exit($new_ann_intrv);
}

function msg_die($msg)
{
	$output = bencode(array(
		'min interval'   => (int) 60, 
		'failure reason' => (string) $msg
	));
	
	die($output);
}

function dummy_exit($interval = 60)
{
	$output = bencode(array(
		'interval'     => (int) $interval, 
		'min interval' => (int) $interval, 
		'peers'        => (string) DUMMY_PEER
	));
	
	die($output);
}

function encode_ip ($dotquad_ip)
{
	$ip_sep = explode('.', $dotquad_ip);
	if (count($ip_sep) == 4)
	{
		return sprintf('%02x%02x%02x%02x', $ip_sep[0], $ip_sep[1], $ip_sep[2], $ip_sep[3]);
	}

	$ip_sep = explode(':', preg_replace('/(^:)|(:$)/', '', $dotquad_ip));
	$res = '';
	foreach ($ip_sep as $x)
	{
		$res .= sprintf('%0'. ($x == '' ? (9 - count($ip_sep)) * 4 : 4) .'s', $x);
	}
	return $res;
}

function decode_ip ($int_ip)
{
	if (strlen($int_ip) == 32) 
	{
		$int_ip = substr(chunk_split($int_ip, 4, ':'), 0, 39);
		$int_ip = ':'. implode(':', array_map("hexhex", explode(':',$int_ip))) .':';
		preg_match_all("/(:0)+/", $int_ip, $zeros);
		if (count($zeros[0]) > 0) 
		{
			$match = '';
			foreach($zeros[0] as $zero)
				if (strlen($zero) > strlen($match))
					$match = $zero;
			$int_ip = preg_replace('/'. $match .'/', ':', $int_ip, 1);
		}
		return preg_replace('/(^:([^:]))|(([^:]):$)/', '$2$4', $int_ip);
	}
	if (strlen($int_ip) !== 8) $int_ip = '00000000';
	$hexipbang = explode('.', chunk_split($int_ip, 2, '.'));
	return hexdec($hexipbang[0]). '.' . hexdec($hexipbang[1]) . '.' . hexdec($hexipbang[2]) . '.' . hexdec($hexipbang[3]);
}

function hexhex($value)
{
	return dechex(hexdec($value));
}

function verify_ip($ip)
{
	if (strpos($ip, ':') !== false)
	{
		$iptype = 'ipv6';
	}
	else if (preg_match('#^(\d{1,3}\.){3}\d{1,3}$#', $ip) !== false)
	{
		$iptype = 'ipv4';
	}
	else
		$iptype = false;
	
	return $iptype;
	//return preg_match('#^(\d{1,3}\.){3}\d{1,3}$#', $ip);
}

function str_compact($str)
{
	return preg_replace('#\s+#', ' ', trim($str));
}

function dbg_log($str, $file)
{
	if (!DBG_LOG)
		return;
	
	$dir = LOG_DIR . (defined('IN_PHPBB') ? 'dbg_bb/' : 'dbg_tr/') . date('m-d_H') . '/';
	return file_write($str, $dir . $file, false, false);
}

function file_write($str, $file, $max_size = LOG_MAX_SIZE, $lock = true, $replace_content = false)
{
	$bytes_written = false;
	
	if ($max_size && @filesize($file) >= $max_size)
	{
		$old_name = $file;
		$ext = '';
		if (preg_match('#^(.+)(\.[^\\/]+)$#', $file, $matches))
		{
			$old_name = $matches[1];
			$ext = $matches[2];
		}
		$new_name = $old_name . '_[old]_' . date('Y-m-d_H-i-s_') . getmypid() . $ext;
		clearstatcache();
		if (@file_exists($file) && @filesize($file) >= $max_size && !@file_exists($new_name))
		{
			@rename($file, $new_name);
		}
	}
	if (!$fp = @fopen($file, 'ab'))
	{
		if ($dir_created = bb_mkdir(dirname($file)))
		{
			$fp = @fopen($file, 'ab');
		}
	}
	if ($fp)
	{
		if ($lock)
		{
			@flock($fp, LOCK_EX);
		}
		if ($replace_content)
		{
			@ftruncate($fp, 0);
			@fseek($fp, 0, SEEK_SET);
		}
		$bytes_written = @fwrite($fp, $str);
		@fclose($fp);
	}
	
	return $bytes_written;
}

function bb_mkdir($path, $mode = 0777)
{
	$old_um = umask(0);
	$dir = mkdir_rec($path, $mode);
	umask($old_um);
	return $dir;
}

function mkdir_rec($path, $mode)
{
	if (is_dir($path))
	{
		return ($path !== '.' && $path !== '..') ? is_writable($path) : false;
	}
	else
	{
		return (mkdir_rec(dirname($path), $mode)) ? @mkdir($path, $mode) : false;
	}
}

function clean_filename($fname)
{
	static $s = array('\\', '/', ':', '*', '?', '"', '<', '>', '|');
	
	return str_replace($s, '_', $fname);
}

function array_deep(&$var, $fn, $one_dimensional = false, $array_only = false)
{
	if (is_array($var))
	{
		foreach ($var as $k => $v)
		{
			if (is_array($v))
			{
				if ($one_dimensional)
				{
					unset($var[$k]);
				}
				else if ($array_only)
				{
					$var[$k] = $fn($v);
				}
				else
				{
					array_deep($var[$k], $fn);
				}
			}
			else if (!$array_only)
			{
				$var[$k] = $fn($v);
			}
		}
	}
	else if (!$array_only)
	{
		$var = $fn($var);
	}
}

// based on OpenTracker [http://whitsoftdev.com/opentracker]
function bencode($var)
{
	if (is_int($var))
	{
		return 'i' . $var . 'e';
	}
	else if (is_float($var))
	{
		return 'i' . sprintf('%.0f', $var) . 'e';
	}
	else if (is_array($var))
	{
		if (count($var) == 0)
		{
			return 'de';
		}
		else
		{
			$assoc = false;
			
			foreach ($var as $key => $val)
			{
				if (!is_int($key) && !is_float($var))
				{
					$assoc = true;
					break;
				}
			}
			
			if ($assoc)
			{
				ksort($var, SORT_REGULAR);
				$ret = 'd';
				
				foreach ($var as $key => $val)
				{
					$ret .= bencode($key) . bencode($val);
				}
				return $ret . 'e';
			}
			else
			{
				$ret = 'l';
				
				foreach ($var as $val)
				{
					$ret .= bencode($val);
				}
				return $ret . 'e';
			}
		}
	}
	else
	{
		return strlen($var) . ':' . $var;
	}
}