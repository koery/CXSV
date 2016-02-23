<?php
/*
* @Desc 余额支付
* @Auth Sang
* @Date 2015-12-28 17:36:24
**/
namespace Lib\Payment\Balance;
use Lib\Payment\Payment;
use Lib\Payment\iPayment;
class Balance extends Payment implements iPayment{
	public function createForm($url){
		return "
<script>
window.location.replace('{$url}');
</script>
		";
	}

	/**
	* 获取支付代码
	* @access public
	* @return string
	*/
	public function getCode(){
		return $this->code;
	}

	/**
	* 获取支付名称
	* @access public
	* @return string
	*/
	public static function getName(){
		return '余额支付';
	}

	public function verifyNotice($order){
		return true;
	}
	public function verifyReturn($order){
		return true;
	}
	public static function getConfigField(){
		return [];
	}
}