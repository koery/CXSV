<?php
//取得命令
if (empty($app_name = $argv[1])) {
    exit("Usage: appServer.php app_name" . PHP_EOL);
}

//PHP版本
if(version_compare(PHP_VERSION, '5.4.0','<')){
	exit('php version >=5.4.0 require'.PHP_EOL);
}

//是否安装了swoole扩展
if(!get_extension_funcs('swoole')){
	exit('HttpServer need swoole extension'.PHP_EOL);
}

//swoole版本检查
if (version_compare(SWOOLE_VERSION, '1.7.16', '<')) {
    exit("HttpServer Swoole >= 1.7.16 required " . PHP_EOL);
}


//需要exec执行命令
if (!function_exists('exec')) {
    exit("HttpServer must enable exec " . PHP_EOL);
}

//读取配置文件
$config_file = __DIR__.'/etc/'.$app_name.'.ini';
if (!is_file($config_file) || false === $config = parse_ini_file($config_file, true)) {
    echo 'the config file no such or no parse' . PHP_EOL;
    return;
}
unset($config_file);

//检查配置
checkConfig($config);

// 默认密钥
define('ADM_ENCRYPT_KEY', 'J60d46rzOz6eWa3gAzyuH8B4');

// 公共类库
define('LIB_PATH',__DIR__.'/lib/');

// 公共模型
define('MOD_PATH',__DIR__.'/mod/');

// 框架根目录
define('FRAME_ROOT',__DIR__.'/');//设置公共常量
define('SERVER_NAME','yedadou.com');

// 管理员邮箱
define('ADMIN_EMAIL','190276134@qq.com');

// 网站根目录
define('DOCUMENT_ROOT',rtrim($config['server']['document_root'],'/').'/');

// 应用名称
define('APP_NAME',$app_name);

// 临时文件目录
define('TEMP_PATH',DOCUMENT_ROOT.'Temp/'.APP_NAME.'/');

// 应用根目录
define('APP_PATH',rtrim($config['app']['app_path'],'/').'/');

// 模板目录
define('TPL_PATH',APP_PATH.'Tpl/');

// 挂件目录
define('WIDGET_PATH',DOCUMENT_ROOT.'Widget/');

// 日志目录
define('LOG_PATH',DOCUMENT_ROOT.'Log/'.APP_NAME.'/');

// 内存文件路径
define('SHM_PATH','/dev/shm/'.SERVER_NAME.'/'.APP_NAME.'/');

// 定时器脚本路径
define('CRON_PATH',DOCUMENT_ROOT.'Cron/');

// 创建应用目录结构
foreach(['Mod','Widget','Plugin','Cron','Task','Lib'] as $item){
  $path = DOCUMENT_ROOT.$item;
  if(!is_dir($path)){
    mkdir($path,0755,true);
    chown($path, 'www');
    chgrp($path, 'www');
  }
}

// 创建控制器目录
if(!is_dir(APP_PATH.'Act')){
  mkdir(APP_PATH.'Act',0755,true);
  chown(APP_PATH.'Act', 'www');
  chgrp(APP_PATH.'Act', 'www');
}

// 创建模板目录
if(!is_dir(TPL_PATH)){
  mkdir(TPL_PATH,0755,true);
  chown(TPL_PATH, 'www');
  chgrp(TPL_PATH, 'www');
}

// 创建临时文件
if(!is_dir(TEMP_PATH)){
  mkdir(TEMP_PATH,0777,true);
  chown(TEMP_PATH, 'www');
  chgrp(TEMP_PATH, 'www');
}

// 创建日志目录
if(!is_dir(LOG_PATH)){
  mkdir(LOG_PATH,0777,true);
  chown(LOG_PATH, 'www');
  chgrp(LOG_PATH, 'www');
}

//调试模式
if(isset($config['debug']) && !empty($config['debug'])){
  define('DEBUG',1);
  // error_reporting(0);
}else{
  error_reporting(0);
}

##mark_load.php  用于搜索 （下同）
// 全局全共函数
require __DIR__.'/load.php';

// 全局唯一挂载对象
global $php;

// 创建服务器实例
$server = new \Lib\AppServer($config);

##一个请求到达被onRequest处理之后的回调函数设置
// 设置请求回调
$server->setProcReqFun(array($server,'start'));
$php = &$server;
##mark_run 
// 启动
$server->run();


function checkConfig(&$config){
	extract($config);
	//检查服务器设置
	extract($server,EXTR_OVERWRITE);
	if(!isset($document_root) || empty($document_root)){
		exit('HttpServer must set the server.document_root');
	}
	if(!isset($host) || empty($host)){
		$config['server']['host'] = '127.0.0.1';
	}
	if(!isset($port) || empty($port)){
		exit('HttpServer must set the server.port');
	}
	// 检查session设置
	extract($session,EXTR_OVERWRITE);
	if(!isset($type) || empty($type)){
		exit('HttpServer must set the session.type');
	}
	if(!isset($session_life) || empty($session_life)){
		exit('HttpServer must set the session.session_life');
	}

	// 检查APP设置
	extract($app,EXTR_OVERWRITE);
	if(!isset($app_path) || empty($app_path)){
		exit('HttpServer must set the app.app_path');
	}

	// 检查CACHE设置
	extract($cache,EXTR_OVERWRITE);
	if(!isset($type) || empty($type)){
		exit('HttpServer must set the cache.type');
	}
	if(!isset($host) || empty($host)){
		exit('HttpServer must set the cache.host');
	}

	// 检查数据库设置
	extract($db,EXTR_OVERWRITE);
	if((!isset($host) || empty($host)) && ((!isset($read) || empty($read)) || (!isset($write) || empty($write)))){
		exit('HttpServer must set the db.host || db.read && db.write link');
	}

	// 检查储存系统设置
	extract($storage,EXTR_OVERWRITE);
	if(!isset($type) || empty($type)){
		exit('HttpServer must set the storage.type');
	}
}