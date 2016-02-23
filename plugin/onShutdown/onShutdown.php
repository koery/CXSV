<?php
/*
Plugin Name: echo shutdown log
Plugin URI: http://www.sang.com/
Description: 关闭服务器时，输出一条日志
Version: 1.0.0
Author: Sang
Author URI: http://www.sang.com/
License: GPLv2 or later
Text Domain: Sang
*/
add_action('on_shutdown',array('OnShutdown','init'));
class OnShutdown{
	public static function init($serv,$sw){

	}
}