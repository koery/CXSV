<?php
/*
* @Desc API的基类
* @Auth coco
* @Date 01/23/2016 10:42:58 AM
**/
namespace Act;
use Lib\Action;
class BaseApi extends Action{
	/**
	* 成功时输出数据
	* @access public
	* @param string $data
	* @return string[json]
	*/
	protected function success($data){

		$return = [
	       	 'success' => 1,
	       	 'data'	=> $data,
    	];
		return json_encode($data,JSON_UNESCAPED_UNICODE);
	}

	/**
	* 输出错误信息
	* @access public
	* @param string $msg
	* @param int $error_no
	* @param int $code
	* @return string[json]
	*/
	protected function error($msg='',$error_no=null,$code=null){
	
		$return = [
			'error' => 1,
			'error_no' => $error_no,
			'msg' => $msg,
			'code' => $code,
		];
		return json_encode($return,JSON_UNESCAPED_UNICODE);
	}
}