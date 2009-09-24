<?php

class cache_common
{
	public $used = false;

	/**
	 * Returns value of variable
	 */
	public function get($name)
	{
		return false;
	}

	/**
	 * Store value of variable
	 */
	public function set($name, $value, $ttl = 86400)
	{
		return false;
	}

	/**
	 * Remove variable
	 */
	public function rm($name)
	{
		return false;
	}

	/**
	 * Garbage collector
	 */
	public function gc()
	{
		return false;
	}
}

class cache_memcached extends cache_common
{
	public $used = true;

	protected $cfg = null;

	protected $memcache = null;

	protected $connected = false;

	public function __construct($cfg)
	{
		if (!$this->is_installed())
		{
			die('Error: Memcached extension not installed');
		}
		
		$this->cfg = $cfg;
		$this->memcache = new Memcache();
	}

	protected function connect()
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

	public function get($name)
	{
		if (!$this->connected)
		{
			$this->connect();
		}
		return ($this->connected) ? $this->memcache->get($name) : false;
	}

	public function set($name, $value, $ttl = 86400)
	{
		if (!$this->connected)
		{
			$this->connect();
		}
		$ttl = ($ttl > 86400 || !$ttl) ? 86400 : intval($ttl);
		return ($this->connected) ? $this->memcache->set($name, $value, FALSE, $ttl) : false;
	}

	public function rm($name)
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