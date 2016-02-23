<?php
/**
* @Auth Sang
* @Desc 验证邮件类
* @Date 2015-04-24
*/
namespace Lib;
class VerifyEmail{
    // 记录错误信息
    private $error = '';

    // 验证邮件有效期:小时
    private $expires = 24;

    // 最大发送次数
    private $max_send_num = 5;

    // 两次发送的时间间隔:分钟
    private $limit_time = 1;

    public function send($username,$email,$redirect_url){
        if(empty($email) || !is_mail($email)){
            $this->error = "不是有效的邮箱地址";
            return false;
        } 
        if(empty($redirect_url) || !is_url($redirect_url)){
            $this->error = "不是有效的URL";
            return false;
        }
        $token = $this->genToken($email);
        $cache_data = cache($token);
        // 验证一下发送次数
        if(!$this->checkSend($cache_data)){
            $this->error = '对不起，你发送的次数太频繁';
            return false;
        }
        // 要保存到cache的数据
        $data = [
            'email' => $email, //当前邮箱
            'num' => isset($cache_data['num']) ? $cache_data['num']+1 : 1, //发送次数+1
            'send_time' => time(),
        ];
        // 将数据保存到cache
        cache($token,$data,$this->expires*3600);
        // 开始组织要发送的邮件的内容
        $title = config('site_name').'验证邮件'; //邮件标题
        $body = $this->getBody($username,$redirect_url,$token); //邮件内容
        if(empty($body)){
        	return false;
        }
        // 发送邮件
        $ret = send_mail($email,$title,$body);
        if($ret===true){
            return ['success'=>1,'expires'=>$this->expires];
        }else{
            $this->error = $ret;
            return false;
        }
    }

    /**
    * 获取错误信息
    * @access public
    * @return string
    */
    public function getError(){
        return $this->error;
    }

    /**
    * 验证发送次数和发送间隔
    * @access private
    * @param array $cache_data
    * @return bool
    */
    private function checkSend($cache_data){
        if((isset($cache_data['num']) && $cache_data['num']>=$this->max_send_num) || (isset($cache_data['send_time']) && time()-$cache_data['send_time']<$this->limit_time*60)){
            return false;
        }

        return true;
    }

    /**
    * 生成TOKEN
    * @access private
    * @param string $email
    * @return string
    */
    
    private function genToken($email){
        // 以IP+用户邮箱的md5做为TOKEN，以控制用户以送邮件的次数
        return md5($_SERVER['REMOTE_ADDR']);
    }

    /**
    * 验证邮件链接跳转过来的token参数是否有效
    * @access public
    * @param string $token
    * @return array('email'=>当前验证的email,'num'=>'已发送次数','send_time'=>'邮件发送时间戳')。无效返回空数组
    */
    public function checkToken($token){
    	if($cache_data = cache($token)){
    		cache($token,null);
    		return $cache_data;
    	}
    	return false;
    }

    /**
    * 组织邮件内容
    * @access private
    * @param string $username 用户名
    * @param string $redirect_url 跳转地址
    * @param string $token TOKEN
    * @return string
    */
    private function getBody($username,$redirect_url,$token){
        // 在邮件模板中选取ID为1的模板，具体设置见后台
        $tpl = model('MailTpl')->getMailTplById(1);
        if(empty($tpl) || empty($tpl['content'])){
            $this->error = '不存在邮件模板';
            return false;
        }
        //生成链接
        $url = $this->createUrl($redirect_url,$token);
        $patten = ['{$user_name}','{$site_name}','{$verify_url}','{$expires}','{$date}'];
        $replace = [$username,config('site_name'),$url,$this->expires,date('Y-m-d H:i:s')];
        return str_replace($patten,$replace,htmlspecialchars_decode($tpl['content']));
    }

    /**
    * 创建链接
    * @access private
    * @param string $redirect_url
    * @param string $token
    * @return string
    */
    private function createUrl($redirect_url,$token){
        $url_info = parse_url($redirect_url);
        if(isset($url_info['query'])){
            parse_str($url_info['query'],$query);
            if(isset($query['token'])){
                $query['token'] = $token;
            }
            $url_info['query'] = $this->array2query($query);
        }else{
            $url_info['query'] = 'token='.$token;
        }
        $url_info['path'] = isset($url_info['path']) ? $url_info['path'] : '/';
        $url = $url_info['scheme'].'://'.$url_info['host'].(isset($url_info['port']) ? ':'.$url_info['port'] : '').$url_info['path'].'?'.$url_info['query'];
        
        return $url;
    }
    
    /**
    * 将数组转换成查询参数
    * @access private
    * @param array $query
    * @return string
    */
    private function array2query($query){
        if(empty($query) || !is_array($query)){
            return $query;
        }
        $str = ''; 
        foreach ($query as $key => $val) {
            $str .= "{$key}={$val}&amp;";            
        }
        return $str ? substr($str, 0,-5) : '';
    }
}