<?php
/*
Auth:Sang
Desc:加密解密类
Date:2014-11-01
*/
namespace Lib;

class Encrypt{

	public static function encrypt($value,$key=''){
	   if(!$value){return '';}
	   $key = $key ? $key : (defined('ADM_ENCRYPT_KEY') ? ADM_ENCRYPT_KEY : 'el6vr7giuxChh7DwXk5YtEmz');
	   $text = $value;
	   $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
	   $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
	   $crypttext = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $text, MCRYPT_MODE_ECB, $iv);
	   return trim(base64_encode($crypttext)); //encode for cookie
	}

	public static function decrypt($value,$key=''){
	   if(!$value){return '';}
	   $key = $key ? $key : (defined('ADM_ENCRYPT_KEY') ? ADM_ENCRYPT_KEY : 'el6vr7giuxChh7DwXk5YtEmz');
	   $crypttext = base64_decode($value); //decode cookie
	   $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
	   $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
	   $decrypttext = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $crypttext, MCRYPT_MODE_ECB, $iv);
	   return trim($decrypttext);
	}
}
?>