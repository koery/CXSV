<?php
/*
* @Desc 中信银行公众账号支付接口
* @Auth Sang
* @Date 2016-01-28 16:16:00
**/
namespace Lib\Payment\ZhongXin;
use Lib\Payment\Payment;
use Lib\Payment\iPayment;
use Lib\Common;
class ZhongXin extends Payment implements iPayment{
	private $gateway = 'https://pay.swiftpass.cn/';

	/**
	* 预下单
	* @access public
	* @param string $var
	* @return void
	*/
	public function unifiedorder($order_info){
		try{
			if(!val($order_info,'order_sn')){
				return $this->msg('FAIL','invalid order_sn');
			}
			if(!val($order_info,'title')){
				return $this->msg('FAIL','invalid order title');
			}
			if(!val($order_info,'openid')){
				return $this->msg('FAIL','invalid openid');
			}
			if(!val($order_info,'total_fee')){
				return $this->msg('FAIL','invalid total_fee');
			}
			$client_ip = val($_SERVER,'REMOTE_ADDR');
			!$client_ip && $client_ip = Common::getIp();
			if(strpos($client_ip, ',')!==false){
				$client_ip = explode(',',$client_ip);
				$client_ip = val($client_ip,0);
			}
			$data = [
				'service' => 'pay.weixin.jspay',
				'version' => '1.0',
				'charset' => 'UTF-8',
				'sign_type' => 'MD5',
				'mch_id' => $this->config['mch_id'],
				'is_raw' => $this->config['is_raw'],
				'out_trade_no' => $order_info['order_sn'],
				'body' => $order_info['title'],
				'sub_openid' => $order_info['openid'],
				'attach' => $order_info['attach'],
				'total_fee' => $order_info['total_fee'],
				'mch_create_ip' => $client_ip,
				'notify_url' => $this->getNoticeCallback(),
				'callback_url' => $this->getUrlCallback(),
				'time_start' => date('YmdHis'),
				'time_expire' => date('YmdHis',time()+86400),
				'goods_tag' => $order_info['goods_tag'],
				'nonce_str' => Common::getRandString(32,true),
			];
			$data['sign'] = $this->getSign($data);
			$xml = $this->toXml($data);
			$api_result = curl($this->gateway.'pay/gateway','post',$xml);
			$api_result = $this->toArray($api_result);
			if(!is_array($api_result) || !isset($api_result['status'])){
				return $this->msg('FAIL','SYSTEM ERROR!');
			}
			if($api_result['status']>0){
				return $this->msg($api_result['status'],$api_result['message']);
			}
			if(isset($api_result['result_code']) && $api_result['result_code']>0){
				return $this->msg($api_result['err_code'],$api_result['err_msg']);
			}
			return $api_result;
		}catch(\Exception $e){
			return $this->msg($e->getCode,$e->getMessage());
		}

	}
	public function createForm($prepay){
		if($this->config['is_raw']==0){
			$href = $this->gateway.'pay/jspay?token_id='.$prepay['token_id'].'&showwxpaytitle=1';
			return ['is_raw' => 0,'href' => $href];
		}else{
			$js_config = $prepay['pay_info'];
			return ['is_raw' => 1,'js_config' => $js_config];
		}
	}
	public function queryOrder($order_sn){
		$data = [
			'service' => 'unified.trade.query',
			'version' => '1.0',
			'charset' => 'UTF-8',
			'sign_type' => 'MD5',
			'mch_id' => $this->config['mch_id'],
			'out_trade_no' => $order_sn,
			'nonce_str' => Common::getRandString(32,true),
		];
		$data['sign'] = $this->getSign($data);
		$xml = $this->toXml($data);
		$api_result = curl($this->gateway.'pay/gateway','post',$xml);
		$api_result = toArray($api_result);
		return $api_result;
	}

	public function verifyNotice($order){
		if(!isset($order['sign'])){
			return $this->msg('FAIL','empty result sign');
		}
		$result_sign = $order['sign'];
		unset($order['sign']);
		$sign = $this->getSign($order);
		return $sign == $result_sign;
	}
	public function verifyReturn($order){
		return true;
	}
	public function getSign(&$params){
		$new_params = [];
		foreach($params as $key=>$val){
			if($val==''){
				unset($params[$key]);
			}else{
				$new_params[] = "{$key}={$val}";
			}
		}
		sort($new_params);
		$new_params[] = "key=".$this->config['secret_key'];
		return strtoupper(md5(join('&',$new_params)));
	}

	public static function getName(){
		return '中信微支付';
	}
	public function getCode(){
		return $this->code;
	}
	public static function getConfigField(){
		return [
			'mch_id' => [
				'text' => '商户号',
				'type' => 'text'
			],
			'secret_key' => [
				'text' => '密钥',
				'type' => 'text',
			],
			'is_raw' => [
				'text' => '支付方式',
				'type' => 'select',
				'items' => [
					'0' => '跳转支付',
					'1' => 'JS原生支付'
				],
			],
			'app_id' => [
				'text' => '公众号ID',
				'type' => 'text',
				'desc' => '关联到中信的公众号ID'
			],
			'app_secret_key' => [
				'text' => '公众号密钥',
				'type' => 'text',
				'desc' => '公众号的secretKey'
			],
		];
	}

	private function toXml($data){
		if(empty($data) || !is_array($data)){
			return '';
		}
		$str = '<xml>';
		foreach ($data as $key => $value) {
			if(in_array($key, ['body','attach'])){
				$value = "<![CDATA[{$value}]]>";
			}
			$str .= "<{$key}>{$value}</{$key}>";
		}
		$str .= '</xml>';
		return $str;
	}

	private function toArray($xml){
		return get_object_vars_deep(simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NOCDATA));
	}

	private function msg($code,$msg){
    	return ['err_code'=>$code,'err_code_des'=>$msg];
    }
}