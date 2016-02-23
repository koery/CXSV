<?php
/*
Auth:Sang
Desc:基础公用方法
Date:2014-11-01
*/
namespace Lib;
class Common {
	//获取action_url，用于权限验证
	public static function getActionUrl(){
		$action_script=$_SERVER['PATH_INFO'];
		$action_script = str_replace('//','/',$action_script);
		if(substr($action_script,-1)=='/')
			$action_script = $action_script.'index';
		
		return $action_script;
	}
	
	public static function getIp() {
		if (isset($_SERVER["HTTP_CLIENT_IP"]) && strcasecmp ( $_SERVER["HTTP_CLIENT_IP"], "unknown" )) {
			$ip = $_SERVER["HTTP_CLIENT_IP"];
		} elseif (isset($_SERVER["HTTP_X_FORWARDED_FOR"]) && strcasecmp ( $_SERVER["HTTP_X_FORWARDED_FOR"], "unknown" )) {
			$ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
		} elseif (isset($_SERVER["REMOTE_ADDR"]) && strcasecmp ( $_SERVER["REMOTE_ADDR"], "unknown" )) {
			$ip = $_SERVER["REMOTE_ADDR"];
		} elseif (isset ( $_SERVER ['REMOTE_ADDR']) && $_SERVER ['REMOTE_ADDR'] && strcasecmp ( $_SERVER ['REMOTE_ADDR'], "unknown" )) {
			$ip = $_SERVER ['REMOTE_ADDR'];
		} else {
			$ip = "unknown";
		}
		return $ip;
	}
	
	public static function getDateTime($time = null) {
		
		return (!is_numeric($time)) ? date ( 'Y-m-d H:i:s' ) : date( 'Y-m-d H:i:s', $time);
	}

	public static function getDate($time){
		return (!is_numeric($time)) ? date ( 'Y-m-d' ) : date( 'Y-m-d', $time);
	}
	
	public static function getSysInfo() {
		global $php;
		if(function_exists('system')){
			exec('ifconfig',$ip_info);
			preg_match_all('/addr:(\d+\.\d+\.\d+\.\d+)/', join('',$ip_info), $matches);
			$matches = $matches[1];
			foreach($matches as $k=>$v){
				if(strpos($v, '127')===0){
					unset($matches[$k]);
				}
			}
			$ip_info = join(' , ',$matches);
		}else{
			$ip_info = 'unknown';
		}
		$sys_info_array = array ();
		$sys_info_array ['gmt_time'] = gmdate ( "Y年m月d日 H:i:s", time () );
		$sys_info_array ['bj_time'] = gmdate ( "Y年m月d日 H:i:s", time () + 8 * 3600 );
		$sys_info_array ['server_ip'] = $ip_info;
		$sys_info_array ['software'] = SERVER_NAME;
		$sys_info_array ['port'] = $_SERVER['SERVER_PORT'];
		$sys_info_array ['admin'] = ADMIN_EMAIL;
		$sys_info_array ['diskfree'] = intval (diskfreespace(DOCUMENT_ROOT)/(1024*1024)) . 'Mb';
		$sys_info_array ['current_user'] = @get_current_user();
		$sys_info_array ['timezone'] = date_default_timezone_get();
		$mysql_version = model()->query("select version()")->fetch();
		$sys_info_array ['mysql_version'] = $mysql_version['version()'];
		$sys_info_array ['memoryfree'] = get_memfree();
		$sys_info_array ['cpufree'] = get_cpufree();
		$use_memory = memory_get_usage(true)/1024/1024;
		$proc_num = $php->c('global.worker_num');
		$sys_info_array['memory_peak'] = memory_get_peak_usage(true)/1024/1024;
		$sys_info_array ['memory_used'] = '每进程占'.$use_memory.'M x '.$proc_num.'进程 = '.$use_memory*$proc_num;
		return $sys_info_array;
	}
	
	
	public static function getRandomIp() {
		$ip = '';
		for($i = 0; $i < 4; $i ++) {
			$ip_str = rand ( 1, 255 );
			$ip .= ".$ip_str";
		}
		$ip = substr($ip, 1);
		
		return $ip;
	}

	/**
    * 生成随机数字
    * @access public
    * @param int $length 长度
    * @param bool $include_str 是否包含字母
    * @return string
    */
    public static function getRandString($length=6,$include_letter=false){
        if($length<=0){
            $length = 6;
        }
        $chars = range(0,9);
        if($include_letter){
        	$chars = array_merge($chars,['A','B','C','D','E','F','G','H','I','J','K','L','M','N','P','Q','R','S','T','U','V','W','X','Y','Z','a','b','c','d','e','f','g','h','j','k','m','n','p','q','r','s','t','u','v','w','x','y','z']);
        }
        $rand_num = '';
        for($i=0;$i<$length;$i++){
            $rand_num .= $chars[array_rand($chars)];
        }
        return $rand_num;
    }
	
