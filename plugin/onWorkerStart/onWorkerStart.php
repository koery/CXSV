<?php
/*
Plugin Name: worker线程启动时执行
Plugin URI: http://www.sang.com/
Description: worker线程启动时执行
Version: 1.0.0
Author: Sang
Author URI: http://www.sang.com/
License: GPLv2 or later
Text Domain: Sang
*/
add_action('on_worker_start',array('OnWorkerStart','init'));
class OnWorkerStart{
	public static function init($serv,$worker_id){
		
	}
}