<?php
/*
* @Desc 聚财通支付接口
* @Auth Sang
* @Date 2015-11-26 11:05:41
**/
namespace Lib\Payment\Paytend;
use Lib\Payment\Payment;
use Lib\Payment\iPayment;
use Lib\Common;
class Paytend extends Payment implements iPayment{
	// 接口地址
	private $gateway = 'https://pay.paytend.com/api/wxpay/';

	/**
	* 创建JSAPI支付表单
	* @access public
	* @param string $order_info
	* @return string
	*/
	public function createForm($order){
		$params = [
			'merchantId' => $this->config['merchantId'],
			'out_trade_no' => val($order,'out_trade_no'),
			'total_fee' => val($order,'total_fee',0),
			'channelType' => '01',
			'sub_mch_notify_url' => $this->getNoticeCallback(),
			'sub_mch_return_url' => $this->getUrlcallback(),
			'body' => val($order,'body',''),
			'nonce_str' => \Lib\Common::getRandString(32,true),
		];
		$sign = $this->getSign($params);
		$params['sign'] = $sign;
		$js_pay_url = $this->gateway.'jsapi_pay.htm?'.array2query($params);
		return '
<!DOCTYPE html>
<html>
<head>
	<title>正在发起支付</title>
</head>
<body>
<script>
window.location.replace("'.$js_pay_url.'");
</script>
</body>
</html>
		';
	}

	/**
	* 获取签名
	* @access public
	* @param array $params
	* @return void
	*/
	public function getSign($params){
		$new_params = [];
		foreach($params as $key=>$val){
			!empty($val) && $new_params[] = "{$key}={$val}";
		}
		sort($new_params);
		$new_params[] = "key=".$this->config['key'];
		// print_r(join('&',$new_params));exit;
		return strtoupper(md5(join('&',$new_params)));
	}

	/**
	* 验证订单--异步
	* @access public
	* @param array $order 订单信息，包含字段：
			* id 订单ID
			* biz_id 商家ID
			* public_id 公众号ID
			* order_sn 订单号
			* title 订单简述
			* order_amount 订单总金额
			* ext 扩展数据
			* trade_sn 预支付单号
	* @return bool
	*/
	public function verifyNotice($order){
		return true;
	}

	/**
	* 获取通知结果
	* @access public
	* @param string 
	* @return array
	*/
	public function getNotice(){
		return $_POST;
	}


	/**
	* 验证订单--同步
	* @access public
	* @param string $var
	* @return void
	*/
	public function verifyReturn($order){
		return true;
	}

	/**
	* 获取支付名称
	* @access public
	* @return string
	*/
	public static function getName(){
		return '聚财通';
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
	* 查询订单有效性
	* @access public
	* @param string $out_trade_no 商户订单号
	* @param bool $is_order_sn 是不是商户订单号，true为根据商户订单号查询, false为查询微信返回的交易号
	* @return array 订单信息
	*/
	public function queryOrder($out_trade_no){
		$data = [
			'merchantId' => $this->config['merchantId'],
			'nonce_str' => Common::getRandString(32,true),
		];
		
		$data['out_trade_no'] = $out_trade_no;
		$sign = $this->getSign($data);
		$data['sign'] = $sign;
		$result = $this->postData('queryOrder.htm',array2query($data));
		return json_decode($result,true);
	}

	/**
	* 获取配置表单
	* @access public
	* @return array
	*/
	public static function getConfigField(){
        return [
        	'merchantId' => [
        		'text'  => '商户ID',
        		'type'  => 'text',
        	],
            'key'       => array(        //密钥
                'text'  => '密钥(key)',
                'type'  => 'text',
            ),
        ];
    }

    /**
    * 提交请求
    * @access private
    * @param array $order_info
    * @return array
    */
    private function postData($api_name,$order_info,$second=30){
		$ch = curl_init();
	   	$url = $this->gateway.$api_name;
		//设置超时
		curl_setopt($ch, CURLOPT_TIMEOUT, $second);
	    curl_setopt($ch,CURLOPT_URL, $url);
	    curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
	    curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
		//设置header
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		//要求结果为字符串且输出到屏幕上
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		//post提交方式
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $order_info);
		//运行curl
	    $data = curl_exec($ch);
		//返回结果
		if($data){
			curl_close($ch);
			return $data;
		} else { 
			$error = curl_error($ch);
			curl_close($ch);
			error($error,0);
		}
		return $data;
    }

    /**
    * 应答内容给微信
    * @access public
    * @param string $return_code
    * @param string $return_msg
    * @return string
    */
    public function response($return_code,$return_msg=""){
    	return json_encode(['return_code'=>$return_code,'return_msg'=>$return_msg]);
    }

    private function msg($code,$msg){
    	return ['err_code'=>$code,'err_code_des'=>$msg];
    }
}