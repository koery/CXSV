<?php
/*
* @Desc 消息模板支持库
* @Auth Sang
* @Date 2015-09-17 11:05:14
**/

/*
*  注意，请搜索 “该字段需要外部计算” ，发送订单类模板消息时，有两个字段需在外部计算
*/
namespace Lib\App;
use Lib\App\Client;
class MsgTpl{
	// 消息类型 会员类|订单类
	private $msg_type;
	// 模板内容
	private $tpl_content;
	// 公众号信息
	private $public;
	// 数据
	private $data;
	// 全局配置
	private $app_config;

	// 会员类模板代换变量
	private $member_replace_arr = [
		//会员ID：
		'{$uid}',
		//昵称：
		'{$nick}',
		//是否代理：
		'{$is_agent}',
		//代理级别：
		'{$agent_level}',
		//是否分享客：
		'{$is_sharer}',
		//会员级别：
		'{$sharer_level}',
		//上级UID：
		'{$parent_uid}',
		//上级昵称：
		'{$parent_nick}',
		//是否已关注：
		'{$is_subscribe}',
		//是否超级合伙人：
		'{$is_super_partner}',
		//省份：
		'{$province}',
		//城市：
		'{$city}',
		//县区：
		'{$street}',
		//公众号名称：
		'{$public_nick}',
		// 下级昵称
		'{$current_nick}',
		// 下级UID
		'{$current_uid}',
	];

	// 订单类模板代换变量
	private $order_replace_arr = [
		//公众号名称 
		'{$public_nick}',
		//订单号 
		'{$order_sn}',
		//订单实付款 
		'{$amount_paid}',
		// 商品数量
		'{$quantity}',
		//商品总额 
		'{$goods_amount}',
		//运费 
		'{$freight_fee}',
		//折扣 
		'{$discount}',
		// 购买者UID
		'{$buyer_uid}',
		//购买者昵称 
		'{$buyer_name}',
		//下单时间 
		'{$create_time}',
		//支付时间 
		'{$pay_time}',
		//支付单号 
		'{$trade_sn}',
		//发货物流
		'{$express_name}',
		//运单号
		'{$invoice}',
		//订单完成时间 
		'{$finish_time}',
		//订单状态 
		'{$status}',
		//订单金额 
		'{$order_amount}',
		//佣金 
		'{$commission}',
		//返佣类型 
		'{$type}',
		// 分佣所得者
		'{$commission_user}'
	];

	public function __construct($msg_type,$tpl_content,$public,$app_config){
		$this->msg_type = $msg_type;
		$this->tpl_content = $tpl_content;
		$this->public = $public;
		$this->app_config = $app_config;
	}

	public function setData($data){
		$this->data = $data;
	}

	/**
	* 发送模板消息
	* @access public
	* @param string $openid
	* @param string $errmsg
	* @return void
	*/
	public function sendTplMsg($openid,&$errmsg=''){
		// 获得模板内容
		$msg_tpl = $this->getMsgTpl();
		if(empty($msg_tpl)){
			$errmsg = 'can not find the msg tpl';
			return false;
		}
		// 替换模板变量
		$this->replaceParams($msg_tpl);
		// 发送
		$type = $msg_tpl['type'];
		if($type=='text'){
			return $this->sendTextMsg($msg_tpl['tpl'],$openid,$errmsg);
		}elseif($type=='wx_tpl'){
			return $this->sendWxTplMsg($msg_tpl['tpl'],$openid,$errmsg);
		}
	}

	/**
	* 取得模板内容
	* @access private
	* @return array
	*/
	private function getMsgTpl(){
		$notice_tpl = $this->tpl_content;
		if(!empty($notice_tpl) && $notice_tpl{0}=='@' && $notice_tpl{strlen($notice_tpl)-1}=='@'){
			$msg_tpl_id = trim($notice_tpl,'@');
			if(empty($msg_tpl_id)){
				return false;
			}
			$msg_tpl_mod = model('MsgTpl');
			$msg_tpl = $msg_tpl_mod->getMsgTplById($msg_tpl_id);
			if(empty($msg_tpl)){
				return false;
			}
			return ['type'=>'wx_tpl','tpl'=>$msg_tpl];
		}else{
			return ['type' => 'text','tpl'=>$notice_tpl];
		}
	}

	/**
	* 替换模板变量
	* @access private
	* @param array $msg_tpl
	* @return void
	*/
	private function replaceParams(&$msg_tpl){
		$type = $msg_tpl['type'];
		switch($this->msg_type){
			case 'member':
				$tpl = $this->replaceMemberParams($type,$msg_tpl['tpl']);
				break;
			case 'order':
				$tpl = $this->replaceOrderParams($type,$msg_tpl['tpl']);
				break;
		}
		return $msg_tpl['tpl'] = $tpl;
	}


