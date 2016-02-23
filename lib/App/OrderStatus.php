<?php
/*
* @Desc 订单状态
* @Auth Sang
* @Date 2015-09-03 09:06:58
**/
namespace Lib\App;
class OrderStatus{
	/*
    * 定义交易状态和退款状态
    */
    // 已删除
    const DELETED = -1;

    // 已取消
    const CANCEL = 0;

    // 待付款
    const PENDING = 1;

    // 已付款，待发货
    const PAID = 2;

    // 已发货
    const SHIPPED = 3;

    // 已收货待评价
    const RGO = 4;

    // 已评价交易完成
    const COMPLITED = 5;

    // 货到付款
    const COD = 6;

    /*
    * 以下为退款状态
    */

    // 退款被拒绝
    const REFUND_DENY = -1;

    // 正常
    const NO_REFUND = 0;

    // 已申请退款
    const REFUND_APPLY = 1;

    // 厂商或代理已同意退款等待买家退货
    const REFUND_ACCEPT = 2;

    // 买家已发货
    const REFUND_SHIPPED = 3;

    // 卖家收到退货同意退款
    const REFUND_COMPLETED = 4;
}