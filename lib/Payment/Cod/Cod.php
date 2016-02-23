<?php
/*
* @Desc 货到付款支付方式
* @Auth Sang
* @Date 2015-08-11 13:51:54
**/
namespace Lib\Payment\Cod;
use Lib\Payment\Payment;
use Lib\Payment\iPayment;
class Cod extends Payment implements iPayment{
	public function createForm($order_info){
		// 推送通知
		// 将数据加密
		$data = urlencode(\Lib\Encrypt::encrypt(json_encode([
			'out_trade_no' => $order_info['order_sn'],
			'order_amount' => $order_info['order_amount'],
		],JSON_UNESCAPED_UNICODE)));
		// 重试5次
		$i=5;
		while($i-->0){
			try{
				$ret = curl($this->getNoticeCallback(),'post',$data);
				if($ret=='success'){
					break;
				}elseif($i==0){
					m_log('cod notice fail! order_sn : '.$order_info['order_sn']);
				}
			}catch(\Exception $e){
				if($i==0){
					m_log('cod notice fail! order_sn : '.$order_info['order_sn']);
				}
			}

		}
		redirect($this->getUrlCallback());
	}
	public function verifyNotice($order){
		return true;
	}
	public function verifyReturn($order){
		return true;
	}
	public static function getName(){
		return '货到付款';
	}
	public static function getConfigField(){
		return [
			'pay_method' => [
				'text' => '付款方式',
				'desc'  => '支持的支付方式',
				'type' => 'checkbox',
				'items' => [
					'cash' => '现金',
					'pos' => '刷卡',
					'all' => '两者都可',
				]
			]
		];
	}
}