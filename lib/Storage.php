<?php
/*
Auth:Sang
Desc:文件储存类
Date:2014-11-01
*/
namespace Lib;
class Storage{
	private $allow_type = 'jpg,gif,bmp,jpeg,png,zip,rar,WebP';

	public function __construct($config){
		if(!isset($config['type']) || empty($config['type'])){
			throw new \Exception("Not set storage type!", 210);
		}
		$type = $config['type'];
		unset($config['type']);
		$class_name = 'Lib\\'.ucwords($type).'Storage';
		if(!class_exists($class_name)){
			throw new \Exception("The storage type {$type} don't exist!", 200);
		}
		!isset($config['allow_type']) && $config['allow_type'] = $this->allow_type;
		$this->storage = new $class_name($config);
	}

	public function __call($method,$params){
		return call_user_func_array(array($this->storage,$method),$params);
	}
}

class AliossStorage implements iStorage{
	private $client;
	private $bucket;
	private $allow_type;
	public function __construct($config){
		\Lib\Loader::setRootNS('Aliyun',LIB_PATH.'AliOss/src/Aliyun/');
		\Lib\Loader::setRootNS('Guzzle\\Parser',LIB_PATH.'AliOss/libs/guzzle/parser/Guzzle/Parser/');
		\Lib\Loader::setRootNS('Guzzle\\Http',LIB_PATH.'AliOss/libs/guzzle/http/Guzzle/Http/');
		\Lib\Loader::setRootNS('Guzzle\\Common', LIB_PATH.'AliOss/libs/guzzle/common/Guzzle/Common/');
		\Lib\Loader::setRootNS('Guzzle\\Plugin' , LIB_PATH.'AliOss/libs/guzzle/plugin/Guzzle/Plugin/');
		\Lib\Loader::setRootNS('Guzzle\\Stream', LIB_PATH.'AliOss/libs/guzzle/stream/Guzzle/Stream/');
		\Lib\Loader::setRootNS('Symfony\\Component\\EventDispatcher' , LIB_PATH.'AliOss/libs/symfony/event-dispatcher/Symfony/Component/EventDispatcher/');
		\Lib\Loader::setRootNS('Symfony\\Component\\ClassLoader' , LIB_PATH.'AliOss/libs/symfony/class-loader/Symfony/Component/ClassLoader/');
		$this->client = \Aliyun\OSS\OSSClient::factory(array(
			'AccessKeyId' => $config['accessKeyId'],
	        'AccessKeySecret' => $config['accessKeySecret'],
		));
		$this->bucket = $config['bucket'];
		$this->allow_type = explode(',',$config['allow_type']);
		$this->domain = $config['domain'];
	}

	public function get($file_path){
		return $this->client->getObject(array(
	        'Bucket' => $this->bucket,
	        'Key' => $file_path,
	    ));
	}

	public function save($file_path){
		$argvs = func_get_args();
		if($file_path{0} == '@'){
			$file_path = substr($file_path, 1);
			//检查文件类型
			list($type,$content_type) = get_mime($file_path);
			if(!in_array($type, $this->allow_type)){
				throw new \Exception("Not allowed file types", 201);
			}
			$content = fopen($file_path,'r');
			$key = __createFileName().'.'.$type;
			$params = isset($argvs[1]) && is_array($argvs[1]) ? $argvs[1] : array();
			$params['ContentLength'] = filesize($file_path);
			$params['ContentType'] = $content_type;
			if(isset($params['path']) && !empty($params['path'])){
				$key = trim($params['path'],'/').'/'.$key;
				unset($params['path']);
			}
		}elseif($file_path{0} != '@' && is_string($argvs[1])){
			$content = $argvs[1];
			$file_info = explode('.',$file_path);
			$type = array_pop($file_info);
			$mimes = \Lib\Mimes::types();
			if(isset($mimes[$type])){
				$content_type = $mimes[$type];
			}else{
				$content_type = 'text/plain';
			}
			$key = $file_path;
			$params = isset($argvs[2]) && is_array($argvs[2]) ? $argvs[2] : array();
			$params['ContentType'] = $content_type;
		}else{
			throw new \Exception("If local file,useing like storage(@/path/to/file,array()),else useing like storage(filename.type,'content',array())", 202);
		}

		//30天过期
		$params['Expires'] = new \DateTime('+30 days');
		
		$data = array(
	        'Bucket' => $this->bucket,
	        'Key' => $key,
	        'Content' => $content,
	    );
	    $data = array_merge($params,$data);
		$this->client->putObject($data);
		if(is_resource($content)){
			fclose($content);
		}
		return trim($this->domain,'/').'/'.$key;
	}