	/**
	* 替换会员类模板变量
	* @access private
	* @param array $type
	* @param mix $tpl
	* @return mix
	*/
	private function replaceMemberParams($type,$tpl){
		$RuleGlobal = val($this->app_config,'RuleGlobal');
		$replace = [
			val($this->data,'uid',0),
			val($this->data,'nick','游客'),
			['非代理','代理'][absint(val($this->data,'is_agent',0))],
			val($this->data,'agent_level','无').'级别',
			val($this->data,'is_sharer') ? val($RuleGlobal,'shareName','分享客') : '分享客',
			val($this->data,'sharer_level') ? $this->getLevelById($this->data['sharer_level']) : '',
			val($this->data,'parent_uid'),
			val($this->data,'parent_nick',val($this->public,'nick','未知公众号')),
			val($this->data,'is_subscribe') ? '已关注' : '未关注',
			val($this->data,'is_super_partner') ? '超级合伙人' : '',
			val($this->data,'province'),
			val($this->data,'city'),
			val($this->data,'street'),
			val($this->public,'nick'),
			val($this->data,'current_nick'),
			val($this->data,'current_uid'),
		];
		if($type=='text'){
			return str_replace($this->member_replace_arr,$replace,$tpl);
		}elseif($type == 'wx_tpl'){
			$tpl['params'] = str_replace($this->member_replace_arr,$replace,$tpl['params']);
		}
		return $tpl;
	}

	/**
	* 替换订单类模板变量
	* @access public
	* @param string $type
	* @param mix $type
	* @return mix
	*/
	public function replaceOrderParams($type,$tpl){
		$order_status = [
			OrderStatus::DELETED => '已删除',
			OrderStatus::CANCEL => '已取消',
			OrderStatus::PENDING => '待付款',
			OrderStatus::PAID => '已付款，待发货',
			OrderStatus::SHIPPED => '已发货',
			OrderStatus::RGO => '已收货待评价',
			OrderStatus::COMPLITED => '已评价交易完成',
			OrderStatus::COD => '货到付款',
			OrderStatus::REFUND_DENY => '退款被拒绝',
			OrderStatus::NO_REFUND => '正常',
			OrderStatus::REFUND_APPLY => '已申请退款',
			OrderStatus::REFUND_ACCEPT => '厂商或代理已同意退款等待买家退货',
			OrderStatus::REFUND_SHIPPED => '买家已发货',
			OrderStatus::REFUND_COMPLETED => '卖家收到退货同意退款',
		];

		$replace = [
			val($this->public,'nick'),
			val($this->data,'order_sn'),
			val($this->data,'amount_paid'),
			val($this->data,'quantity','1'),
			val($this->data,'goods_amount'),
			val($this->data,'freight_fee'),
			val($this->data,'discount'),
			val($this->data,'buyer_uid'),
			val($this->data,'buyer_name'),
			datetime(val($this->data,'create_time')),
			datetime(val($this->data,'pay_time')),
			val($this->data,'trade_sn'),
			val($this->data,'express_name'),
			val($this->data,'invoice'),
			datetime(val($this->data,'finish_time')),
			$order_status[val($this->data,'status',0)],
			val($this->data,'order_amount'),
			price(val($this->data,'commission',0)), //该字段需要外部计算
			val($this->data,'type'),  //该字段需要外部计算
			val($this->data,'commission_user'), //该字段需要外部计算
		];

		if($type=='text'){
			$tpl = str_replace($this->order_replace_arr,$replace,$tpl);
		}elseif($type=='wx_tpl'){
			$tpl['params'] = str_replace($this->order_replace_arr, $replace, $tpl['params']);
		}
		return $tpl;
	}

	/**
	* 发送文本消息
	* @access private
	* @param string $tpl,$openid,$errmsg
	* @return bool
	*/
	private function sendTextMsg($tpl,$openid,&$errmsg=''){
		$client = Client::getInstance();
		$ret = $client->msgApi('weixin/send/post',$this->public['public_id'],['public_id'=>$this->public['public_id'],'type'=>'text','touser'=>$openid,'content'=>$tpl]);
		if(val($ret,'errcode')){
			$errmsg = $ret['errmsg'];
			return false;
		}
		return true;
	}

	/**
	* 发送模板消息
	* @access private
	* @param string $tpl,$openid,$errmsg
	* @return void
	*/
	private function sendWxTplMsg($tpl,$openid,&$errmsg=''){
		$client = Client::getInstance();
		$data = ['public_id'=>$this->public['public_id'],'touser'=>$openid,'template_id'=>$tpl['tpl_id'],'url'=>$tpl['click_url'],'topcolor'=>'#000000','data'=>$tpl['params']];
		$ret = $client->msgApi('weixin/templateMsg/post',$this->public['public_id'],$data);
		if(val($ret,'errcode')){
			$errmsg = $ret['errmsg'];
			return false;
		}
		return true;
	}

	/**
	* 获取会员级别
	* @access public
	* @param int $level_id
	* @return string
	*/
	public function getLevelById($level_id){
		$mod = model();
		$mod->setTableName('member_level');
		$level = $mod->where("id={$level_id}")->fields('name')->fetch();
		return val($level,'name','');
	}
}