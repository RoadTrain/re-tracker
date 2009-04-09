<?php

class cache_common
{
	public $used = false;

	/**
	 * Returns value of variable
	 */
	public function get ($name)
	{
		return false;
	}

	/**
	 * Store value of variable
	 */
	public function set ($name, $value, $ttl = 86400)
	{
		return false;
	}

	/**
	 * Remove variable
	 */
	public function rm ($name)
	{
		return false;
	}

	/**
	 * Garbage collector
	 */
	public function gc ()
	{
		return false;
	}
}

class cache_apc extends cache_common
{
	public $used = true;

	public function __construct()
	{
		if (!$this->is_installed())
		{
			die('Error: APC extension not installed');
		}
	}

	public function get ($name)
	{
		return apc_fetch($name);
	}

	public function set ($name, $value, $ttl = 0)
	{
		return apc_store($name, $value, $ttl);
	}

	public function rm ($name)
	{
		return apc_delete($name);
	}

	protected function is_installed()
	{
		return function_exists('apc_fetch');
	}
}

class cache_memcached extends cache_common
{
	public $used = true;

	protected $cfg = null;

	protected $memcache = null;

	protected $connected = false;

	public function __construct ($cfg)
	{
		if (!$this->is_installed())
		{
			die('Error: Memcached extension not installed');
		}
		
		$this->cfg = $cfg;
		$this->memcache = new Memcache();
	}

	protected function connect ()
	{
		$connect_type = ($this->cfg['pconnect']) ? 'pconnect' : 'connect';
		
		if (@$this->memcache->$connect_type($this->cfg['host'], $this->cfg['port']))
		{
			$this->connected = true;
		}
		
		if (defined('DBG_LOG') && DBG_LOG)
		{
			dbg_log(' ', 'CACHE-connect' . ($this->connected ? '' : '-FAIL'));
		}
		
		if (!$this->connected && $this->cfg['con_required'])
		{
			die('Could not connect to memcached server');
		}
	}

	public function get ($name)
	{
		if (!$this->connected)
		{
			$this->connect();
		}
		return ($this->connected) ? $this->memcache->get($name) : false;
	}

	public function set ($name, $value, $ttl = 86400)
	{
		if (!$this->connected)
		{
			$this->connect();
		}
		$ttl = ($ttl > 86400 || !$ttl) ? 86400 : intval($ttl);
		return ($this->connected) ? $this->memcache->set($name, $value, MEMCACHE_COMPRESSED , $ttl) : false;
	}

	public function rm ($name)
	{
		if (!$this->connected)
		{
			$this->connect();
		}
		return ($this->connected) ? $this->memcache->delete($name) : false;
	}

	protected function is_installed()
	{
		return class_exists('Memcache');
	}

}

class cache_sqlite extends cache_common
{
	public $used = true;

	protected $cfg = array();

	protected $db = null;

	public function __construct ($cfg)
	{
		$this->cfg = array_merge($this->cfg, $cfg);
		$this->db = new sqlite_common($cfg);
	}

	public function get ($name)
	{
		$result = $this->db->query("
			SELECT cache_value
			FROM " . $this->cfg['table_name'] . "
			WHERE cache_name = '" . sqlite_escape_string($name) . "'
				AND cache_expire_time > " . TIMENOW . "
			LIMIT 1
		");
		
		return ($result and $cache_value = sqlite_fetch_single($result)) ? unserialize($cache_value) : false;
	}

	public function set ($name, $value, $ttl = 86400)
	{
		$name = sqlite_escape_string($name);
		$expire = TIMENOW + $ttl;
		$value = sqlite_escape_string(serialize($value));
		
		$result = $this->db->query("
			REPLACE INTO " . $this->cfg['table_name'] . "
				(cache_name, cache_expire_time, cache_value)
			VALUES
				('$name', '$expire', '$value')
		");
		
		return (bool)$result;
	}

	public function rm ($name)
	{
		$result = $this->db->query("
			DELETE FROM " . $this->cfg['table_name'] . "
			WHERE cache_name = '" . sqlite_escape_string($name) . "'
		");
		
		return (bool)$result;
	}

	public function gc ($expire_time = TIMENOW)
	{
		$result = $this->db->query("
			DELETE FROM " . $this->cfg['table_name'] . "
			WHERE cache_expire_time < $expire_time
		");
		
		return ($result) ? sqlite_changes($this->db->dbh) : 0;
	}
}

class cache_file extends cache_common
{
	public $used = true;

	protected $dir = null;

	public function __construct ($dir)
	{
		$this->dir = $dir;
	}

	public function get ($name)
	{
		$filename = $this->dir . $name . '.php';
		
		if (file_exists($filename))
		{
			require ($filename);
		}
		
		return (!empty($filecache['value'])) ? $filecache['value'] : false;
	}

	public function set ($name, $value, $ttl = 86400)
	{
		if (!function_exists('var_export'))
		{
			return false;
		}
		
		$filename = $this->dir . $name . '.php';
		$expire = TIMENOW + $ttl;
		$cache_data = array('expire'=>$expire, 'value'=>$value);
		
		$filecache = "<?php\n";
		$filecache .= '$filecache = ' . var_export($cache_data, true) . ";\n";
		$filecache .= '?>';
		
		return (bool)file_write($filecache, $filename, false, true, true);
	}

	public function rm ($name)
	{
		$filename = $this->dir . $name . '.php';
		if (file_exists($filename))
		{
			return (bool)unlink($filename);
		}
		return false;
	}

	public function gc ($expire_time = TIMENOW)
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
						
						include ($filename);
						
						if (empty($filecache['expire']) or ($filecache['expire'] < $expire_time))
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
