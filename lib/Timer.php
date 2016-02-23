<?php
/*
* @Desc 定时器封装
* @Auth Sang
* @Date 2015-12-18 16:08:56
**/
namespace Lib;
class Timer{
	// 开始时间
	private static $start_time;
	// 计数 秒
	private static $second=0;
	// 计数 分
	private static $minute=0;
	// 计数 时
	private static $hour=0;
	// 计数 日
	private static $day=0;
	// 计数 月
	private static $month=0;
	// 计数 周
	private static $week=0;
	// 计数 年
	private static $year=0;

	// 当前 月
	private static $now_m=0;
	// 当前 日
	private static $now_d=0;
	// 当前 周
	private static $now_w=0;
	// 当前 时
	private static $now_h=0;
	// 当前 分
	private static $now_i=0;
	// 当前 秒
	private static $now_s=0;

	/**
	* 添加并运行一个定时器
	* @access public
	* @param string $cron_name
	* @return bool
	*/
	/*
	 * cron_mark3 运行定时器
	 */
	public static function run($cron_name,&$errmsg=''){
		if(self::isRuning($cron_name)){
			$errmsg = "the cron {$cron_name} is runing!";
			return false;
		}
		$cron_class_name = '\\Cron\\'.$cron_name;
		if(!class_exists($cron_class_name)){
			$errmsg = 'the cron: '.$cron_name.' does not exists!';
			return false;
		}
		if(!isset($cron_class_name::$time) || !self::checkTime($cron_class_name::$time)){
			$errmsg = 'invalid time format on cron '.$cron_name;
			return false;
		}
		$process = new \swoole_process(function($worker) use($cron_name,$cron_class_name){
			$worker->name('php_cron_worker:'.$cron_name.' from '.APP_NAME);
			swoole_timer_tick(1000,['\Lib\Timer','onTimer'],$cron_class_name);//cron_mark 每秒扫描定时器
			$worker->daemon();
		});
		$process->start();
		\swoole_process::wait();
		return true;
	}

	/**
	* 停止一个定时器
	* @access public
	* @param string $cron_name
	* @return mix
	*/
	public static function stop($cron_name){
		$result = system("ps aux|grep php_cron_worker:{$cron_name}|grep -v grep|awk '{print $2}'|xargs kill -15");
		global $php;
		$php->serv->reload();
		return $result ? $result : true;
	}

	/**
	* 列出当前正在运行的所有定时器，并返回PID和进程名称
	* @access public
	* @return array
	*/
	public static function getCrons(){
		exec("ps aux|grep php_cron_worker:|grep -v grep|awk '{print \$2 \" \" \$11 \" \"}'",$out);
		if(empty($out)){
			return [];
		}
		$status = [];
		foreach ($out as $key => $item) {
			list($pid,$pname) = explode(' ',$item,2);
			list($_,$name) = explode(':',$pname);
			$status[$name][] = [
				'pid' =>$pid,
				'pname' => $pname,
			];
		}
		return $status;
	}

	/**
	* 检测一个定时器是否在运行
	* @access public
	* @param string $cron_name
	* @return bool
	*/
	public static function isRuning($cron_name){
		$result = system("ps aux|grep php_cron_worker:{$cron_name}|grep -v grep");
		return !empty($result);
	}

