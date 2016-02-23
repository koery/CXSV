<?php
/*
Auth:Sang
Desc:统一缓存访问接口，缓存方式在.ini配置文件里面修改
Date:2014-11-01
*/
namespace Lib;
class Cache{
	private $cache;
	public function __construct($config){
		if(!isset($config['type']) || empty($config['type'])){
			throw new \Exception("Pleace set the cache server type", 200);
		}
		if(!isset($config['host']) || empty($config['host'])){
			throw new \Exception("Pleace set the cache server host", 201);
		}
		switch(strtolower($config['type'])){
			case 'memcache':
				$this->cache = new MemcacheCache($config);
			break;
		}
	}

	public function __call($fun,$params){
		return call_user_func_array(array($this->cache,$fun), $params);
	}
}

class MemcacheCache{
	protected $memcached = false;
    protected $cache;
    //启用压缩
    protected $compress = true;
    //缓存前缀
    private $prefix = '';

	public function __construct($config){
		if(class_exists('\Memcached')){
			$this->memcached = true;
			$this->cache = new \Memcached;
		}else{
			$this->cache = new \Memcache;
		}
		if(isset($config['prefix']) && !empty($config['prefix'])){
			$this->prefix = $config['prefix'];
		}
		$this->addServer($config['host']);
	}

	public function addServer($host){
		$hosts = explode(',',$host);
		foreach($hosts as $_host){
			list($host,$port,$weight,$persistent) = explode(':',$_host);
			!$weight && $weight = 1;
			if($this->memcached){
				$this->cache->addServer($host, $port, $weight);
			}else{
				$this->cache->addServer($host, $port,$persistent,$weight);
			}
		}
	}

	public function set($key, $value, $expire = 0){
        if ($this->memcached)
        {
            return $this->cache->set($this->prefix.$key, $value, $expire);
        }
        else
        {
            return $this->cache->set($this->prefix.$key, $value, $this->compress ? MEMCACHE_COMPRESSED : 0, $expire);
        }
    }

    public function get($key){
        return $this->memcached?$this->cache->get($this->prefix.$key):$this->cache->get($this->prefix.$key);
    }

    public function delete($key){
        return $this->cache->delete($this->prefix.$key);
    }

    public function clean(){
    	return $this->cache->flush();
    }
    public function __call($method,$params){
        return call_user_func_array(array($this->cache,$method),$params);
    }
}