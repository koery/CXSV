<?php
namespace Lib;
/**
 * Swoole错误类
 * 错误输出、数据调试、中断程序运行
 * @package SwooleSystem
 * @subpackage Error
 * @author Tianfeng.Han
 *
 */
class Error extends \Exception{
    /**
     * 错误ID
     * @var unknown_type
     */
	public $error_id;
	/**
	 * 错误信息
	 * @var unknown_type
	 */
	public $error_msg;
	static public $error_code;
    static public $stop = true;
    static $echo_html = false;

	/**
	 * 输出一条错误信息，并结束程序的运行
	 * @param $msg
	 * @param $content
	 * @return unknown_type
	 */
	static function info($msg,$content,$debug=1){
        $info = '';
        if(is_ajax()){
        	if(defined('DEBUG') && DEBUG!='off'){
        		return json_encode(['error'=>$msg,'msg'=>$content],JSON_UNESCAPED_UNICODE);
	        }else{
        		return json_encode(['error'=>$msg,'msg'=>'The Page Have An Errors'],JSON_UNESCAPED_UNICODE);
	        }
        }elseif(self::$echo_html)
		$info .= <<<HTMLS
<html>
<head>
<title>$msg</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<style type="text/css">
*{
	font-family:		Consolas, Courier New, Courier, monospace;
	font-size:			14px;
}
body {
	background-color:	#fff;
	margin:				40px;
	color:				#000;
}

#content  {
border:				#999 1px solid;
background-color:	#fff;
padding:			20px 20px 12px 20px;
line-height:160%;
}

h1 {
font-weight:		normal;
font-size:			14px;
color:				#990000;
margin: 			0 0 4px 0;
}
</style>
</head>
<body>
	<div id="content">
		<h1>$msg</h1>
		<p>$content</p><pre>
HTMLS;
        else{
            $info .= "$msg: $content\n";
        }
	    if(defined('DEBUG') and DEBUG!='off' and $debug):

	        $trace = array_reverse(debug_backtrace());
	        $info .= str_repeat('-', 100)."\n";
	        foreach($trace as $k=>$t){
	        	$k++;
	            if(empty($t['line'])) $t['line'] = 0;
	            if(empty($t['class'])) $t['class'] = '';
	            if(empty($t['type'])) $t['type'] = '';
	            if(empty($t['file'])) $t['file'] = 'unknow';
	            $info .= "#$k line:{$t['line']} call:{$t['class']}{$t['type']}{$t['function']}\tfile:".str_replace(array('/dev/shm/',APP_PATH,FRAME_ROOT),'',$t['file'])."\n";
	        }
	        $info .= str_repeat('-', 100)."\n";
	        if(self::$echo_html){
	            $info .= '</pre></div></body></html>';
	        }
        endif;
		return $info;
	}
	static function warn($title,$content){
		echo '<b>Warning </b>:'.$title."<br/> \n";
		echo $content;
	}
	/**
	 * 调试Session
	 * @return unknown_type
	 */
	static function sessd(){
		echo '<pre>';
		echo '<h1>Session Data:</h1><hr />';
		var_dump($_SESSION);
		echo '<h1>Cookies Data:</h1><hr />';
		var_dump($_COOKIE);
		echo '</pre>';
	}

	static function reqd(){
		echo '<pre>';
		echo '<h1>POST Data:</h1><hr />';
		var_dump($_POST);
		echo '<h1>GET Data:</h1><hr />';
		var_dump($_GET);
	}

	static function servd(){
		echo '<pre>';
		echo '<h1>Server Data:</h1><hr />';
		var_dump($_SERVER);
		echo '<h1>ENV Data:</h1><hr />';
		var_dump($_ENV);
		echo '<h1>REQUEST Data:</h1><hr />';
		var_dump($_REQUEST);
		echo '</pre>';
	}

	static function debug($var){
		debug($var);
	}
	static function dump(){
		echo '<pre>';
	    $vars = func_get_args();
	    foreach($vars as $var) var_dump($var);
	    echo '</pre>';
	}
	/**
	 * 以表格的形式显示一个2维数组
	 * @param $var
	 * @return unknown_type
	 */
	static function output($var){
		if(!is_array($var)) self::warn('Error Debug!','Not is a array!');
		$attr['border'] = 1;
		$attr['style'] = 'font-size:14px';

		$table = new HTML_table($var, $attr);
		echo $table->html();
	}
	static function parray($array){
		if(!is_array($array)) self::warn('Error Debug!','Not is a array!');
		foreach($array as $k=>$v){
			echo $k,': ';
			var_dump($v);
			echo BL;
		}
	}
	static function pecho($str){
		echo $str,"<br />\n";
	}
	/**
	 * 调试数据库
	 * @return unknown_type
	 */
	static function dbd($bool = true){
		global $php;
		if($bool) $php->db->debug = true;
		else $php->db->debug = false;
	}
	static function tpld($bool = true){
		global $php;
		if($bool) $php->tpl->debugging = true;
		else $php->tpl->debugging = false;
	}
	function __toString(){
		if(!isset(self::$error_code[$this->error_id])) return 'Not defined Error';
		return self::$error_code[$this->error_id];
	}

	public static function trace(){
		$trace = array_reverse(debug_backtrace());
		$info = "\n";
		foreach($trace as $k=>$t){
        	$k++;
            if(empty($t['line'])) $t['line'] = 0;
            if(empty($t['class'])) $t['class'] = '';
            if(empty($t['type'])) $t['type'] = '';
            if(empty($t['file'])) $t['file'] = 'unknow';
            $info .= "#$k line:{$t['line']} call:{$t['class']}{$t['type']}{$t['function']} file:".$t['file']."\n";
        }
        return $info;
	}
}
