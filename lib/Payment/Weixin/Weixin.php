<?php
/*
* @Desc 微信支付接口
* @Auth Sang
* @Date 2015-07-07 09:03:39
**/

/* 微信通知返回结果
* appid  微信分配的公众账号ID
* mch_id  微信支付分配的商户号
* device_info  微信支付分配的终端设备号，
* nonce_str  随机字符串，不长于32位
* sign  签名，详见签名算法
* result_code  SUCCESS/FAIL
* err_code  详细参见第6节错误列表
* err_code_des  错误返回的信息描述
* openid  用户在商户appid下的唯一标识
* is_subscribe  用户是否关注公众账号，Y-关注，N-未关注，仅在公众账号类型支付有效
* trade_type  JSAPI、NATIVE、APP
* bank_type  银行类型，采用字符串类型的银行标识，银行类型见附表
* total_fee  订单总金额，单位为分
* fee_type  货币类型，符合ISO 4217标准的三位字母代码，默认人民币：CNY，其他值列表详见货币类型
* cash_fee  现金支付金额订单现金支付金额，详见支付金额
* cash_fee_type  货币类型，符合ISO 4217标准的三位字母代码，默认人民币：CNY，其他值列表详见货币类型
* coupon_fee  代金券或立减优惠金额<=订单总金额，订单总金额-代金券或立减优惠金额=现金支付金额，详见支付金额
* coupon_count  代金券或立减优惠使用数量
* coupon_batch_id_$n  代金券或立减优惠批次ID ,$n为下标，从0开始编号
* coupon_id_$n  代金券或立减优惠ID, $n为下标，从0开始编号
* coupon_fee_$n  单个代金券或立减优惠支付金额, $n为下标，从0开始编号
* transaction_id  微信支付订单号
* out_trade_no  商户系统的订单号，与请求一致。
* attach  商家数据包，原样返回
* time_end  支付完成时间，格式为yyyyMMddHHmmss，如2009年12月25日9点10分10秒表示为20091225091010。其他详见时间规则
*/
namespace Lib\Payment\Weixin;
use Lib\Payment\Payment;
use Lib\Payment\iPayment;
use Lib\Common;
class Weixin extends Payment implements iPayment{
	// 接口地址
	private $gateway = 'https://api.mch.weixin.qq.com/';

	/**
	* 统一下单
	* @access public
	* @param array $order_info 订单信息，必须包含以下元素：
			* 'body' => $order_info['title'],
			* 'out_trade_no' => $order_info['order_sn'],
			* 'total_fee' => $order_info['order_amount']*
			* 'product_id' => request('product_id','','tr
			* 'openid' => request('openid','',''),
			* 'time_start' => request('time_start',''),
			* 'time_expire' => request('time_expire',''),
			* 'goods_tag' => request('goods_tag',''),
			* 'attach' => $order_info['ext'],
	* @param string $trade_type 接口类型 jsapi为JS接口
	* @param string $device_info
	* @return array
	*/
	public function unifiedorder($order_info,$trade_type='jsapi',$device_info='WEB'){
		$trade_type = strtolower($trade_type);
		// if(!val($order_info,'nonce_str')){
		// 	return $this->response('FAIL','nonce_str cannot be empty');
		// }
		if(!val($order_info,'body')){
			return $this->msg('FAIL','body cannot be empty');
		}
		if(!val($order_info,'out_trade_no')){
			return $this->msg('FAIL','out_trade_no cannot be empty');
		}
		if(!val($order_info,'total_fee')){
			return $this->msg('FAIL','total_fee cannot be empty');
		}
		if(empty($trade_type) || !in_array($trade_type, ['jsapi','navtive','app'])){
			return $this->msg('FAIL','invalid trade_type');
		}
		if($trade_type=='jsapi' && !val($order_info,'openid')){
			return $this->msg('FAIL','openid cannot be empty');
		}
		$notify_url = $this->getNoticeCallback();
		if(empty($notify_url) || empty($notify_url)){
			return $this->msg('FAIL','invalid notify_url');
		}
		$client_ip = val($_SERVER,'REMOTE_ADDR');
		!$client_ip && $client_ip = Common::getIp();
		if(strpos($client_ip, ',')!==false){
			$client_ip = explode(',',$client_ip);
			$client_ip = val($client_ip,0);
		}
		$order_info = array_merge($order_info,[
			'appid' => $this->config['appid'],
			'mch_id' => $this->config['mch_id'],
			'fee_type' => 'CNY',
			'spbill_create_ip' => $client_ip,
			'notify_url' => $notify_url,
			'nonce_str' => Common::getRandString(32,true),
			'trade_type' => strtoupper($trade_type),
		]);
		$sign = $this->getSign($order_info);
		$order_info['sign'] = $sign;
		return $this->toArray($this->postData('pay/unifiedorder',$order_info));
	}

