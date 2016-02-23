<?php
/*
* @Desc 微信独立配置类
* @Auth Sang
* @Date 2015-07-02 14:22:24
**/
namespace Lib\App;
class Config{
	public static function get($name){
		return config($name,'',self::getTableName());
	}

	public static function set($name,$value){
		return config($name,$value,self::getTableName());
	}

	public static function delete($name){
		return config($name,null,self::getTableName());
	}

	public static function getAccessToken(){
		$access_token = self::get('open_access_token');
		if(isset($access_token['component_access_token'])){
			return $access_token['component_access_token'];
		}
		return false;
	}

	private static function getTableName(){
		global $php;
		$table_name = $php->c('app.config_table');
		return $table_name ? $table_name : 'wx_config';
	}

	public static function setAccessToken($token_info){
		if(!isset($token_info['create_time']) || !isset($token_info['expires_in']) || !isset($token_info['component_access_token'])){
			return false;
		}
		self::set('open_access_token',$token_info);
	}

	public static function getPreAuthCode(){
		$code = self::get('pre_auth_code');
		if(isset($code['pre_auth_code'])){
			return $code['pre_auth_code'];
		}

		return false;
	}

	public static function setPreAuthCode($code_info){
		if(!isset($token_info['create_time']) || !isset($token_info['expires_in']) || !isset($token_info['pre_auth_code'])){
			return false;
		}
		self::set('pre_auth_code',$code_info);
	}

	public static function getTicket(){
		return self::get('ComponentVerifyTicket');
	}

	public static function setTicket($ticket){
		return self::set('ComponentVerifyTicket',['ticket'=>$ticket,'create_time'=>date('Y-m-d H:i:s')]);
	}
}