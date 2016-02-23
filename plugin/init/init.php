<?php
/*
Plugin Name: ServerStart
Plugin URI: http://www.sang.com/
Description: 服务器启动时，进行一些初始化.
Version: 1.0.0
Author: Sang
Author URI: http://www.sang.com/
License: GPLv2 or later
Text Domain: Sang
*/
add_action('server_start',array('ServerStart','init'));
class ServerStart{
	public static function init($serv){
		
	}
}