	/**
	* 创建JSAPI支付表单
	* @access public
	* @param string $order_info
	* @return string
	*/
	public function createForm($prepay){
		if(!isset($prepay['prepay_id'])){
			return '{}';
		}
		$params = [
			'appId' => $this->config['appid'],
			'timeStamp' => (string)time(),
			'nonceStr' => Common::getRandString(32,true),
			'package' => 'prepay_id='.$prepay['prepay_id'],
			'signType' => 'MD5',
		];
		$sign = $this->getSign($params);
		$params['paySign'] = $sign;
		return json_encode($params);
	}

	/**
	* 发送红包
	* @access public
	* @param int $type 红包类型，1普通红包 2裂变红包
	* @param string $re_openid 接收的用户的openid
	* @param array $data 红包数据
	* @return mix
	*/
	public function sendRedpack($type,$data){
		try{
			if(empty($type) || !in_array($type, [1,2])){
				return $this->msg('ERROR','invalid redpack type.please input 1 or 2');
			}
			if(empty($data)){
				return $this->msg('ERROR','invalid redpack data');
			}
			$data = [2=>[
				'send_name' => val($data,'send_name'),
				're_openid' => val($data,'re_openid'),
				'mch_billno' => val($data,'mch_billno'),
				'total_amount' => val($data,'total_amount'),
				'total_num' => val($data,'total_num'),
				'amt_type' => val($data,'amt_type'),
				'amt_list' => val($data,'amt_list'),
				'wishing' => val($data,'wishing'),
				'act_name' => val($data,'act_name'),
				'remark' => val($data,'remark'),
			],1=>[
				'mch_billno' => val($data,'mch_billno'),
				'nick_name' => val($data,'nick_name'),
				'send_name' => val($data,'send_name'),
				're_openid' => val($data,'re_openid'),
				'total_amount' => val($data,'total_amount'),
				'wishing' => val($data,'wishing'),
				'act_name' => val($data,'act_name'),
				'remark' => val($data,'remark'),
				'logo_imgurl' => val($data,'logo_imgurl'),
			]][$type];
			$check_err_msg = '';
			if(!$this->checkRedPackData($data,$type,$check_err_msg)){
				return $this->msg('ERROR',$check_err_msg);
			}
			$data = array_merge($data,[
				'wxappid' => $this->config['appid'],
				'nonce_str'=>Common::getRandString(32,true),
				'mch_id'=>$this->config['mch_id'],
				// 'sub_mch_id'=>,
				'client_ip'=>get_internet_ip(),
			]);
			$sign = $this->getSign($data);
			$data['sign'] = $sign;
			$api_name = [1=>'mmpaymkttransfers/sendredpack',2=>'mmpaymkttransfers/sendgroupredpack'][$type];
			$cert = $this->getCertFile($this->config);
			$ret = $this->postData($api_name,$data,$cert);
			return $this->toArray($ret);
		}catch(\Exception $e){
			return $this->msg('ERROR',$e->getMessage());
		}finally{
			$this->cleanCertFile($cert);
		}
	}

