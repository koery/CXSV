<?php
/*
Plugin Name: echo connect log
Plugin URI: http://www.sang.com/
Description: 有一个链接进入时，输出一条日志
Version: 1.0.0
Author: Sang
Author URI: http://www.sang.com/
License: GPLv2 or later
Text Domain: Sang
*/

add_action('on_connect',array('OnConnect','init'));
class OnConnect{
	public static function init($serv,$sw, $client_id, $from_id){
		//调试时开启
        if(defined('DEBUG')){
            $serv->log("client[#$client_id@$from_id] connect");
        }
	}
}