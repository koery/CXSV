<?php
/*
* @Desc redis驱动
* @Auth Sang
* @Date 2016-01-08 17:15:59
**/
namespace Lib;
class Redis{
	private $redis;
	public function __construct($config){
		if(!class_exists('Redis')){
			throw new \Exception("Can not find the Redis class", 300);
		}
		if(!isset($config['host']) || !isset($config['port'])){
			throw new \Exception("Pleace set the redis server host or port", 301);
		}
		$this->redis = new \Redis();
		$this->redis->connect($config['host'],$config['port']);
		if(isset($config['auth'])){
			$this->redis->auth($config['auth']);
		}
		if(($ping = $this->redis->ping())!='+PONG'){
			throw new \Exception($ping, 302);
		}
	}

	public function __call($fun,$args){
		return call_user_func_array([$this->redis,$fun], $args);
	}
}