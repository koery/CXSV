# yedadou API 规范

* 灵活，功能优先
* 精简，逻辑清晰


## 注释

* 描述 @todo
* 参数 @param
* 返回值 @return

>@todo 描述该接口的功能实现，通常一个api接口实现单一的逻辑功能，遇到需要绑定多个功能api需要将实现的功能全部描述出来。</br>
eg:</br>
资金新增接口内通常会绑定日志记录的功能，防止开发中调用资金新增接口之后写入日志操作


>/**
>
>*@todo   ..todo something..  尽可能完成描述全部实现的功能</br>
>*@param  	int  	$uid 	 用户id</br>
>*  .</br>
>*  .</br>
>*@param 	string 	$address 用户地址</br>
>*@return 	int  	$id 	 新增的地址id</br>
>*/





##函数定义

* public/protected/private
* 函数名称  驼峰+清晰业务相关名称
* 参数 灵活传递

>参数有2种形式,灵活运用</br>
eg:<br>
	public function serach($array) //多参数的情况下<br>
    public function serach($condtion,$offset,$size,$order='id desc',$feilds='*')//少参数的情况



# api 处理流程

* 参数验证
* $where 
* sql 执行 
* 结果返回 
 
>参数验证 主要包括参数的特殊处理和验证过滤函数的处理
>结果返回函数

	public function success($msg,$data){

		$return = [
			'success' => 1,
			'data'	=> $data,
		];
		
		
	}

	public function error($msg,$error_no,code){
	
	 $return = [
			
			'msg'	=> $msg,//描述
			'error' => 1,//1 参数检验错误  2sql执行错误  3其他
			'code'	=> 100056 
		];
	}






