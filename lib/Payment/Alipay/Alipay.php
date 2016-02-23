<?php
/*
Auth:Sang
Desc:支付宝支付接口
Date:2014-11-01
*/
namespace Lib\Payment\Alipay;
use Lib\Payment\Payment;
use Lib\Payment\iPayment;
class Alipay extends Payment implements iPayment{
	//支付网关
	private $gateway = "https://www.alipay.com/cooperate/gateway.do";
    //查询通知是否有效的URL
    private $query_url = "http://notify.alipay.com/trade/notify_query.do";

	/**
    * 创建支付表单
    * @param array order_info 订单信息,必须包含：
        * string title 订单名称
    	* string order_sn 订单编号
    	* float order_amount 订单总价
    * @return string 一个第三方接口的支付表单
	*/
	public function createForm($order_info){
		$params = array(
            /* 基本信息 */
            'service'           => $this->config['alipay_service'],
            'partner'           => $this->config['alipay_partner'],
            '_input_charset'    => 'utf-8',
            'notify_url'        => $this->getNoticeCallback(),
            'return_url'        => $this->getUrlCallback(),
            'error_notify_url'  => site_url()."/payError",
            /* 业务参数 */
            'subject'           => $order_info['title'],
            //订单ID由不属签名验证的一部分，所以有可能被客户自行修改，所以在接收网关通知时要验证指定的订单ID的外部交易号是否与网关传过来的一致
            'out_trade_no'      => $order_info['order_sn'],
            'price'             => $order_info['order_amount'],   //应付总价
            'quantity'          => 1,
            'payment_type'      => 1,

            /* 物流参数 */
            'logistics_type'    => 'EXPRESS',
            'logistics_fee'     => 0,
            'logistics_payment' => 'BUYER_PAY_AFTER_RECEIVE',

            /* 买卖双方信息 */
            'seller_email'      => $this->config['alipay_account'],
            // 扩展参数，用来标识支会付方式
            'extra_common_param' => $this->getCode(),
        );
		
		//验证回调地址
		if(empty($params['notify_url']) || empty($params['return_url'])){
			throw new \Exception("没有设置回调地址",511);
		}

        $params['sign']         =   $this->getSign($params);
        $params['sign_type']    =   'MD5';

        $form = "<form action=\"{$this->gateway}\" method=\"get\" style=\"display:none\">\n";
        foreach($params as $key=>$val){
        	$form .= "<input type=\"hidden\" name=\"{$key}\" value=\"{$val}\"/>\n";
        }
        $form .= "</form>";
        return $form;
	}

	/*签名*/
	private function getSign($params){
        /* 去除不参与签名的数据 */
        unset($params['sign'], $params['sign_type'], $params['method'], $params['order_id'],$params['m'],$params['a']);
        /* 排序 */
        ksort($params);
        reset($params);
        $sign  = '';
        foreach ($params AS $key => $value)
        {
            $sign  .= "{$key}={$value}&";
        }
        return md5(substr($sign, 0, -1) . $this->config['alipay_key']);
	}

    /**
    * 验证支付平台服务器异步通知的订单信息是否合法
    * @access public
    * @param array $order 本地订单信息
    * @return bool
    */
    public function verifyNotice($order){
        return $this->verify($order,true);
    }

    /**
    * 验证支付平台服务器同步通知的订单信息是否合法
    * @access public
    * @param array $order 本地订单信息
    * @return bool
    */
    public function verifyReturn($order){
        return $this->verify($order);
    }