	/**
	* 查询红包发放情况
	* @access public
	* @param string $mch_billno 商户订单ID
	* @return array
	*/
	public function queryRedPack($mch_billno){
		try{
			if(empty($mch_billno)){
				return $this->msg('ERROR','invalid mch_billno');
			}
			$data = [
				'appid' => $this->config['appid'],
				'nonce_str'=>Common::getRandString(32,true),
				'mch_id'=>$this->config['mch_id'],
				'mch_billno' => $mch_billno,
				'bill_type'=>'MCHT',
			];
			$sign = $this->getSign($data);
			$data['sign'] = $sign;
			$cert = $this->getCertFile($this->config);
			$ret = $this->postData('mmpaymkttransfers/gethbinfo',$data,$cert);
			return $this->toArray($ret);
		}catch(\Exception $e){
			return $this->msg('ERROR',$e->getMessage());
		}finally{
			$this->cleanCertFile($cert);
		}
	}

	/**
	* 检查红包数据是否正确
	* @access public
	* @param array $data
	* @param string $type
	* @param string &$check_err_msg
	* @return void
	*/
	public function checkRedPackData(&$data,$type,&$check_err_msg){
		// 必须参数
		if(!val($data,'send_name')){
			$check_err_msg = 'invalid send_name';
			return false;
		}
		if(!val($data,'total_amount') || !absint($data['total_amount'])){
			$check_err_msg = 'invalid total_amount';
			return false;
		}
		if(!val($data,'wishing')){
			$check_err_msg = 'invalid wishing';
			return false;
		}
		if(!val($data,'act_name')){
			$check_err_msg = 'invalid act_name';
			return false;
		}
		if(!val($data,'remark')){
			$check_err_msg = 'invalid remark';
			return false;
		}
		if(!val($data,'re_openid')){
			$check_err_msg = 'invalid re_openid';
			return false;
		}
		if(check_length($data['mch_billno'],10,28)==false){
			$check_err_msg = 'mch_billno length must be less than or equal to 28';
			return false;
		}
		switch($type){
			case 1:
			if(!val($data,'nick_name')){
				$check_err_msg = 'invalid nick_name';
				return false;
			}
			if($data['total_amount']<100 || $data['total_amount']>20000){
				$check_err_msg = 'total_amount must be greater than 100, less than 20000';
				return false;
			}
			if(val($data,'share_url') && !is_url($data['share_url'])){
				$check_err_msg = 'invalid share_url';
				return false;
			}
			if(val($data,'logo_imgurl') && !is_url($data['logo_imgurl'])){
				$check_err_msg = 'invalid logo_imgurl';
				return false;
			}
			if(val($data,'share_imgurl') && !is_url($data['share_imgurl'])){
				$check_err_msg = 'invalid share_imgurl';
				return false;
			}
			$data['total_num'] = 1;
			if(isset($data['amt_type'])) unset($data['amt_type']);
			if(isset($data['amt_list'])) unset($data['amt_list']);
			$data['min_value'] = $data['max_value'] = $data['total_amount'];
			break;

			/*
			* 裂变红包
			*/
			case 2:
			// 检查红包总额
			if($data['total_amount']<100 || $data['total_amount']>100000){
				$check_err_msg = 'total_amount must be greater than 100, less than 100000';
				return false;
			}
			// 检查发放人数
			if(!val($data,'total_num') || !absint($data['total_num'])){
				$check_err_msg = 'invalid total_num';
				return false;
			}
			// 检查每个人平均分得的金额是否在1-200之间
			$divide = $data['total_amount']/100/$data['total_num'];
			if($divide<1 || $divide>200){
				$check_err_msg = 'Each a redpack the average amount must be between 1.00 to 200.00 yuan';
				return false;
			}
			// 检查金额生成方式
			if(!val($data,'amt_type') && !val($data,'amt_list')){
				$check_err_msg = 'amt_type and amt_list not all is empty';
				return false;
			}

			if(val($data,'amt_type')){
				$data['amt_type'] = strtoupper($data['amt_type']);
				if(!in_array($data['amt_type'],['ALL_RAND'])){
					$check_err_msg = 'invalid amt_type';
					return false;
				}
			}
			if(val($data,'amt_list')){
				$amt_list_arr = explode('|',$data['amt_list']);
				if(count($amt_list_arr)!=$data['total_num']){
					$check_err_msg = 'the amt_list count must be equal to total_num';
					return false;
				}
				foreach($amt_list_arr as $item){
					if(absint($item)<0){
						$check_err_msg = 'invalid amt_list. Each a value must be all positive integer';
						return false;
					}
				}
				$sum = array_sum($amt_list_arr);
				if($sum > $data['total_amount']){
					$check_err_msg = 'the amt_list sum is not greater than total_amount';
					return false;
				}
			}
			break;
		}
		return true;
	}

