<?php
/**
* @Auth Sang
* @Desc 加密类2，实现与JAVA互通
* @Date 2015-04-17
*/
namespace Lib;
class Encrypt2{
	public static function encrypt($input, $key='') {
		if(empty($input)) return '';
		$key = '3Wri9i2abNXlLhmi';
		$size = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB);
		$input = self::pkcs5Pad($input, $size);
		$td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_ECB, '');
		$iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
		mcrypt_generic_init($td, $key, $iv);
		$data = mcrypt_generic($td, $input);
		mcrypt_generic_deinit($td);
		mcrypt_module_close($td);
		$data = base64_encode($data);
		return $data;
	}
 
	private static function pkcs5Pad ($text, $blocksize) {
		$pad = $blocksize - (strlen($text) % $blocksize);
		return $text . str_repeat(chr($pad), $pad);
	}
 
	public static function decrypt($input, $key='') {
		if(!$input){return '';}
	    $key = '3Wri9i2abNXlLhmi';
		$decrypted= mcrypt_decrypt(MCRYPT_RIJNDAEL_128,$key,base64_decode($input),MCRYPT_MODE_ECB);
		$dec_s = strlen($decrypted);
		$padding = ord($decrypted[$dec_s-1]);
		$decrypted = substr($decrypted, 0, -$padding);
		return $decrypted;
	}	
}