<?php
/**
* @Auth Sang
* @Desc 短信发送类
*       使用方法：先调用 setGateway 设置网关，再设置一些固定参数，比如用户名，密码之类，字段要接照服务商的要求设置，
*       再调用setParams设置业务参数，比如接收号码，发送内容等，最后调用send发送
* @Date 2015-04-17
*/
namespace Lib;
class Sms{
  private static $instance = null;
  //短信网关
  private $gateway;
  
  //参数列表
  private $params=array();

  private function __construct(){
    
  }
  
  public function setGateway($url){
    $this->gateway=$url;
    return $this;
  }
  
  public function __set($name,$val){
    $this->params[$name]=$val;
  }
  
  public function setParams($params=array()){
    $this->params=array_merge($this->params,$params);
    return $this;
  }
  
  public function send($method='post'){
      return curl($this->gateway,$method,$this->params,30);
  }

  /**
  * 单例模式入口
  * @access public
  * @param string $var
  * @return void
  */
  public static function getInstance(){
    if(empty(self::$instance)){
      self::$instance = new Sms();
    }
    self::$instance->params = [];
    return self::$instance;
  }
}