	public static function filterText($text){
		if(ini_get('magic_quotes_gpc')){
			$text=stripslashes($text);
		}
		return $text;
	}

	public static function renderJsConfirm($class,$confirm_title="确定要这样做吗？"){
		$confirm_html="<script>";
		if(!is_array($class)){
			$class=explode(',',$class);
		}
		
		foreach($class as $item){
			$confirm_html .= "
				$('.$item').click(function(){
						
						var href=$(this).attr('href');
						bootbox.confirm('$confirm_title', function(result) {
							if(result){

								location.replace(href);
							}
						});		
					})
					
				";
		}
	
		$confirm_html.="</script>

";	
		return $confirm_html;
	}

	public static function day2year($d){
		return $d%12 == 0 ? ($d/12).'年' : $d.'个月';
	}

	/**
	* 生成地区联动的JS代码
	* @access public
	* @param Int $level:联动级别
	* @return String
	*/
	public static function renderJsRegion($level=3){
		$selected = $changes = '';
		switch($level){
			case 3:
				$selected = 'select[name="province"],select[name="city"]';
				$changes = "
							if(\$self.attr('name')=='province'){
								e.change(_change);
							}
						";
				break;
			case 2:
				$selected = 'select[name="province"]';
				break;
			default:
				return '';
				break;
		}
		return <<<EOT
<script type="text/javascript">
	\$(function(){
		\$('$selected').change(_change);
		function _change(e){
			\$self = \$(this);
			\$.get(window.location.protocol+'//'+window.location.host+'/ajax/getRegion?type='+this.name+'&fid='+this.value+'&t='+Math.random(),function(data){
				var next={},i=0;
				while(next = \$self.next('select')){
					next.remove();
					if(next.length==0) break;
				}
				if(data){
					var e = \$(data);
					$changes
					\$self.after(e);
				}
			});
		}
	});
</script>
EOT;
	}

	/**
	* 产生订单号
	* @access public
	* @return String
	*/
	public static function genOrderSn(){
		$order_sn = date('Ymd');
		$mtime = explode('.',microtime(true))[1];
		$order_sn .= substr(time(),-5).str_pad($mtime, 4,0,STR_PAD_RIGHT).rand(10,99);
		return $order_sn;
	}

	/**
	* 获得银行代码和列表
	* @access public
	* @return Array
	*/
	public static function getBankList(){
		return [
				"ABC"=>"中国农业银行",
				"BOC"=>"中国银行",
				"CMB"=>"招商银行",
				"COMM"=>"交通银行",
				"ICBC"=>"中国工商银行",
				"PSBC"=>"中国邮政储蓄银行",
				"SPDB"=>"上海浦东发展银行",
				"SZPAB"=>"平安银行",
				"CCB"=>"中国建设银行",
				"CMBC"=>"中国民生银行",
				"CITIC"=>"中信银行",
				"BCCB"=>"北京银行",
				"BJRCB"=>"北京农商行",
				"BOS"=>"上海银行",
				"CBHB"=>"渤海银行",
				"CCQTGB"=>"重庆三峡银行",
				"CEB"=>"中国光大银行",
				"CIB"=>"兴业银行",
				"CITYBANK"=>"城市商业银行",
				"COUNTYBANK"=>"村镇银行",
				"CSCB"=>"长沙银行",
				"CZB"=>"浙商银行",
				"CZCB"=>"浙江稠州商业银行",
				"EGBANK"=>"恒丰银行",
				"GDB"=>"广东发展银行",
				"GNXS"=>"广州市农信社",
				"GZCB"=>"广州市商业银行",
				"HCCB"=>"杭州银行",
				"HKBCHINA"=>"汉口银行",
				"HKBEA"=>"东亚银行",
				"HSBANK"=>"徽商银行",
				"HXB"=>"华夏银行",
				"NBCB"=>"宁波银行",
				"NJCB"=>"南京银行",
				"RCB"=>"农村商业银行",
				"RCC"=>"农村信用合作社",
				"SDB"=>"深圳发展银行",
				"SHRCB"=>"上海农村商业银行",
				"SNXS"=>"深圳农村商业银行",
				"SXJS"=>"晋城市商业银行",
				"UCC"=>"城市信用合作社",
				"UPOP"=>"银联卡",
				"URCB"=>"农村合作银行",
				"WZCB"=>"温州市商业银行",
				"YDXH"=>"尧都信用合作联社",
			];
	}

	/**
	* 产生密码  双重MD5 加扰码
	* @access public
	* @param string $password
	* @return Array
	*/
	public static function genPassword($password,$salt=''){
		$salt or $salt = substr(uniqid(rand()), -6);
		return array(md5(md5($password).$salt),$salt);
	}

	/**
	* 生成支付密码，sha1加密，有私钥
	* @access public
	* @param string $password
	* @return string
	*/
	public static function getPayPassword($password){
		//私钥
	    $key='8af8b271496beb3917aa424026926544';
	    return hash_hmac('sha1', $password, $key);
	}
}