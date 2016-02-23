<?php
/*
* @Desc 微信会话类
* @Auth Sang
* @Date 2015-11-23 09:44:16
**/
namespace Lib\App;
class WXSession{
	// MONGODB集合名
	private static $mdb_table = 'wx_session';

	// 微信会话超时时间 48小时
	private static $expires_time = 172800;

	// 更新会话时间
	public static function update($open_id){
		if(empty($open_id)){
			return false;
		}
		$mdb = mongodb(self::$mdb_table);
		$now = time();
		$data = [
			'_id' => $open_id,
			'last_time' => $now,
			'expires_time' => $now+self::$expires_time
		];
		// 有一定机率调用一个异步定时器来清理过期的会话
		if(rand(1,9)==5){
			swoole_timer_after(50,function() use($mdb,$now){
				$mdb->where("expires_time<{$now}")->delete();
			});
		}
		return $mdb->save($data);
	}

	// 查询会话是否过期
	public static function query($open_id){
		if(empty($open_id)){
			return false;
		}
		$mdb = mongodb(self::$mdb_table);
		$session = $mdb->where("_id='{$open_id}'")->fetch();
		if(empty($session) || $session['expires_time']<time()){
			return false;
		}
		return $session;
	}
}