    /*验证通知结果，strict:true严格验证,包括验证通知ID
    order_info必须包含：
    订单编号 order_sn;
    订单总价 order_amount;
    */
	private function verify($order_info, $strict = false){
        if (empty($order_info)){
            $this->error='不存在该订单';

            return false;
        }

        /* 初始化所需数据 */
        $notify =   $_POST ? $_POST : $_GET;
        /*----------本地验证开始----------*/
        /* 验证与本地信息是否匹配 */
        /* 这里不只是付款通知，有可能是发货通知，确认收货通知 */
        if ($order_info['order_sn'] != $notify['out_trade_no']){
            /* 通知中的订单与欲改变的订单不一致 */
            $this->error='订单不一致';

            return false;
        }
        if ($order_info['order_amount'] != $notify['total_fee']){
            /* 支付的金额与实际金额不一致 */
            $this->error='支付的金额与实际金额不一致';

            return false;
        }

        /* 验证来路是否可信 */
        if ($strict){
            /* 严格验证 */
            $verify_result = $this->queryNotify($notify['notify_id']);
            if(!$verify_result)
            {
                /* 来路不可信 */
                $this->error='非法数据来源';
                return false;
            }
        }

        /* 验证通知是否可信 */
        $sign_result = $this->verifySign($notify);
        
        if (!$sign_result){
            /* 若本地签名与网关签名不一致，说明签名不可信 */
            $this->error='签名不一致';
            return false;
        }

        /*----------通知验证结束----------*/

        //至此，说明通知是可信的，订单也是对应的，可信的

        /* 按通知结果返回相应的结果 */
        switch ($notify['trade_status']){
            case 'WAIT_SELLER_SEND_GOODS':      //买家已付款，等待卖家发货
            case 'TRADE_SUCCESS':
            case 'TRADE_FINISHED':              //交易结束
            $order_status = self::PAY_FINISHED;
            break;

            case 'WAIT_BUYER_CONFIRM_GOODS':    //卖家已发货，等待买家确认
            $order_status = self::PAY_SHIPPED;
            break;

            case 'TRADE_CLOSED':               //交易关闭
                $order_status = self::PAY_CANCELED;
                break;
            default:
                $this->error='未知的交易状态';
                return false;
                break;
        }
        if(isset($notify['refund_status'])){
            switch ($notify['refund_status']){
                case 'REFUND_SUCCESS':              //退款成功，取消订单
                    $order_status = self::PAY_REFUND;
                break;
            }
        }

        return $order_status;
    }

    /**
     *    查询通知是否有效
     *    @author    Sang
     *    @param     string $notify_id
     *    @return    string
     */
    public function queryNotify($notify_id){
        if(empty($notify_id)){
            return false;
        }
        $query_url = $this->query_url."?partner={$this->config['alipay_partner']}&notify_id={$notify_id}";
        try{
            return curl($query_url) === 'true';
        }catch(exception $e){
            return false;
        }
    }

    private function verifySign($notify){

        $local_sign = $this->getSign($notify);
        return ($local_sign == $notify['sign']);
    }

    /**
    * 取得该支付接口的所有配置项     
    * @access public
    * @return array
    */
    public static function getConfigField(){
        return [
            'alipay_account'   => array(        //账号
                'text'  => '支付宝账号',
                'desc'  => '输入您在支付宝的账号',
                'type'  => 'text',
            ),
            'alipay_key'       => array(        //密钥
                'text'  => '交易安全校验码（key）',
                'desc'  => '输入您在支付宝平台申请的密钥',
                'type'  => 'text',
            ),
            'alipay_partner'   => array(        //合作者身份ID
                'text'  => '合作者身份（partner ID）',
                'type'  => 'text',
            ),
            'alipay_service'  => array(         //服务类型
                'text'      => '接口类型',
                'desc'  =>'1.5%费率用户请选“担保交易接口”',
                'type'      => 'select',
                'items'     => array(
                    'trade_create_by_buyer'   => '标准双接口',
                    'create_partner_trade_by_buyer'   => '担保交易接口',
                    'create_direct_pay_by_user'   => '即时到帐交易接口',
                ),
            ),
            'is_direct'=>array(
                'text'=>'是否启用网银直连',
                'desc'=>'如果开启，请先确定是否申请了直连接口',
                'type'=>'select',
                'items'=>array(
                    0=>'否',
                    1=>'是'
                ),
            ),
        ];
    }

    public static function getName(){
        return '支付宝';
    }
}