	public function listFiles($params){
		return [];
	}

	public function delete($path){

	}

	public function setAllowType($allow_type){
		$this->allow_type = explode(',',$allow_type);
	}
}

class LocalStorage implements iStorage{
	private $path = '/home/storage/';
	private $domain;
	private $allow_type;
	public function __construct($config){
		isset($config['path']) && !empty($config['path']) && $this->path = trim($config['path'],'/').'/';
		(isset($config['domain']) && !empty($config['domain']) && $this->domain = rtrim($config['domain'],'/').'/') || $this->domain = dom('www').'/';
		$this->allow_type = explode(',',$config['allow_type']);
	}

	public function get($file_path){
		$path = $this->path.$file_path;
		return file_get_contents($path);
	}

	public function save($file_path){
		$argvs = func_get_args();
		if($file_path{0} == '@'){
			$file_path = substr($file_path, 1);
			if(!file_exists($file_path)){
				throw new \Exception("File {$file_path} not exist", 202);
			}
			//检查文件类型
			list($type,$content_type) = get_mime($file_path);
			if(!in_array($type, $this->allow_type)){
				throw new \Exception("Not allowed file types", 201);
			}
			$params = isset($argvs[1]) && is_array($argvs[1]) ? $argvs[1] : array();
			$dest_file = __createFileName().'.'.$type;
			if(isset($params['path']) && trim($params['path'])){
				$dest_file = trim($params['path'],'/').'/'.$dest_file;
			}
			$save_path = $this->path.$dest_file;
			$dir = dirname($save_path);
			if(!is_dir($dir)){
				mkdir($dir,0755,true);
			}
			copy($file_path, $save_path);
			return trim($this->domain,'/').'/'.$dest_file;
		}elseif($file_path{0} != '@' && is_string($argvs[1])){
			$file_path = ltrim($file_path,'/');
			$content = $argvs[1];
			$params = isset($argvs[2]) && is_array($argvs[2]) ? $argvs[2] : array();
			$crc32 = absint(crc32($file_path))%2000;
			$save_path = $this->path.$crc32.'/'.$file_path;
			$dir = dirname($save_path);
			if(!is_dir($dir)){
				mkdir($dir,0755,true);
			}
			file_put_contents($save_path, $content);
			return trim($this->domain,'/').'/'.$crc32.'/'.$file_path;
		}else{
			throw new \Exception("If local file,useing like storage(@/path/to/file,array()),else useing like storage(filename.type,'content',array())", 202);
		}

		return false;
	}

	public function delete($path){
		if(is_url($path)){
			$path_info = parse_url($path);
			$path = ltrim($path_info['path'],'/');
		}
		$file = $this->path.$path;
		if(is_file($file)){
			unlink($file);
		}
		return true;
	}

	public function listFiles($params){
		$path = val($params,'path','');
		$order = val($params,'order','');
		if(!empty($order) && !in_array($order, ['size','type','name'])){
			$order = '';
		};
		$path = $this->path.trim($path,'/');
		$files = $this->getFiles($path);
		if(!empty($order)){
			usort($files,$this->cmp_func($order));
		}
		return $files;
	}

	public function getFiles($path){
		if(!is_dir($path)){
			return [];
		}
		$odir = opendir($path);
		$files = [];
		while($file = readdir($odir)){
			if($file{0}=='.') continue;
			$file_path = $path.'/'.$file;
			$dir_path = str_replace($this->path,'',$file_path);
			if(is_dir($file_path)){
				$files[] = [
					'is_dir' => true,
					'has_file' => (count(scandir($file_path))>2),
					'filesize' => 0,
					'is_photo' => false,
					'filetype' => '',
					'filename' => $file,
					'datetime' => date('Y-m-d H:i:s',filemtime($file_path)),
				];
			}else{
				$file_ext = explode('.',trim($file));
				$file_ext = strtolower(array_pop($file_ext));
				$files[] = [
					'is_dir' => false,
					'has_file' => false,
					'filesize' => filesize( $file_path ),
					'dir_path' => $this->domain.$dir_path,
					'is_photo' => in_array($file_ext, $this->allow_type),
					'filetype' => $file_ext,
					'filename' => $file,
					'datetime' => date('Y-m-d H:i:s',filemtime($file_path)),
				];
			}
		}
		closedir($odir);
		return $files;
	}

