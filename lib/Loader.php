<?php
/**
 * 库加载器
 * @author Tianfeng.Han
 * @package SwooleSystem
 * @subpackage base
 *
 */
namespace Lib;
class Loader{
	/**
	 * 命名空间的路径
	 */
	static $nsPath;
	
	static $swoole;
	static $_objects;
	
	function __construct($swoole){
		self::$swoole = $swoole;
		self::$_objects = array(
				'model'=>[],
				'lib'=>[],
				'object'=>[]);
	}

	/**
	 * 加载一个模型对象
	 * @param $model_name 模型名称
	 * @return $model_object 模型对象
	 */
	static function loadModel($model_name='',$prefix=''){
		if(empty($model_name)){
			return new \Lib\Model;
		}
		$mod_key = $model_name;
		if(isset(self::$_objects['model'][$mod_key])){
			/**
			 * model实例化之后会被保存，整个框架常驻内存，静态的error会被保存
			 * 在加载已经存在的模型之前清空错误数据
			 */
			self::$_objects['model'][$mod_key]->clearError();
			return self::$_objects['model'][$mod_key];
		}
		else
		{
			if($model_name{0}=='#'){
				$model_name = substr($model_name,1);
				$mod = '\\Mod\\'.$model_name;
			}elseif($model_name{0}=='@'){
				$mod = '\\AppMod\\'.$model_name;
			}else{
				$mod = '\\App\\Mod\\'.$model_name;
			}
			self::$_objects['model'][$mod_key] = new $mod($model_name,$prefix);
			return self::$_objects['model'][$mod_key];
		}
	}
	/**
	 * 自动载入类
	 * 本框架中初始的namespace = __DIR__/Lib|Mod/
	 * new一個當前未包含的類會自動在Lib 或者 Mod 下搜索
	 * @param $class
	 */
	static function autoload($class){
		$root = '';
		foreach(self::$nsPath as $key=>$val){
			$key_arr = explode('\\',$key);
			$class_arr = explode('\\',trim($class,'\\'));
			if($key_arr[0]==$class_arr[0]){
				$root = $val;
				$class = substr($class,strlen($key)+1);
				break;
			}
		}
		$file_path = str_replace('\\','/', $root).str_replace('\\','/', $class).'.php';
		if(is_file($file_path)){
			include($file_path);
		}
	}
	/**
	 * 设置根命名空间
	 * @param $root
	 * @param $path
	 */
	static function setRootNS($root, $path)
	{
		self::$nsPath[$root] = $path;
	}
}