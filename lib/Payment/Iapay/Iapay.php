<?php
/*
* @Desc 爱贝支付接口
* @Auth Sang
* @Date 2015-12-26 14:36:36
**/

/*
导步通知回调请求格式示例
urlencode前内容：
transdata={"transtype":0,"cporderid":"1","transid":"2","appuserid":"test","appid":"3","waresid":31,"feetype":
4,"money":5.00,"currency":"RMB","result":0,"transtime":"2012-12-12 12:11:10","cpprivate":"test","paytype":1}
&sign=xxxxxx&signtype=RSA
urlencode。若都有配置以接口传入的为准。
*/

namespace Lib\Payment\Iapay;
use Lib\Payment\Payment;
use Lib\Payment\iPayment;
class Iapay extends Payment implements iPayment{
	// 接口地址
	private $gateway = 'http://ipay.iapppay.com:9999';

	private $h5url = "https://web.iapppay.com/h5/exbegpay";

	/**
	* 统一下单
	* @access public
	* @param array $order
	* @return array
	*/
	public function unifiedorder($order){
		try{
			if(!val($order,'cporderid')){
				return $this->msg('FAIL','invalid cporderid');
			}
			if(!val($order,'price')){
				return $this->msg('FAIL','invalid price');
			}
			$order = array_merge($order,[
				'appid' => val($this->config,'appid',''),
				'waresid' => intval(val($this->config,'waresid')),
				'waresname' => val($order,'waresname',''),
				'currency' => 'RMB',
				'appuserid' => $order['cporderid'].'#'.val($this->config,'waresid',1),
				'notifyurl' => $this->getNoticeCallback(),
			]);
			//验证字符长度
			foreach($order as $k => $v) {
				switch ($k) {
					case 'waresname':
						if(strlen($v) > 32) {
							$msg = "waresname length  more than 32";
							return $this->msg('FAIL',$msg);
						}
						break;
					case 'cporderid':
						if(strlen($v) > 64) {
							$msg = "cporderid length  more than 64";
							return $this->msg('FAIL',$msg);
						}
						break;
					case 'price':
						if(strlen($v) > 32) {
							$msg = "waresname length  more than 32"; 
							return $this->msg('FAIL',$msg);
						}
						break;
					case 'appuserid':
						if(strlen($v) > 32) {
							$msg = "appuserid length  more than 32"; 
							return $this->msg('FAIL',$msg);
						}
						break;
					case 'cpprivateinfo':
						if(strlen($v) > 64) {
							$msg = "cpprivateinfo length  more than 64"; 
							return $this->msg('FAIL',$msg);
						}
						break;
					case 'notifyurl':
						if(strlen($v) > 128) {
							$msg = "notifyurl length  more than 128"; 
							return $this->msg('FAIL',$msg);
						}
						break;
				}
			}
			$request_data = $this->composeReq($order);
			// print_r(urldecode($request_data));exit;
			$respData = $this->postData($this->gateway.'/payapi/order', $request_data);
		    //验签数据并且解析返回报文
		    $respData = $this->parseResp($respData);
		    return $respData;
		}catch(\Excetion $e){
			return $this->msg($e->getCode,$e->getMessage());
		}

	}

	/**
	* 创建支付表单
	* @access public
	* @param array $order
	* @return void
	*/
	public function createForm($prepay){
		if(isset($prepay['transid']) && !empty($prepay['transid'])){
			$params = [
				'transid' => $prepay['transid'],
				'redirecturl' => $this->getUrlCallback(),
				'cpurl' => $this->getUrlCallback(),
			];
			$request_data = $this->composeReq($params);
			return '
<!DOCTYPE html>
<html>
<head>
	<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=0" />
	<title>正在发起支付</title>
</head>
<body>
正在发起支付...
<script>
window.location.replace("'.$this->h5url."?{$request_data}".'")
</script>
</body>
</html>
';
		}
	}

	/**
	* 获取签名
	* @access public
	* @param array $params
	* @return void
	*/
	public function getSign(&$params){
		$params = json_encode($params);
		//转换为openssl密钥
		$res = openssl_get_privatekey($this->formatPriKey($this->config['appv_key']));
		//调用openssl内置签名方法，生成签名$sign
		openssl_sign($params, $sign, $res, OPENSSL_ALGO_MD5);

		//释放资源
		openssl_free_key($res);
	    
		//base64编码
		$sign = base64_encode($sign);
		return $sign;
	}

	/**RSA验签
	 * $data待签名数据
	 * $sign需要验签的签名
	 * $pubKey爱贝公钥
	 * 验签用爱贝公钥，摘要算法为MD5
	 * return 验签是否通过 bool值
	 */
	public function verify($params, $sign)  {
	    //转换为openssl格式密钥
		$res = openssl_get_publickey($this->formatPubKey($this->config['platp_key']));
	    //调用openssl内置方法验签，返回bool值
		$result = (bool)openssl_verify($params, base64_decode($sign), $res, OPENSSL_ALGO_MD5);
		
	    //释放资源
		openssl_free_key($res);

	    //返回资源是否成功
		return $result;
	}