	/**
	* 定时器
	* @access public
	* @param \swoole_server $serv
	* @param int $interval
	* @return void
	*/
	public static function onTimer($timer_id,$task=null){		
		// 每60秒，秒归0，分进1
		if(++self::$second==60){
			self::$minute++;
			self::$second=0;
		}

		// 每60分，分归0，时进1
		if(self::$minute==60){
			self::$hour++;
			self::$minute=0;
		}

		// 每24时，时归0，日进1
		if(self::$hour==24){
			self::$day++;
			self::$hour=0;
		}

		// 每7天，周进1
		self::$day%7==0 && self::$week++;

		// 每个月，天归0，月进1
		if(self::$day==[31,date('Y')%4==0?29:28,31,30,31,30,31,31,30,31,30,31][date('m')-1]){
			self::$month++;
			self::$day=0;
		}

		// // 当前月日时分秒周
		list(self::$now_m,self::$now_d,self::$now_h,self::$now_i,self::$now_s,self::$now_w) = explode('-',date('m-d-H-i-s-w'));
		
		$fmt = self::parseTime($task::$time);
		$task_name = self::getTaskName($task);

		if(self::inSecond($fmt['second']) && self::inMinute($fmt['minute']) && self::inHour($fmt['hour']) && self::inDay($fmt['day']) && self::inMonth($fmt['month']) && self::inWeek($fmt['week'])){
			$task_name = self::getTaskName($task);

			try{
				$task::run();
			}catch(\Exception $e){
				em_log(__METHOD__.' cron_name:'.$task.' '.$e->getMessage());
			}
		}

	}

	/**
	* 获取任务名称
	* @access private
	* @param object $task
	* @return string
	*/
	private static function getTaskName($task){
		return explode('\\',$task)[2];
	}

	/**
	* 获取运行中锁文件
	* @access private
	* @param string $task_name
	* @return string
	*/
	private static function getLockFileName($task_name){
		return '/tmp/swcron_'.$task_name.'.lock';
	}

	/**
	* 锁定
	* @access private
	* @param string $task_name
	* @return void
	*/
	private static function lock($task_name){
		touch(self::getLockFileName($task_name));
	}

	/**
	* 解锁
	* @access private
	* @param string $task_name
	* @return void
	*/
	private static function unlock($task_name){
		$lock_file = self::getLockFileName($task_name);
		if(is_file($lock_file)){
			unlink($lock_file);
		}
	}

	/**
	* 解锁全部
	* @access private
	* @param string $var
	* @return void
	*/
	private static function unlockAll(){
		@exec('rm -f /tmp/swcron_*');
	}

	/**
	* 是否锁定
	* @access private
	* @param string $task_name
	* @return bool
	*/
	private static function islock($task_name){
		return file_exists(self::getLockFileName($task_name));
	}

	/**
	* 解析时间格式为数组
	* @access private
	* @param string $time
	* @return array
	*/
	private static function parseTime($time){
		$arr = preg_split('/\s+/', $time);
		// 秒，分，时，日，月，周
		return array_combine(['second','minute','hour','day','month','week'], $arr);
	}

	/**
	 * @access private
	 * @param string $time
	 * @return bool
	 */
	private static function checkTime($time){
		return preg_match('/^((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)$/i',$time);
	}

	/**
	* 比较当前时间是否符合时间条件， *\/1   1,2,3   1,2,3/1   1   1-5    1-5/1，
	* 分别代表：每1秒/分/时/天/月/周，在第1,2,3秒/分/时/天/月/周，在1,2,3秒/分/时/天/月/周内每1秒/分/时/天/月/周， 在第1秒/分/时/天/月/周，1-5秒/分/时/天/月/周，1-5秒/分/时/天/月/周内每1秒/分/时/天/月/周
	* @access public
	* @param string $var
	* @return void
	*/
	private static function compare($now,$step,$fmt){
		switch(true){
			case preg_match('/^\*\/\d+$/',$fmt):
				// echo 'fmt1';
				return self::compareFmt_1($now,$step,$fmt);
			break;
			case preg_match('/^\d+(,\d)+\/\d+$/',$fmt):
				// echo 'fmt3';
				return self::compareFmt_3($now,$step,$fmt);
			break;
			case preg_match('/^\d+(,\d)+$/',$fmt):
				// echo 'fmt2';
				return self::compareFmt_2($now,$step,$fmt);
			break;
			case preg_match('/^\d+$/',$fmt):
				// echo 'fmt4';
				return self::compareFmt_4($now,$step,$fmt);
			break;
			case preg_match('/^\d+\-\d+\/\d+$/',$fmt):
				// echo 'fmt6';
				return self::compareFmt_5($now,$step,$fmt);
			case preg_match('/^\d+\-\d+$/',$fmt):
				// echo 'fmt5';
				return self::compareFmt_6($now,$step,$fmt);
			break;
			default:
				return true;
			break;
		}
	}