	/**
	* 企业付款接口
	* @access public
	* @param array $data
	* @return array
	*/
	public function companyTransfer($data){
		try{
			if(empty($data)){
				return $this->msg('ERROR','invalid transfer data');
			}
			$check_err_msg = '';
			if($this->checkTransferData($data,$check_err_msg)===false){
				return $this->msg('ERROR',$check_err_msg);
			}
			$data = array_merge($data,[
				'mch_appid' => $this->config['appid'],
				'mchid' => $this->config['mch_id'],
				'nonce_str' => Common::getRandString(32,true),
				'spbill_create_ip' => get_internet_ip(),
			]);
			$data['sign'] = $this->getSign($data);
			$cert = $this->getCertFile($this->config);
			$ret = $this->postData('mmpaymkttransfers/promotion/transfers',$data,$cert);
			return $this->toArray($ret);
		}catch(\Exception $e){
			return $this->msg('ERROR',$e->getMessage());
		}finally{
			$this->cleanCertFile($cert);
		}
	}

	/**
	* 检查企业付款数据正确性
	* @access public
	* @param array $data
	* @return bool
	*/
	public function checkTransferData(&$data,&$check_err_msg){
		if(!val($data,'partner_trade_no')){
			$check_err_msg = 'invalid partner_trade_no';
			return false;
		}
		if(!val($data,'openid')){
			$check_err_msg = 'invalid openid';
			return false;
		}
		if(!val($data,'check_name')){
			$data['check_name'] = 'NO_CHECK';
		}
		if(in_array(val($data,'check_name'),['FORCE_CHECK','OPTION_CHECK']) && !val($data,'re_user_name')){
			$check_err_msg = 'invalid re_user_name';
			return false;
		}
		if(!val($data,'amount') || $data['amount']<100){
			$check_err_msg = 'amount must be greater than 100';
			return false;
		}
		if(!val($data,'desc')){
			$check_err_msg = 'invalid desc';
			return false;
		}
		return true;
	}

