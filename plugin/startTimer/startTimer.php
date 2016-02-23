<?php
/*
Plugin Name: timer
Plugin URI: http://www.sang.com/
Description: 定时器
Version: 1.0.0
Author: Sang
Author URI: http://www.sang.com/
License: GPLv2 or later
Text Domain: Sang
*/

/**
* @Auth Sang
* @Desc 使用方式：在应用根目录建立Cron目录，在目录里面写定时PHP脚本，脚本名称首字大写，命名空间为：Cron
*       内容如：
*########################################################################
*		<?php 
*			namespace Cron;
*			class Test{
*				public $time='* * * * * *';
*				private $start_time;
*				public function __construct($start_time){
*					$this->start_time = $start_time;
*				}
*				public function run(){
*					#do somthing
*				}
*			}
*		?>
*########################################################################
* 时间格式：秒 分 时 月 日 周
* 可参考linux的crontab格式
* @Date 2015-03-18
*/
namespace SysPlugin;
add_action('on_worker_start',array('\\SysPlugin\\StartTimer','init'));
/*
 * cron_mark1 钩子形式 启动定时器
 */
class StartTimer{
	/**
	* 初始化
	* @access public
	* @param \swoole_server $serv
	* @param int $worker_id
	* @return void
	*/
	public static function init($serv,$worker_id){
		global $php;
		//只有当开启定时器　或　worker_id等于１，才执行，以保证只有一个进程启动定时器
		if(!$php->c('server.enable_timer') || $worker_id>0){
			return;
		}
		//载入定时任务脚本
		if(!is_dir(CRON_PATH)){
			return;
		}
		$odir = opendir(CRON_PATH);
		/*
		 * cron_mark2  加载全部定时器
		 */
		while($file = readdir($odir)){
			if($file{0}!='.' && ($task_file = CRON_PATH.$file) && file_exists($task_file)){
				$cron_name = ucwords(explode('.',$file,2)[0]);
				$errmsg = '';
				\Lib\Timer::run($cron_name);
				if(!empty($errmsg)){
					em_log(__METHOD__.' '.$errmsg);
				}
			}
		}
		closedir($odir);
		return;
	}
}