<?php
/*
* @Desc 工具类
* @Auth Sang
* @Date 2015-09-25 10:33:38
**/
namespace Lib\App;
class Util{
	public static function getMd5Sign($params,$secretkey=''){
		if(empty($params)){
			return '';
		}
		if(isset($params['sing'])){
			unset($params['sign']);
		}
		ksort($params);
		$str = [];
		foreach($params as $key=>$val){
			$val!=='' && $str[] = "{$key}={$val}";
		}
		$str = $secretkey.join('&',$str).$secretkey;
		$sign = strtoupper(md5($str));
		return $sign;
	}

	public static function getExpressList(){
		return [
			'shentong'=>'申通快递',
			'huitongkuaidi'=>'百世汇通',
			'emsguoji'=>'EMS',
			'yuantong'=>'圆通速递',//（暂只支持HtmlAPI,要JSON、XML格式结果和签收状态state请联系企业QQ 800036857 转“小佰”）
			'yunda'=>'韵达快运',//（暂只支持HtmlAPI,要JSON、XML格式结果和签收状态state请联系企业QQ 800036857 转“小佰”）
			'debangwuliu'=>'德邦物流',
			'tiandihuayu'=>'华宇物流',
			'jiajiwuliu'=>'佳吉物流',
			'shunfeng'=>'顺丰速递',
			'tiandihuayu'=>'天地华宇',
			'xinbangwuliu'=>'新邦物流',
			'yuntongkuaidi'=>'运通快递',
			'zhongtong'=>'中通速递',//（暂只支持HtmlAPI,要JSON、XML格式结果和签收状态state请联系企业QQ 800036857 转“小佰”）
			'dhl'=>'DHL-中国件',
			'tiantian'=>'天天快递',
			'other' => '其它'
		];
	}

	/**
	* 自定义显示微信头像尺寸
	* @access public
	* @param string $avatar 头像地址
	* @param int $size 尺寸范围：0最大(640x640)，46(46x46)，64(64x64)，96(96x96)，132(132x132)
	* @return string
	*/
	public static function getAvatarBySize($avatar,$size){
		if(empty($avatar)){
			return '';
		}
		if(!in_array(intval($size), [0,46,64,96,132])){
			return $avatar;
		}
		$avatar = substr($avatar,0,-1);
		return $avatar.$size;
	}
}