	/**
	* 查询企业付款结果
	* @access public
	* @param int $biz_id
	* @return array
	*/
	public function queryTransfer($partner_trade_no){
		try{
			if(empty($partner_trade_no)){
				return $this->msg('ERROR','invalid partner_trade_no');
			}
			$data = [
				'nonce_str' => Common::getRandString(32,true),
				'partner_trade_no' => $partner_trade_no,
				'mch_id' => $this->config['mch_id'],
				'appid' => $this->config['appid'],
			];
			$data['sign'] = $this->getSign($data);
			$cert = $this->getCertFile($this->config);
			$ret = $this->postData('mmpaymkttransfers/gettransferinfo',$data,$cert);
			return $this->toArray($ret);
		}catch(\Exception $e){
			return $this->msg('ERROR',$e->getMessage());
		}finally{
			$this->cleanCertFile($cert);
		}
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
	* 将数据转换成XML
	* @access public
	* @param array $data
	* @return string
	*/
	public function toXml($data){
		$xml = "<xml>\n%s\n</xml>\n";
		$str = [];
		foreach($data as $key=>$value){
			$str[] = "<{$key}><![CDATA[{$value}]]></{$key}>";
		}
		return sprintf($xml,join("\n",$str));
	}

	/**
	* 将结果转换成数组
	* @access public
	* @param array $xml
	* @return array
	*/
	public function toArray($xml){
		return (array)simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NOCDATA);
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
		return '微信支付';
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
	* @param string $transaction_id 微信返回的交易号或商户订单号
	* @param bool $is_order_sn 是不是商户订单号，true为根据商户订单号查询, false为查询微信返回的交易号
	* @return array 订单信息
	*/
	public function queryOrder($transaction_id,$is_order_sn=false){
		$data = [
			'appid' => $this->config['appid'],
			'mch_id' => $this->config['mch_id'],
			'nonce_str' => Common::getRandString(32,true),
		];
		if($is_order_sn==true){
			$data['out_trade_no'] = $transaction_id;
		}else{
			$data['transaction_id'] = $transaction_id;
		}
		$sign = $this->getSign($data);
		$data['sign'] = $sign;
		return $this->toArray($this->postData('pay/orderquery',$data));
	}

	/**
	* 获取配置表单
	* @access public
	* @return array
	*/
	public static function getConfigField(){
        return [
        	'appid' => [
        		'text'  => '公众账号ID(AppId)',
        		'type'  => 'text',
        	],
        	'appsecret' => [
        		'text'  => '应用密钥(AppSecret)',
        		'type'  => 'text',
        	],
            'mch_id'    => array(        //账号
                'text'  => '商户号(mch_id)',
                'type'  => 'text',
            ),
            'key'       => array(        //密钥
                'text'  => 'API密钥(key)',
                'type'  => 'text',
            ),
            'apiclient_cert'   => array(        //合作者身份ID
                'text'  => '证书pem格式(apiclient_cert.pem)',
                'type'  => 'file',
            ),
            'apiclient_key' => array(         //服务类型
                'text'      => '证书密钥pem格式(apiclient_key.pem)',
                'type'      => 'file',
            ),
        ];
    }

    /**
    * 提交请求
    * @access private
    * @param array $order_info
    * @return array
    */
    private function postData($api_name,$order_info,$useCert=false,$second=30){
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
		if(!empty($useCert)){
			if(!isset($useCert['apiclient_cert']) || !is_file($useCert['apiclient_cert'])){
				error('invalid apiclient_cert file',800111);
			}
			if(!isset($useCert['apiclient_key']) || !is_file($useCert['apiclient_key'])){
				error('invalid apiclient_key file',800112);
			}
			
			//设置证书
			//使用证书：cert 与 key 分别属于两个.pem文件
			curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLCERT, $useCert['apiclient_cert']);
			curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLKEY, $useCert['apiclient_key']);
			curl_setopt($ch,CURLOPT_CAINFO, __DIR__.'/rootca.pem');
		}
		//post提交方式
		$xml = $this->toXml($order_info);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
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
    public function response($return_code,$return_msg){
    	return $this->toXml(['return_code'=>$return_code,'return_msg'=>$return_msg]);
    }

    private function msg($code,$msg){
    	return ['err_code'=>$code,'err_code_des'=>$msg];
    }

    /**
    * 将存在数据库里的密钥写入到文件里，用完删掉
    * @access private
    * @param array $config
    * @return array [cert,key]
    */
    private function getCertFile(&$config){
    	// ['apiclient_cert'=>$api_cert,'apiclient_key'=>$api_key]
    	$api_cert = val($config,'apiclient_cert');
    	$api_key = val($config,'apiclient_key');
    	if(empty($api_cert) || empty($api_key)){
    		return false;
    	}
    	$api_cert = \Lib\Encrypt::decrypt($api_cert);
    	$api_key = \Lib\Encrypt::decrypt($api_key);
    	if(empty($api_cert) || empty($api_key)){
    		return false;
    	}
    	$cert_tmp_file = tempnam(TEMP_PATH, 'wxpay_cert_');
    	file_put_contents($cert_tmp_file, $api_cert);
    	$key_tmp_file = tempnam(TEMP_PATH, 'wxpay_key_');
    	file_put_contents($key_tmp_file, $api_key);
    	return ['apiclient_cert'=>$cert_tmp_file,'apiclient_key'=>$key_tmp_file];
    }

    /**
    * 清理临时生成的证书文件
    * @access private
    * @param array $cert
    * @return void
    */
    private function cleanCertFile($cert){
    	if(!empty($cert)){
    		foreach($cert as $key=>$file){
    			if(is_file($file)){
    				unlink($file);
    			}
    		}
    	}
    }
}