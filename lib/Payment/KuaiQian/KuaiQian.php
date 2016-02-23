<?php
/*
Auth:Sang
Desc:快钱支付接口
Date:2014-11-01
*/
namespace Lib\Payment\KuaiQian;
use Lib\Payment\Payment;
use Lib\Payment\iPayment;
class KuaiQian extends Payment implements iPayment{
	//支付网关
	private $gateway = "https://www.99bill.com/gateway/recvMerchantInfoAction.htm";

	/*创建支付表单*/
	public function createForm($order_info){
		$params = [
			'inputCharset' => '1',
			'pageUrl' => $this->getUrlCallback(),
			'bgUrl' => $this->getNoticeCallback(),
			'version' => 'v2.0',
			'language' => '1',
			'signType' => '4',
			'merchantAcctId' => $this->config['merchantAcctId'],
			'payerName' => '',
			'payerContactType' => '',
			'payerContact' => '',
			'orderId' => $order_info['order_sn'],
			'orderAmount' => $order_info['order_amount']*100,
			'orderTime' => date("YmdHis"),
			'productName' => $order_info['title'],
			'productNum' => '1',
			'productId' => '',
			'productDesc' => '',
			'ext1' => $this->getCode(),
			'ext2' => '',
			'payType' => '00',
			'bankId' => '',
			'redoFlag' => '1',
			'pid' => '',
		];

		//验证回调地址
		if(empty($params['bgUrl'])){
			throw new \Exception("没有设置回调地址",511);
		}

		$sign = $this->getSign($params);
		$params['signMsg'] = $sign;
		$form = "<form action=\"{$this->gateway}\" method=\"get\" style=\"display:none\">\n";
        foreach($params as $key=>$val){
        	if($val=='') continue;
        	$form .= "<input type=\"hidden\" name=\"{$key}\" value=\"{$val}\"/>\n";
        }
        $form .= "</form>";
        return $form;
	}

	private function getSign($params){
		$body = '';
		foreach($params as $key=>$val){
			if($val=='') continue;
			$body .= "{$key}={$val}&";
		}
		$body =trim($body,'&');
		$fp = fopen(dirname(__FILE__)."/99bill-rsa.pem", "r");
		$priv_key = fread($fp, 123456);
		fclose($fp);
		$pkeyid = openssl_get_privatekey($priv_key);
		openssl_sign($body, $signMsg, $pkeyid,OPENSSL_ALGO_SHA1);
		openssl_free_key($pkeyid);

		return base64_encode($signMsg);
	}

	public function verifyNotice($order){
		$params = [
			'merchantAcctId' => $_REQUEST['merchantAcctId'],
			'version' => $_REQUEST['version'],
			'language' => $_REQUEST['language'],
			'signType' => $_REQUEST['signType'],
			'payType' => $_REQUEST['payType'],
			'bankId' => $_REQUEST['bankId'],
			'orderId' => $_REQUEST['orderId'],
			'orderTime' => $_REQUEST['orderTime'],
			'orderAmount' => $_REQUEST['orderAmount'],
			'dealId' => $_REQUEST['dealId'],
			'bankDealId' => $_REQUEST['bankDealId'],
			'dealTime' => $_REQUEST['dealTime'],
			'payAmount' => $_REQUEST['payAmount'],
			'fee' => $_REQUEST['fee'],
			'ext1' => $_REQUEST['ext1'],
			'ext2' => $_REQUEST['ext2'],
			'payResult' => $_REQUEST['payResult'],
			'errCode' => $_REQUEST['errCode'],
		];

		if($order['order_sn']!=$params['orderId']){
			$this->error = '订单号不一致';
			return false;
		}
		if(($order['order_amount'])*100!=$params['orderAmount']){
			$this->error = '支付的金额与实际金额不一致';
			return false;
		}


		$body = '';
		foreach($params as $key=>$val){
			if($val=='') continue;
			$body .= "{$key}=$val&";
		}
		$body =trim($body,'&');
		$MAC=base64_decode($_REQUEST['signMsg']);
		$fp = fopen(dirname(__FILE__)."/99bill.cert.rsa.cer", "r"); 
		$cert = fread($fp, 8192); 
		fclose($fp); 
		$pubkeyid = openssl_get_publickey($cert); 
		$ret = openssl_verify($body, $MAC, $pubkeyid);
		if(!$ret){
			$this->error = '验证签名失败';
			return false;
		}
		if(intval($params['payResult'])!=10){
			$this->error = '订单支付失败';
			return false;
		}
		return true;
	}
	public function verifyReturn($order){
		return $this->verifyNotice($order);
	}
	public static function getName(){
		return '快钱支付';
	}
	public function getCode(){
		return $this->code;
	}

	public static function getConfigField(){
        return [
        	//自动付款
            'merchantAcctId'   => array(        //账号
                'text'  => '快钱账号(merchantAcctId)',
                'desc'  => '',
                'type'  => 'text',
            ),
            'zdfk_key'       => array(        //密钥
                'text'  => '自动付款接口密钥(zdfk_key)',
                'desc'  => '',
                'type'  => 'text',
            ),
            'dpl_key'   => array(        //合作者身份ID
                'text'  => '大批量资金结算密钥(dpl_key)',
                'type'  => 'text',
            ),
            'gateway_key'  => array(         //服务类型
                'text'      => '人民币网关密钥(gateway_key)',
                'type'      => 'text',
            ),
            'query_key'=>array(
                'text'=>'网关订单查询接口密钥(query_key)',
                'type'=>'text',
            ),
        ];
    }
}