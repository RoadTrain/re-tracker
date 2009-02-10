<?php
	
class cache_common
{
	var $used = false;
	/**
	* Returns value of variable
	*/
	function get ($name)
	{
		return false;
	}
	/**
	* Store value of variable
	*/
	function set ($name, $value, $ttl = 0)
	{
		return false;
	}
	/**
	* Remove variable
	*/
	function rm ($name)
	{
		return false;
	}
}

class cache_apc extends cache_common
{
	var $used = true;

	function cache_apc ()
	{
		if (!$this->is_installed())
		{
			die('Error: APC extension not installed');
		}
	}

	function get ($name)
	{
		return apc_fetch($name);
	}

	function set ($name, $value, $ttl = 0)
	{
		return apc_store($name, $value, $ttl);
	}

	function rm ($name)
	{
		return apc_delete($name);
	}

	function is_installed ()
	{
		return function_exists('apc_fetch');
	}
}

class cache_memcached extends cache_common
{
	var $used      = true;

	var $cfg       = null;
	var $memcache  = null;
	var $connected = false;

	function cache_memcached ($cfg)
	{
		global $bb_cfg;

		if (!$this->is_installed())
		{
			die('Error: Memcached extension not installed');
		}

		$this->cfg = $cfg;
		$this->memcache = new Memcache;
	}

	function connect ()
	{
		$connect_type = ($this->cfg['pconnect']) ? 'pconnect' : 'connect';

		if (@$this->memcache->$connect_type($this->cfg['host'], $this->cfg['port']))
		{
			$this->connected = true;
		}

		if (DBG_LOG) dbg_log(' ', 'CACHE-connect'. ($this->connected ? '' : '-FAIL'));

		if (!$this->connected && $this->cfg['con_required'])
		{
			die('Could not connect to memcached server');
		}
	}

	function get ($name)
	{
		if (!$this->connected) $this->connect();
		return ($this->connected) ? $this->memcache->get($name) : false;
	}

	function set ($name, $value, $ttl = 0)
	{
		if (!$this->connected) $this->connect();
		return ($this->connected) ? $this->memcache->set($name, $value, false, $ttl) : false;
	}

	function rm ($name)
	{
		if (!$this->connected) $this->connect();
		return ($this->connected) ? $this->memcache->delete($name) : false;
	}

	function is_installed ()
	{
		return class_exists('Memcache');
	}
}

class cache_sqlite extends cache_common
{
	var $used = true;

	var $cfg  = array();
	var $db   = null;

	function cache_sqlite ($cfg)
	{
		$this->cfg = array_merge($this->cfg, $cfg);
		$this->db = new sqlite_common($cfg);
	}

