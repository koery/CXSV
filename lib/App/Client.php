<?php
/*
* @Desc 接口调用类
* @Auth Sang
* @Date 2015-09-17 18:04:52
**/
namespace Lib\App;
class Client{
	private $api_gateway = 'http://api.yedadou.com/yedadou/';//野大豆API服务器地址
	private $wxapi_gateway = 'http://$APPID$.wxapi.yedadou.com/';//野大豆微信接口服务器地址
	private $msgapi_gateway = 'http://msg.yedadou.com/';//野大豆消息服务器地址

	private static $instance;

	private function __construct(){
		// 获得API URL配置
		$api_gateway = Config::get('ydd_api_url');
		$wxapi_gateway = Config::get('wx_api_url');
		$msgapi_gateway = Config::get('wxmsg_api_url');

		$api_gateway && $this->api_gateway = $api_gateway;
		$wxapi_gateway && $this->wxapi_gateway = $wxapi_gateway;
		$msgapi_gateway && $this->msgapi_gateway = $msgapi_gateway;
	}

	/**
	* 单例入口
	* @access public
	* @return void
	*/
	public static function getInstance(){
		if(empty(self::$instance)){
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * 野大豆接口调用方法
	 * @param string $api_name 方法名 格式:类名/方法名
	 * @param array $params 提交的参数
	 * @param string $token 授权码
	 * @param boolean $isDebug 是否需要打印调试信息
	 * @param boolean $isJsonDecode 返回的结果是否需要 json_decodel转换
	 * @return 返回一个json化的字符串
	 */
	public function yddApi($api_name,$params,$isDebug=false){
		$remote_server=$this->api_gateway.$api_name;
		$returnData=$this->getData($remote_server, $params,$isDebug);
		return $returnData;
	}

	/**
	 * 野大豆微信API接口调用方法
	 * @param string $api_name 方法名 格式:类名/方法名
	 * @param string $public_id 公众号ID
	 * @param array $params 提交的参数
	 * @param boolean $isDebug 是否需要打印调试信息
	 * @return array
	 */
	public function wxApi($api_name,$public_id,$params,$isDebug=false){
		if(empty($public_id)){
			return [
				'error' => true,
				'msg' => 'invalid public_id',
				'sub_msg' => '公众号ID不能为空',
			];
		}
		$remote_server=str_replace('$APPID$',$public_id,$this->wxapi_gateway).$api_name;
		$returnData=$this->getData($remote_server, $params,$isDebug);
		return $returnData;
	}

	/**
	 * 野大豆消息接口调用方法
	 * @param string $api_name 方法名 格式:类名/方法名
	 * @param string $public_id 公众号ID
	 * @param array $params 提交的参数
	 * @param string $token 授权码
	 * @param blooean $isDebug 授权码
	 * @param boolean $isDebug 是否需要打印调试信息
	 * @param boolean $isJsonDecode 返回的结果是否需要 json_decodel转换
	 * @return \Act\Yedadou\unknown
	 */
	public function msgApi($api_name,$public_id,$params,$isDebug=false){
		
		if(empty($public_id)){
			return [
				'error' => true,
				'msg' => 'invalid public_id',
				'sub_msg' => '公众号ID不能为空',
			];
		}
		$params['public_id'] = $public_id;
		$remote_server=$this->msgapi_gateway.$api_name;
		$returnData=$this->getData($remote_server, $params,$isDebug);
		return $returnData;
	}

	/**
	* 请求数据
	* @access private
	* @param string $url
	* @param array $params
	* @param string $token
	* @param bool $isDebug
	* @return void
	*/
	private function getData($url,$params,$isDebug=false){
		try{
			$path_arr = explode('/',$url);
			$method = array_pop($path_arr);
			if($method=='get'){
				$method = 'get';
			}else{
				$method = 'post';
			}
			$ret = curl($url,$method,$params);
			if(!$isDebug){
				return json_decode($ret,true);
			}else{
				return $ret;
			}
		}catch(\Exception $e){
			return ['error'=>1,'msg'=>$e->getCode(),'sub_msg'=>$e->getMessage()];
		}
	}
}
