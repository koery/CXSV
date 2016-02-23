<?php
/*
Auth:Sang
Desc:系统日志模型
Date:2014-10-27
*/
namespace Mod;
use Lib\Model;
class SysLog extends Model{
	private $columns = 'op_id, user_name, action, class_name , class_obj, result , op_time';
	
	public function addLog($user_name, $action, $class_name , $class_obj ,$result = "") {
		$now_time=time();
		$insert_data = array ('user_name' => $user_name, 'action' => $action, 'class_name' => $class_name ,'class_obj' => $class_obj , 'result' => $result ,'op_time' => $now_time);
		$id = $this->insert($insert_data);
		return $id;
	}
	
	public function getLogs($class_name,$user_name,$start ,$page_size,$start_date='',$end_date='') {
		$condition=array();
		$where = "1";
		$sub_condition = array();
		if($class_name != '' && $class_name!='ALL'){
			$where .= " and class_name='{$class_name}'";
		}	
		if($user_name != ''){
			$where .= " and user_name='{$user_name}'";
		}
		if(!empty($start_date)){
			$where .= " and op_time>={$start_date}";
		}
		if(!empty($end_date)){
			$where .= " and op_time<={$end_date}";
		}
		
		return $this->where($where)->order('op_id desc')->limit($start,$page_size)->select();
	}
	
	public function searchCount($class_name,$user_name,$start_date,$end_date) {
		$where = "1";
		if($class_name != '' && $class_name!='ALL'){
			$where .= " and class_name='{$class_name}'";
		}
		if($user_name != ''){
			$where .= " and user_name='{$user_name}'";
		}
		if(!empty($start_date)){
			$where .= " and op_time>='{$start_date}'";
		}
		if(!empty($end_date)){
			$where .= " and op_time<='{$end_date}'";
		}
		$num = $this->where($where)->count();
		return $num;
	}
}
?>