<?php
/*
Auth:Sang
Desc:session实现类，不同于PHP自带SESSION。该类采用MEMCACHE或其它KV缓存做为session存储
Date:2014-11-01
*/
namespace Lib;
class Session{
	private $session;
	public function __construct($config){
		switch(strtolower($config['type'])){
			case 'memcache':
				$this->session = new MemcacheSession($config);
			break;
		}
	}

	public function __call($fun,$params){
		return call_user_func_array(array($this->session,$fun), $params);
	}
}

class MemcacheSession{
	private $session_id;
	private $cache;
	private $cookie_name = 'CXSESSID';
	private $config;

	//session数据的key的前缀
	private $sess_prefix = 'sess_';

	public function __construct($config){
		$this->config = &$config;
		if(isset($config['memcache'])){
			$this->cache = new \Lib\MemcacheCache($config['memcache']);
		}
		if(isset($config['prefix']) && !empty($config['prefix'])){
			$this->sess_prefix = $config['prefix'];
		}
		if(isset($config['cookie_name']) && !empty($config['cookie_name'])){
			$this->cookie_name = $config['cookie_name'];
		}
	}

	public function start(){
		$this->session_id = cookie($this->cookie_name);
		if(empty($this->session_id)){
			$this->session_id = md5(uniqid($_SERVER['REMOTE_ADDR']).rand(1111111,9999999));
			cookie($this->cookie_name,$this->session_id);
		}
		$_SESSION = $this->load();
	}

	private function load(){
		return $this->get($this->session_id);
	}

	public function save(){
		if(!empty($_SESSION)){
			$this->set($this->session_id,$_SESSION);
		}else{
			$this->delete();
		}
	}

	public function get($session_id){
		return $this->cache ? $this->cache->get($this->sess_prefix.$session_id) : cache($this->sess_prefix.$session_id);
	}

	public function set($session_id,$session=array()){
		return $this->cache ? $this->cache->set($this->sess_prefix.$session_id,$session,$this->config['session_life']) : cache($this->sess_prefix.$session_id,$session,$this->config['session_life']);
	}

	public function delete(){
		$_SESSION = [];
		return $this->cache ? $this->cache->delete($this->sess_prefix.$this->session_id) : cache($this->sess_prefix.$this->session_id,null);
	}

	public function getSessionId(){
		return $this->session_id;
	}
}