<?php
/*
Plugin Name: 输出数据时执行
Plugin URI: http://www.sang.com/
Description: 输出数据时执行
Version: 1.0.0
Author: Sang
Author URI: http://www.sang.com/
License: GPLv2 or later
Text Domain: Sang
*/
namespace Server\Plugin;
use Lib\App\Config;
add_action('on_response',array('\Server\Plugin\OnResponse','init'));
class OnResponse{
	public static function init(&$data){
		if(strpos($data, '{__STATIC_URL__}')){
			$data = str_replace('{__STATIC_URL__}',Config::get('static_url'),$data);
		}
	}
}