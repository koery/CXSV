<?php
/*
Auth:Sang
Desc:日志写入文件
Date:2014-11-01
*/
namespace Lib;
use Lib\Log;
class Loger extends Log{
	private $file_name;
	private static $instance = null;
	private function __construct($prefix=''){
		if($prefix){
			$this->file_name = LOG_PATH.$prefix.'_'.date('Y-m-d').'.log';
		}else{
			$this->file_name = LOG_PATH.date('Y-m-d').'.log';
		}
	}
	public function put($msg,$level=self::INFO){
		if(is_array($msg)){
			$msg = var_export($msg,true);
		}
		$log = $this->format($msg,$level);
		$dir = dirname($this->file_name);
		if(!is_dir($dir)){
			mkdir($dir,0755,true);
		}
		$ofile = fopen($this->file_name, 'a');
		fwrite($ofile, $log);
		fclose($ofile);
	}

	public static function getInstance($prefix=''){
		if(self::$instance === null){
			self::$instance = new self($prefix);
		}
		return self::$instance;
	}

	/**
	* 往MONGODB写日志
	* 需要mongodb支持，新建数据表：syslog，并限定长度为100000条
	* @access public
	* @param string $var
	* @return void
	*/
	public function mLog($act,$request_method){
		try{
			$mdb = mongodb('syslog_'.APP_NAME);
			if((in_array($request_method,['add','post','update']) && is_post()) || $request_method=='delete'){
				if(isset($_POST['password'])){
					$_POST['password'] = '******';
				}
				$user_info = session('user_info');
				$data = [
					'username' => val($user_info,'user_name'),
					'url' => \Lib\Common::getActionUrl(),
					'referer' => str_replace(site_url(),'',$_SERVER['HTTP_REFERER']),
					'action' => ['post'=>'提交','add'=>'添加','update'=>'修改','delete'=>'删除'][$request_method],
					'query_str' => isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '',
					'data' => $_POST,
					'op_time' => date('Y-m-d H:i:s'),
					'timestamp' => time(),
				];
				$mdb->insert($data);
			}
		}catch(\Exception $e){
			m_log(__METHOD__.' : '.$e->getMessage());
		}
	}

	/**
	* 读取操作日志
	* @access public
	* @param array $condition
	* @return void
	*/
	public function readLog($condition=[],$offset=0,$size=100,$order=''){
		$log_db = 'syslog_'.APP_NAME;
		$mdb = mongodb($log_db);
		$count = $mdb->where($condition)->count();
		return ['count'=>$count,'data'=>$mdb->where($condition)->limit($offset,$size)->order($order)->select()];
	}
}