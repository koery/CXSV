<?php
/*
Auth:Sang
Desc:支付接口基类
Date:2014-11-01
*/
namespace Lib\Payment;
class Payment{
	protected $code;
	// 单例模式下的实例
	private static $instance;

	// 服务器通知回调地址
	private $notice_callback;

	// 支付成功时支付网关跳转到的地址
	private $url_callback;

	//支付失败时跳转到的地址
	private $error_callback='';

	protected $config;

	protected $error;

	private static $names = [
		'Alipay'=>'支付宝',
		'KuaiQian' => '快钱支付',
		'Weixin' => '微信支付',
		'Cod' => '货到付款',
		'Paytend' => '聚财通',
		'Iapay' => '爱贝云支付',
		'Balance' => '余额支付',
		'ZhongXin' => '中信微支付',
	];

	public function __construct($biz_id = ''){
		$payment = get_called_class();
		$arr = explode('\\',$payment);
		$this->code = array_pop($arr);
		unset($arr);
		if($config = $this->checkPayment($biz_id,$this->code)){
			$this->config = $config;
			$this->setNoticeCallback(site_url().'/payCallback/'.lcfirst($this->getCode()).'/asyn');
			$this->setUrlCallback(site_url().'/payCallback/'.lcfirst($this->getCode()).'/syn');
			$this->setErrorCallback(site_url().'/payCallback/error');
		}else{
			error("支付接口 [".$this->getName()."] 未启用。".$biz_id, 510);
		}
	}

	/**
	* 设置服务器通知回调地址
	* @access public
	* @param string $callback 回调地址
	* @return string
	*/
	public function setNoticeCallback($callback){
		$this->notice_callback = $callback;
	}

	/**
	* 获得服务器通知回调地址
	* @access public
	* @return string
	*/
	public function getNoticeCallback(){
		return $this->notice_callback;
	}

	/**
	* 设置URL回调地址
	* @access public
	* @param string $callback 回调地址
	* @return string
	*/
	public function setUrlCallback($callback){
		$this->url_callback = $callback;
	}

	/**
	* 获得URL回调地址
	* @access public
	* @return string
	*/
	public function getUrlCallback(){
		return $this->url_callback;
	}

	/**
	* 设置支付出错时的回调地址
	* @access public
	* @param string $callback 回调地址
	* @return string
	*/
	public function setErrorCallback($callback){
		$this->error_callback = $callback;
	}

	/**
	* 获取支付出错时的回调地址
	* @access public
	* @return string
	*/
	public function getErrorCallback(){
		return $this->error_callback;
	}

	/**
	* 获取错误信息
	* @access public
	* @return string
	*/
	public function getError(){
		return $this->error;
	}

	/**
	* 获取框架支持的所有接口
	* @access public
	* @return array
	*/
	public static function getPayments(){
		$odir = opendir(__DIR__);
		$list = [];
		while($file = readdir($odir)){
			if($file{0}!='.' && is_dir(__DIR__.'/'.$file)){
				$list[$file] = [
					'payment_code'=>$file,
					'payment_name'=>self::$names[$file],
					'config'=>'',
					'is_online'=>1,
					'enabled'=>0,
					'sort_order'=>255
				];
			}
		}
		return $list;
	}

	/**
	* 检查支付接口是否有效并返回接口配置
	* @access public
	* @param int $biz_id 商家ID
	* @param string $payment 第三方支付接口名称
	* @return array
	*/
	public function checkPayment($biz_id,$payment){
		if($biz_id==='' || empty($payment)){
			return false;
		}
		$payment_mod = model('#Payment');
		return $payment_mod->getConfig($biz_id,$payment);
	}

	/**
	* 获得一个支付接口对象
	* @access public
	* @param int $biz_id 商家ID
	* @param string $payment 第三方支付接口名称
	* @return object
	*/
	public static function getPayment($biz_id,$payment){
		if($biz_id==='' || empty($payment)){
			error("The payment '{$payment}' does not exist");
		}
		$payment = ucwords($payment);
		$key = $biz_id.'_'.$payment;
		if(!isset(self::$instance[$key])){
			$pay_class = '\\Lib\\Payment\\'.$payment.'\\'.$payment;
			if(class_exists($pay_class)){
				self::$instance[$key] = new $pay_class($biz_id);
			}else{
				error("The payment '{$payment}' does not exist");
			}
		}
		return self::$instance[$key];
	}

	/**
	* 获取支付接口配置
	* @access public
	* @return array
	*/
	public function getConfig(){
		return $this->config;
	}
	
	/**
	* 获取支付接口代码
	* @access public
	* @return string 如：alipay
	*/
	public function getCode(){
		return $this->code;
	}
}

interface iPayment{
	public function setNoticeCallback($callback);
	public function getNoticeCallback();
	public function setUrlCallback($callback);
	public function getUrlCallback();
	public function createForm($order);
	public function verifyNotice($order);
	public function verifyReturn($order);
	public static function getName();
	public function getCode();
	public static function getConfigField();
}