	/**
	* 比较格式1 *\/1
	* @access public
	* @param int $time
	* @param string $fmt
	* @return bool
	*/
	private static function compareFmt_1($now,$step,$fmt){
		list($_,$int) = explode('/',$fmt,2);
		return $step % $int==0;
	}

	/**
	* 比较格式2   1,2,3
	* @access public
	* @param int $time
	* @param string $fmt
	* @return bool
	*/
	private static function compareFmt_2($now,$step,$fmt){
		$in = explode(',',$fmt);
		return in_array($now , $in);
	}

	/**
	* 比较格式3   1,2,3/1
	* @access public
	* @param int $time
	* @param string $fmt
	* @return bool
	*/
	private static function compareFmt_3($now,$step,$fmt){
		list($in,$int) = explode('/',$fmt);
		$in = explode(',',$in);
		return in_array($now, $in) && $step%$int==0;
	}

	/**
	* 比较格式4   1
	* @access public
	* @param int $time
	* @param string $fmt
	* @return bool
	*/
	private static function compareFmt_4($now,$step,$fmt){
		return $now==$fmt;
	}

	/**
	* 比较格式5    1-5
	* @access public
	* @param int $time
	* @param string $fmt
	* @return bool
	*/
	private static function compareFmt_5($now,$step,$fmt){
		list($start,$end) = explode('-',$fmt);
		return in_array($now, range($start, $end));
	}

	/**
	* 比较格式6    1-5/1
	* @access public
	* @param int $time
	* @param string $fmt
	* @return bool
	*/
	private static function compareFmt_6($now,$step,$fmt){
		list($in,$int) = explode('/',$fmt);
		list($start,$end) = explode('-',$in);
		return in_array($now, range($start, $end)) && $step%$int==0;
	}
	


	/**
	* 每秒执行
	* @access public
	* @param int $secondFmt
	* @return bool
	*/
	private static function inSecond($secondFmt){
		return self::compare(self::$now_s,self::$second,$secondFmt) || $secondFmt=='*';
	}

	/**
	* 每分执行
	* @access private
	* @param string $minuteFmt
	* @return bool
	*/
	private static function inMinute($minuteFmt){
		return self::compare(self::$now_i,self::$minute,$minuteFmt) && (self::$minute>0 || self::$hour>0) || $minuteFmt=='*';
	}

	/**
	* 每小时执行
	* @access private
	* @param string $hourFmt
	* @return bool
	*/
	private static function inHour($hourFmt){
		return self::compare(self::$now_h,self::$hour,$hourFmt) && (self::$hour>0 || self::$day>0) || $hourFmt=='*';
	}

	/**
	* 每日执行
	* @access private
	* @param string $dayFmt
	* @return bool
	*/
	private static function inDay($dayFmt){
		return self::compare(self::$now_d,self::$day,$dayFmt) && (self::$day>0 || self::$month>0) || $dayFmt=='*';
	}

	/**
	* 每月执行
	* @access private
	* @param string $monthFmt
	* @return bool
	*/
	private static function inMonth($monthFmt){
		return self::compare(self::$now_m,self::$month,$monthFmt) && self::$month>0 || $monthFmt=='*';
	}

	/**
	* 每周执行
	* @access private
	* @param string $weekFmt
	* @return bool
	*/
	private static function inWeek($weekFmt){
		return self::compare(self::$now_w,self::$week,$weekFmt) && (self::$week>0) || $weekFmt=='*';
	}
}