	/**
	* 查询订单真实性
	* @access public
	* @param string $order_sn
	* @return array
	*/
	public function queryOrder($order_sn){
		$data = [
			'appid' => $this->config['appid'],
			'cporderid' => $order_sn,
		];
		$request_data = $this->composeReq($data);
		$result = $this->postData($this->gateway.'/payapi/queryresult',$request_data);
		$resp_data = $this->parseResp($result);
		return $resp_data;
	}

	/**格式化公钥
	 * $pubKey PKCS#1格式的公钥串
	 * return pem格式公钥， 可以保存为.pem文件
	 */
	public function formatPubKey($pubKey) {
		$fKey = "-----BEGIN PUBLIC KEY-----\n";
		$len = strlen($pubKey);
		for($i = 0; $i < $len; ) {
		    $fKey = $fKey . substr($pubKey, $i, 64) . "\n";
		    $i += 64;
		}
		$fKey .= "-----END PUBLIC KEY-----";
		return $fKey;
	}


	/**格式化公钥
	 * $priKey PKCS#1格式的私钥串
	 * return pem格式私钥， 可以保存为.pem文件
	 */
	public function formatPriKey($priKey) {
		$fKey = "-----BEGIN RSA PRIVATE KEY-----\n";
		$len = strlen($priKey);
		for($i = 0; $i < $len; ) {
		    $fKey = $fKey . substr($priKey, $i, 64) . "\n";
		    $i += 64;
		}
		$fKey .= "-----END RSA PRIVATE KEY-----";
		return $fKey;
	}

	/**
	 * 组装request报文
	 * $params 需要组装的json报文
	 * $vkey  cp私钥，格式化之前的私钥
	 * return 返回组装后的报文
	 */
	public function composeReq($params){
	    //生成签名
		$sign = $this->getSign($params);

	    //组装请求报文，目前签名方式只支持RSA这一种
		$reqData = "transdata=".urlencode($params)."&sign=".urlencode($sign)."&signtype=RSA";
		return $reqData;
	}

	/**
	 * 解析response报文
	 * $content  收到的response报文
	 * $respJson 返回解析后的json报文
	 * return    解析成功TRUE，失败FALSE
	 */
	public function parseResp($content) {
		$arr = array_map(create_function('$v', 'return explode("=", $v);'), explode('&', $content));
		foreach($arr as $value) {
		        $resp[($value[0])] = urldecode($value[1]);
		}
		$arr = $resp;
		//解析transdata
	    if(isset($arr['transdata'])){
	    	$respData = json_decode($arr["transdata"],true);
	    	//验证签名，失败应答报文没有sign，跳过验签
			if(isset($arr['sign'])){
		        //校验签名
		        return $this->verify($arr["transdata"],$arr['sign']) ? $respData : ['code' => 7,'errmsg' => 'fail sign'];
			}else{
				return $respData;
			}
	    }else if(isset($arr['errmsg'])) {
		    return $arr;
		}
		return ['code' => 9,'errmsg' => 'unknown errors'];
	}

	/**
	 * curl方式发送post报文
	 * $remoteServer 请求地址
	 * $postData post报文内容
	 * $userAgent用户属性
	 * return 返回报文
	 */
	public function postData($remoteServer, $postData) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $remoteServer);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'yedadou wei service');
		$data = curl_exec($ch);
		curl_close($ch);
		return $data;
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
		$notice = $this->getNotice();
	}

	/**
	* 获取通知结果
	* @access public
	* @param string 
	* @return array
	*/
	public function getNotice(){
		return $this->toArray($GLOBALS['rawContent']);
	}

	/**
	* 验证订单--同步
	* @access public
	* @param string $var
	* @return void
	*/
	public function verifyReturn($order){
		return $this->verifyNotice($order);
	}

	/**
	* 获取支付名称
	* @access public
	* @return string
	*/
	public static function getName(){
		return '爱贝云支付';
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
	* 获取配置表单
	* @access public
	* @return array
	*/
	public static function getConfigField(){
        return [
        	'userid' => [
        		'text' => '用户账号',
        		'type' => 'text',
        	],
        	'appid' => [
        		'text'  => '应用编号(APP_ID)',
        		'type'  => 'text',
        	],
        	'appv_key' => [
        		'text' => '应用私钥(APPV_KEY)',
        		'type' => 'text',
        	],
        	'platp_key' => [
        		'text' => '平台公钥(PLATP_KEY)'
        	],
        	'waresid' => [
        		'text'  => '应用中的商品编号',
        		'type'  => 'text',
        	],
        ];
    }

    /**
    * 应答内容给微信
    * @access public
    * @param string $return_code
    * @param string $return_msg
    * @return string
    */
    public function response($return_code,$return_msg){
    	return $this->toXml(['return_code'=>$return_code,'return_msg'=>$return_msg]);
    }

    private function msg($code,$msg){
    	return ['err_code'=>$code,'err_code_des'=>$msg];
    }
}