	public function setAllowType($allow_type){
		$this->allow_type = explode(',',$allow_type);
	}

	public function cmp_func($order){
		return function($a, $b) use($order){
			if ( $a['is_dir'] && !$b['is_dir'] ){
				return -1;
			}elseif( !$a['is_dir'] && $b['is_dir'] ){
	      		return 1;
			}else{
	    		if($order == 'size'){
	    			if ($a['filesize'] > $b['filesize']){
	    				return 1;
	    			}elseif($a['filesize'] < $b['filesize']){
	    				return -1;
	    			}else{
	    				return 0;
	    			}
	    		}elseif($order == 'type'){
	    			return strcmp($a['filetype'], $b['filetype']);
	    		}else{
	    			return strcmp($a['filename'], $b['filename']);
	    		}
	    	}
	  	};
	}
}

class GridfsStorage implements iStorage{
	private $mdb;
	private $domain;
	private $allow_type;

	public function __construct($config){
		(isset($config['domain']) && !empty($config['domain']) && $this->domain = rtrim($config['domain'],'/').'/') || $this->domain = dom('www').'/';
		$this->mdb = mongodb(val($config,'table','store'),true);
		$this->allow_type = explode(',',$config['allow_type']);
	}
	public function get($file_path){
		if($this->is_filename($file_path)){
			return $this->mdb->where(['filename'=>trim($file_path,'/')])->getFile();
		}else{
			return $this->mdb->where(['_id'=>$file_path])->getFile();
		}
	}

	public function save($file_path){
		$argvs = func_get_args();
		if($file_path{0}=='@'){
			$file_path = substr($file_path,1);
			if(!is_file($file_path)){
				error('Invalid file',0);
			}
			$mime = get_mime($file_path);
			if(!in_array($mime[0], $this->allow_type)){
				error('invalid file type',0);
			}
			$params = [
				'contentType' => $mime[1],
				'_id' => md5(uniqid(microtime(true).''.rand(10000,99999),true)),
			];
			$path = '';
			if(isset($argvs[1]) && !empty($argvs[1])){
				$params2 = &$argvs[1];
				if(isset($params2['id']) && !empty($params2['id'])){
					$params['_id'] = $params2['id'];
					unset($params2['id']);
				}
				$file_name = $params['_id'].'.'.$mime[0];
				if(isset($params2['path']) && !empty($params2['path'])){
					$path = trim($params2['path'],'/').'/';
					unset($params2['path']);
				}
				$params = array_merge($params2,$params);
			}
			$params['filename'] = $path.$params['_id'].'.'.$mime[0];
			$options = val($argvs,2,[]);
			$ret = $this->mdb->storeFile($file_path,$params,$options);
			if($ret!==false){
				return trim($this->domain,'/').'/'.$params['filename'];
			}else{
				return $this->mdb->getError();
			}
		}elseif(is_string($argvs[1]) && !empty($argvs[1])){
			$type = explode('.',$file_path);
			$type = array_pop($type);
			$mimes = \Lib\Mimes::types();
			$mime = val($mimes,$type,'file');
			if(!in_array($type, explode(',',$this->allow_type))){
				error('invalid file type',0);
			}
			$id = md5($file_path);
			$params = [
				'_id' => $id,
			];
			$params['filename'] = $file_path;
			if(isset($argvs[2]) && !empty($argvs[2])){
				$params = array_merge($params2,$argvs[2]);
			}
			$options = val($argvs,3,[]);
			return $this->mdb->storeBytes($argvs[1],$params,$options);
		}
	}

	public function listFiles($params){
		return $this->mdb->where($params)->select();
	}

	public function delete($file_path){
		if($this->is_filename($file_path)){
			return $this->mdb->where(['filename'=>$file_path])->delete();
		}else{
			return $this->mdb->deleteFile($file_path);
		}
	}

	private function is_filename($file_path){
		if(count(explode('.',$file_path))>=2) return true;
		else return false;
	}
} 

interface iStorage{
	public function get($file_path);
	public function save($file_path);
	public function listFiles($params);
	public function delete($file_path);
}

function __createFileName(){
	$filename = md5(uniqid(session('@id'),true));
	$crc32 = absint(crc32($filename))%2000;
	return $crc32.'/'.$filename;
}