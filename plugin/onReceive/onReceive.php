<?php
/*
Plugin Name: on receive plugin
Plugin URI: http://www.sang.com/
Description: 新连接到来并且接收完数据时执行
Version: 1.0.0
Author: Sang
Author URI: http://www.sang.com/
License: GPLv2 or later
Text Domain: Sang
*/
add_action('on_receive',['onReceive','init']);
class onReceive{
	public static function init($serv,$fd,$from_id,$loger){
		if(defined('DEBUG')) {
            $loger->info("new request:\"".$_SERVER['HTTP_METHOD'].' '.$_SERVER['REQUEST_URI']."\" fd={$fd} from={$from_id}");
        }
	}
}