	function get ($name)
	{
		$result = $this->db->query("
			SELECT cache_value
			FROM ". $this->cfg['table_name'] ."
			WHERE cache_name = '". sqlite_escape_string($name) ."'
				AND cache_expire_time > ". TIMENOW ."
			LIMIT 1
		");

		return ($result AND $cache_value = sqlite_fetch_single($result)) ? unserialize($cache_value) : false;
	}

	function set ($name, $value, $ttl = 86400)
	{
		$name   = sqlite_escape_string($name);
		$expire = TIMENOW + $ttl;
		$value  = sqlite_escape_string(serialize($value));

		$result = $this->db->query("
			REPLACE INTO ". $this->cfg['table_name'] ."
				(cache_name, cache_expire_time, cache_value)
			VALUES
				('$name', '$expire', '$value')
		");

		return (bool) $result;
	}

	function rm ($name)
	{
		$result = $this->db->query("
			DELETE FROM ". $this->cfg['table_name'] ."
			WHERE cache_name = '". sqlite_escape_string($name) ."'
		");

		return (bool) $result;
	}

	function gc ($expire_time = TIMENOW)
	{
		$result = $this->db->query("
			DELETE FROM ". $this->cfg['table_name'] ."
			WHERE cache_expire_time < $expire_time
		");

		return ($result) ? sqlite_changes($this->db->dbh) : 0;
	}
}

class cache_file extends cache_common
{
	var $used = true;

	var $dir  = null;

	function cache_file ($dir)
	{
		$this->dir = $dir;
	}

	function get ($name)
	{
		$filename = $this->dir . $name . '.php';
		
		if(file_exists($filename)) 
		{
			require($filename);
		}
		
		return (!empty($filecache['value'])) ? $filecache['value'] : false;
	}

	function set ($name, $value, $ttl = 86400)
	{
		if (!function_exists('var_export'))
		{
			return false;
		}
		
		$filename   = $this->dir . $name . '.php';
		$expire     = TIMENOW + $ttl;
		$cache_data = array(
			'expire'  => $expire,
			'value'   => $value,
		);

		$filecache = "<?php\n";
		$filecache .= '$filecache = ' . var_export($cache_data, true) . ";\n";
		$filecache .= '?>';		

		return (bool) file_write($filecache, $filename, false, true, true);
	}

	function rm ($name)
	{
		$filename   = $this->dir . $name . '.php';
		if (file_exists($filename))
		{
			return (bool) unlink($filename);
		}
		return false;
	}

	function gc ($expire_time = TIMENOW)
	{
		$dir = $this->dir;
		
		if (is_dir($dir))
		{
			if ($dh = opendir($dir))
			{
				while ((($file = readdir($dh)) !== false))
				{
					if ($file != "." && $file != "..")
					{ 
						$filename = $dir . $file;
					
						include($filename);
					
						if(empty($filecache['expire']) OR ($filecache['expire'] < $expire_time))
						{
							unlink($filename);					
						}
					}
				}
				closedir($dh);
			}
		}

		return;
	}
}

// Sqlite class for cache
class sqlite_common
{
	var $cfg = array(
	             'db_file_path' => 'sqlite.db',
	             'table_name'   => 'table_name',
	             'table_schema' => 'CREATE TABLE table_name (...)',
	             'pconnect'     => true,
	             'con_required' => true,
	             'log_name'     => 'SQLite',
	           );
	var $dbh       = null;
	var $connected = false;

	var $num_queries    = 0;
	var $sql_starttime  = 0;
	var $sql_inittime   = 0;
	var $sql_timetotal  = 0;
	var $cur_query_time = 0;

	var $dbg            = array();
	var $dbg_id         = 0;
	var $dbg_enabled    = false;
	var $cur_query      = null;

	var $table_create_attempts = 0;

	function sqlite_common ($cfg)
	{
		if (!function_exists('sqlite_open')) die('Error: Sqlite extension not installed');
		$this->cfg = array_merge($this->cfg, $cfg);
		$this->dbg_enabled = (SQL_DEBUG && DBG_USER && !empty($_COOKIE['sql_log']));
	}

	function connect ()
	{
		$this->cur_query = 'connect';
		$this->debug('start');

		$connect_type = ($this->cfg['pconnect']) ? 'sqlite_popen' : 'sqlite_open';

		if (@$this->dbh = $connect_type($this->cfg['db_file_path'], 0666, $sqlite_error))
		{
			$this->connected = true;
		}

		if (DBG_LOG) dbg_log(' ', $this->cfg['log_name'] .'-connect'. ($this->connected ? '' : '-FAIL'));

		if (!$this->connected && $this->cfg['con_required'])
		{
			trigger_error($sqlite_error, E_USER_ERROR);
		}

		$this->debug('stop');
		$this->cur_query = null;
	}

	function create_table ()
	{
		$this->table_create_attempts++;
		$result = sqlite_query($this->dbh, $this->cfg['table_schema']);
		$msg = ($result) ? "{$this->cfg['table_name']} table created" : $this->get_error_msg();
		trigger_error($msg, E_USER_WARNING);
		return $result;
	}

	function query ($query, $type = 'unbuffered')
	{
		if (!$this->connected) $this->connect();

		$this->cur_query = $query;
		$this->debug('start');

		$query_function = ($type === 'unbuffered') ? 'sqlite_unbuffered_query' : 'sqlite_query';

		if (!$result = $query_function($this->dbh, $query, SQLITE_ASSOC))
		{
			if (!$this->table_create_attempts && !sqlite_num_rows(sqlite_query($this->dbh, "PRAGMA table_info({$this->cfg['table_name']})")))
			{
				if ($this->create_table())
				{
					$result = $query_function($this->dbh, $query, SQLITE_ASSOC);
				}
			}
			if (!$result)
			{
				$this->trigger_error($this->get_error_msg());
			}
		}

		$this->debug('stop');
		$this->cur_query = null;

		$this->num_queries++;

		return $result;
	}

	function fetch_row ($query, $type = 'unbuffered')
	{
		$result = $this->query($query, $type);
		return is_resource($result) ? sqlite_fetch_array($result, SQLITE_ASSOC) : false;
	}

	function fetch_rowset ($query, $type = 'unbuffered')
	{
		$result = $this->query($query, $type);
		return is_resource($result) ? sqlite_fetch_all($result, SQLITE_ASSOC) : array();
	}

	function escape ($str)
	{
		return sqlite_escape_string($str);
	}

	function get_error_msg ()
	{
		return 'SQLite error #'. ($err_code = sqlite_last_error($this->dbh)) .': '. sqlite_error_string($err_code);
	}

	function trigger_error ($msg = 'DB Error')
	{
		if (error_reporting()) trigger_error($msg, E_USER_ERROR);
	}

	function debug ($mode)
	{
		if (!$this->dbg_enabled) return;

		$id  =& $this->dbg_id;
		$dbg =& $this->dbg[$id];

		if ($mode == 'start')
		{
			$this->sql_starttime = utime();

			$dbg['sql']  = $this->cur_query;
			$dbg['src']  = $this->debug_find_source();
			$dbg['file'] = $this->debug_find_source('file');
			$dbg['line'] = $this->debug_find_source('line');
			$dbg['time'] = '';
		}
		else if ($mode == 'stop')
		{
			$this->cur_query_time = utime() - $this->sql_starttime;
			$this->sql_timetotal += $this->cur_query_time;
			$dbg['time'] = $this->cur_query_time;
			$id++;
		}
	}

	function debug_find_source ($mode = '')
	{
		foreach (debug_backtrace() as $trace)
		{
			if ($trace['file'] !== __FILE__)
			{
				switch ($mode)
				{
					case 'file': return $trace['file'];
					case 'line': return $trace['line'];
					default: return hide_bb_path($trace['file']) .'('. $trace['line'] .')';
				}
			}
		}
		return null;
	}
}