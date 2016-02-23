<?php
/*
Auth:Sang
Desc:验证码类
Date:2014-11-01
*/
namespace Lib\VerifyCode;
class VerifyCode{
	const CODE_LENGTH = 4;
	public static function show($cn=false){
		set_header('Content-Type','image/png');
		$height = min(get('h',28,'absint'),70);
		$width = min(get('w',55,'absint'),120);
		
		$im = imagecreatetruecolor($width, $height);

		$english = array(2,3,4,5,6,7,8,9,'A','B','C','D','E','F','G','H','I','J','K','L','M','N','P','Q','R','S','T','U','V','W','X','Y','Z','a','b','c','d','e','f','g','h','j','k','m','n','p','q','r','s','t','u','v','w','x','y','z');
		if($cn){
			$chinese = array("人","出","来","友","学","孝","仁","义","礼","廉","忠","国","中","易","白","者","火 ","土","金","木","雷","风","龙","虎","天","地", "生","晕","菜","鸟","田","三","百","钱","福","爱","情","兽","虫","鱼","九","网","新","度","哎","唉","啊","哦","仪","老","少","日","月","星","于","文","琦","搜","狐","卓","望"); 
		}else{
			$chinese= array();
		}
		$chars = array_merge($english,$chinese);
		// 创建颜色 
		$fontcolor = imagecolorallocate($im, 0x6c, 0x6c, 0x6c); 
		//$bg = imagecolorallocate($im, rand(0,85), rand(85,170), rand(170,255)); 
		$bg = imagecolorallocate($im, 0xfc, 0xfc, 0xfc); 
		imagefill($im, 0, 0, $bg);
		
		// 设置文字 
		$text = "";
		for($i=0;$i<self::CODE_LENGTH;$i++) $text .= trim($chars[rand(0,count($chars)-1)]); 

		session('verify_code',strtolower($text)); 

		$font = __DIR__.'/tahoma.ttf'; 

		$gray = ImageColorAllocate($im, 200,200,200); 

		// 添加文字 
		imagettftext($im, min(30,get('f',15,'absint')), 0, 1, 23, $fontcolor, $font, $text); 

		//加入干扰象素
		$r = rand()%50;
		for($i=0;$i<150;$i++){
			$x=sqrt($i)*2+$r;
			imagesetpixel($im, abs(sin($i)*80) , abs(cos($i)*28) , $gray); 
			imagesetpixel($im, $x , $i , $gray); 
			imagesetpixel($im, rand()%80 , rand()%28 , $gray); 
			
		}

		// 输出图片 
		imagepng($im); 
		imagedestroy($im); 
	}

	public static function check($verify_code=''){
		return session('verify_code') == strtolower(empty($verify_code) ? post('verify_code') : $verify_code);
	}
}