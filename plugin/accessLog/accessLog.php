<?php
/*
Plugin Name: 新请求到来时执行
Plugin URI: http://www.sang.com/
Description: 新请求到来时执行
Version: 1.0.0
Author: Sang
Author URI: http://www.sang.com/
License: GPLv2 or later
Text Domain: Sang
*/
add_action('on_request',['WriteLog','run']);
class WriteLog{
	public static function run(){
		try{
			global $php;
			if(!$php->c('global.write_log')){
				return;
			}
			$mdb = mongodb('access_log_'.APP_NAME);
			$data = [
				'remote_ip' => \Lib\Common::getIp(),
				'time' => date('Y-m-d H:i:s'),
				'request_uri' => cur_url(),
				'post_data' => $_POST,
				'rawContent' => !empty($_FILES) ? $_FILES : val($GLOBALS,'rawContent'),
				'referer' => val($_SERVER,'HTTP_REFERER',''),
				'user_agent' => val($_SERVER,'HTTP_USER_AGENT'),
				'memory_used' => price(memory_get_usage(true)/1024).'K',
				'peak_memory_used' => price(memory_get_peak_usage()/1024).'K',
			];
			$mdb->insert($data);
		}catch(\Exception $e){
			
		}
	}
}