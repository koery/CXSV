<?php
/*
* 基本加载入口，命名空间设置，全局公共类加载，全局公共函数
 */
// 常见域名后缀
define('SUFFIX','com,cn,edu,gov,int,mil,net,org,biz,info,pro,name,museu,coop,aero,xxx,idv');

// 包含自动加载器
require LIB_PATH.'Loader.php';

// 注册命名空间
\Lib\Loader::setRootNS('Lib',LIB_PATH);
\Lib\Loader::setRootNS('Mod',MOD_PATH);

/**
 * new操作一個類時，在當前的文件下找不到類就會自動調用spl_autoload_register註冊的自動加載
 * 在本框架中自動加載函數位於Lib\Loader::autoload
 */
// 注册自动加载函数
spl_autoload_register('\\Lib\Loader::autoload');

// 设置时区
date_default_timezone_set('PRC');


/**
* 获取$_GET参数
* @param String $key
* @param Mix $default
* @param String $funs
* @return Mix
*/
function get($key,$default='',$funs=''){
  $val = _get_post($key,$funs,'get');
  return $val!==false ? $val : $default;
}

/**
* 获取$_POST参数
* @param String $key
* @param Mix $default
* @param String $funs
* @return Mix
*/
function post($key,$default='',$funs=''){
  $val = _get_post($key,$funs,'post');
  return $val!==false ? $val : $default;
}

/**
* 获取$_REQUEST参数
* @param String $key
* @param Mix $default
* @param String $funs
* @return Mix
*/
function request($key,$default='',$funs=''){
  $val = _get_post($key,$funs,'request');
  return $val!==false ? $val : $default;
}

/**
* get,post,request的处理函数
* @param String $key
* @param String $funs
* @param String $method
* @return Mix
*/
function _get_post($key,$funs='',$method='get'){
  if(empty($key)){
    throw new \Exception("key can not empty.eg:key[.key1[.key2......]]",120);
  }
  $arr = explode('.',$key);
  $method = strtolower($method);
  $data = $method == 'get' ? $_GET : ($method == 'post' ? $_POST : ($method == 'request' ? $_REQUEST : []));
  if(empty($data)){
    return false;
  }
  $key = array_shift($arr);
  $val = isset($data[$key]) ? $data[$key] : false;
  while($key = array_shift($arr)){
    $val = isset($val[$key]) ? $val[$key] : false;
  }
  if($val===false){
    return false;
  };
  if($funs){
    if(!is_array($funs)){
      $funs = explode(',',$funs);
      foreach($funs as $fun){
        /**
         * 不能使用没有返回主体的函数 eg:is_empty()
         * $param = 'aaa',需要调用函数转换为大写，需要返回AAA,
         * 而不能返回ture / fasle 
         */
        $val = call_user_func($fun,$val);
      }
    }else{
      $val = call_user_func($funs,$val);
    }
  }
  return $val;
}

/**
* 添加一个动作*
* @param String $type
* @param Mix $handler
* @param Int $weight
* @return Bool
*/

/**
 * plugin_mark3 全局插件变量保存数据
 */
function add_action($type,$handler,$weight=1){
  global $php;
  $php->plugins[$type][$weight][] = $handler;
  return true;
}

/**
* 执行一批动作
* @param String $type
* @param mix $arg<n>，默认可接受最多8个参数，一般应用应该够了。
* @return void
*/
/**
 * plugin_mark4 调用钩子 可执行多个钩子
 */
function apply_action($type,&$arg1=null,&$arg2=null,&$arg3=null,&$arg4=null,&$arg5=null,&$arg6=null,&$arg7=null,&$arg8=null){
  global $php;
  if(isset($php->plugins[$type]) && !empty($php->plugins[$type])){
    $actions = $php->plugins[$type];
    ksort($actions);
    foreach($actions as $action){
      foreach($action as $the_action){
        call_user_func_array($the_action, [&$arg1,&$arg2,&$arg3,&$arg4,&$arg5,&$arg6,&$arg7,&$arg8]);
      }
    }
  }
}

/**
* 输出调试信息
* @return void
*/
function debug(){
  $html = '<pre>';
    $vars = func_get_args();
    foreach($vars as $var) $html.=print_r($var,true);
    $html .= '</pre>';
    throw new \Exception($html, 1);
}

/**
* 抛出错误信息和代码
* @access public
* @param String $msg
* @param Int $code
* @return void
*/
function error($msg,$code=1){
  throw new \Exception($msg, $code);
}

/**
* 退出，并输出信息
* @return void
*/

function _exit($msg=''){
  if(is_array($msg) || is_object($msg)){
    $msg = json_encode($msg);
  }
  return error($msg,0);
}

/**
* 退出，不输出任何信息
* @return void
*/
function _die(){
  throw new \Exception("", PHP_INT_MAX);
}


/**
* 设置HTTP头
* @param String $key
* @param String $value
* @return void
*/
function set_header($key,$value){
  global $php;
  $php->setHeader($key,$value);
}

/**
* 根据传入的参数获取带http的二级域名，参数为空则输出顶级域名
* @param String $dom_name
* @return String
*/
function dom($dom_name=null){
    if(isset($_SERVER['SERVER_PROTOCOL'])){
      $sch = strpos($_SERVER['SERVER_PROTOCOL'],'https') !==false ? 'https://' : 'http://';
    }else{
      $sch = 'http://';
    }
    $domain = get_dom().get_port();
    if(empty($dom_name)){
        return $sch.$domain;
    }else{
        return $sch.$dom_name.'.'.$domain;  
    }
}

/**
* 获取网站根链接，无论任何情况下，都输出例如：http://www.59pi.com:port的地址
* @access public
* @param
* @return
*/
function site_url(){
  if(isset($_SERVER['SERVER_PROTOCOL'])){
    $sch = strpos($_SERVER['SERVER_PROTOCOL'],'https') !==false ? 'https://' : 'http://';
  }else{
    $sch = 'http://';
  }
  
  return $sch.val($_SERVER,'HTTP_HOST','unknown host');
}

/**
* 获取当前浏览器访问的链接
* @return String
*/
function cur_url(){
  if(isset($_SERVER['HTTP_HOST'])){
    if(isset($_SERVER['SERVER_PROTOCOL'])){
      $sch = strpos($_SERVER['SERVER_PROTOCOL'],'https') !==false ? 'https://' : 'http://';
    }else{
      $sch = 'http://';
    }
    $query_str = val($_SERVER,'QUERY_STRING');
    $query_str && $query_str = '?'.$query_str;
    $url = $sch.val($_SERVER,'HTTP_HOST','unknown host').val($_SERVER,'REQUEST_URI').$query_str;
    return $url;
  }
  return '';
}

/**
* 获取端口号
* @return string
*/
function get_port(){
  if(isset($_SERVER['HTTP_HOST']) && !empty($_SERVER['HTTP_HOST'])){
    $port = explode(':',$_SERVER['HTTP_HOST'],2);
    if(isset($port[1]) && !empty($port[1])){
      return ':'.$port[1];
    }
  }
  return '';
}

/**
* 操作COOKIE
* 只传入$name时，获取名称为$name的cookie
* 传入$name但$value为null时，删除$name的cookie
* 同时传入$name和$value，设置名称为$name，值为$value的cookie
* @param String $name
* @param String $value
* @param Int $expires
* @param String $path
* @param String $domain
* @return Mix
*/
function cookie($name,$value='',$expires=0,$path='/',$domain=''){
  global $php;
  empty($domain) && $domain = get_dom();
  $prefix = $php->c('cookie.prefix');
  if($value===''){
    return isset($_COOKIE[$name]) ? $_COOKIE[$name] : false;
  }elseif($value===null){
    $php->setCookie($name,'',-3600,$path,$domain);
  }else{
    $php->setCookie($name,$value,$expires,$path,$domain);
  }
}

/**
* 获得根域名
* @return string
*/
function get_dom($url=''){
  $suffix_list = explode(',',SUFFIX);
  if(!isset($_SERVER['HTTP_HOST'])){
    return '';
  }
  if(!empty($url) && is_url($url)){
    $url_info = parse_url($url);
    $host = $url_info['host'];
  }else{
    $host = explode(':',$_SERVER['HTTP_HOST'],2)[0];
  }
  if(is_ip($host)){
    return $host;
  }
  $arr = explode('.',$host);
  $new_arr = [];
  do{
    $suffix = array_pop($arr);
    $new_arr[] = $suffix;
  }while(in_array($suffix, $suffix_list));
  return join('.',array_reverse($new_arr));
}

/**
* 操作session
* 只传入$name时，获取名称为$name的session
* 传入$name但$value为null时，删除$name的session
* 同时传入$name和$value，设置名称为$name，值为$value的session
* $name为@id，表示获取当前session的ID
* @param String $name
* @param Mix $value
* @return Mix
*/
function session($name,$value=''){
  global $php;
  if($name{0} == '@'){
    switch(substr($name, 1)){
      case 'id':
        return $php->session->getSessionId();
        break;
      default :
        return false;
      break;
    }
  }
  $prefix = isset($php->config['session']['prefix']) ? $php->config['session']['prefix'] : '';
  if($name===null){
    return $php->session->delete();
  }
  $name = $prefix.$name;
  if($value===''){
    return isset($_SESSION[$name]) ? $_SESSION[$name] : false;
  }elseif($value===null){
    unset($_SESSION[$name]);
  }else{
    $_SESSION[$name] = $value;
  }
}

/**
* 操作储存系统
* $file_path为@开头，并且后面是一个文件的绝对路径时，表示将这个文件上传到储存系统，此时$content可当作$params
* $file_path为储存系统的文件路径，其它参数为空时，表示获取这个文件内容
* $file_path为储存系统的文件路径 $content为null时，表示删除这个文件
* $file_path为储存系统的文件路径，$content为字符串，表示将$content储存到$file_path的文件内
* $params为一个一维数组，支持的参数有：path:文件在储存系统中的位置,
* 如果$file_path = 'list'，列出存储空间的文件，$content为一个选项数组，可选项为：
* order:排序,可输入的值为：filemtime_asc:文件修改时间升序,filemtime_desc：文件修改时间降序，filename_asc：文件名升序，filename_desc:文件名降序
* path:存储空间的相对路径,如存储空间绝对路径为：/home/storate,传入 path/to，则相当于 /home/storate/path/to
* @param String $file_path
* @param String $content
* @param Array $params
* @return Mix
*/
function storage($file_path,$content = '' , $params = array()){
  global $php;
  if(empty($file_path)){
    return false;
  }
  if($file_path=='list'){
    return $php->storage->listFiles($content);
  }elseif($file_path{0} == '@'){
    return $php->storage->save($file_path,$content);
  }elseif($content===''){
    return $php->storage->get($file_path);
  }elseif($content===null){
    return $php->storage->delete($file_path);
  }else{
    if(is_string($content))
      return $php->storage->save($file_path,$content,$params);
    else
      throw new \Exception("Content must be string", 200);
  }
}

/**
* 操作缓存
* 只传入$key表示获得key为$key的缓存内容
* 传入$key和$value表示设置key为$key的，值为$value的缓存
* 传入$key，$value为null表示删除$key的缓存
* @param String $key 缓存key
* @param Mix $value 内容
* @param Int $expires 过期时间
* @return Mix
*/
function cache($key,$value='',$expires=0){
  global $php;
  if($key===null){
    return $php->cache->clean();
  }elseif($value===''){
    return $php->cache->get($key);
  }elseif($value===null){
    return $php->cache->delete($key);
  }else{
    return $php->cache->set($key,$value,$expires);
  }
}

/**
* 操作REDIS
* @param int $db 选择数据库 默认为0
* @return redis object
*/
function redis($db=0){
  global $php;
  $redis = $php->redis;
  $redis->select($db);
  return $redis;
}

/**
* 操作配置系统
* config('name')表示获取名称为name的配置项
* config('name',null)表示删除名称为name的配置项
* config('name','value')表示设置名称为name，值为value的配置项
* @param String $name 配置项名称
* @param Mix $value 配置项内容
* @param string $db_name 数据表名，需要确认数据库中存在该表
* @return Mix
*/
function config($name,$value='',$db_name='adm_system'){
  static $kvdb;
  if(empty($db_name)){
    error('db_name cannot be empty',909);
  }
  if(!isset($kvdb[$db_name])){
    $kvdb[$db_name] = new \Lib\Kvdb($db_name);
  }
  if($value===''){
    return $kvdb[$db_name]->get($name);
  }elseif($value===null){
    return $kvdb[$db_name]->delete($name);
  }else{
    return $kvdb[$db_name]->set($name,$value);
  }
}

/**
* 获得一个数据模型
* model('#TableName')表示获得服务器内置的数据模型
* model('TableName')表示获得用户自定义的数据模型
* 参数$prefix表示表前缀，如：59pi_
* @param String $mod_name 数据表名
* @param String $prefix 表前缀
* @return Object
*/
function model($mod_name='',$prefix=''){
  return \Lib\Loader::loadModel($mod_name,$prefix);
}

/**
* 获得一个mongoDB实例
* @param string $table_name
* @return object
*/
function mongodb($table_name,$is_gridfs=false){
  static $mongodbs = [];
  global $php;
  $table_name = strtolower($table_name);
  $key = $table_name.($is_gridfs ? 'true' : 'false');
  $key = md5($key);
  if(!isset($mongodbs[$key])){
    $mongodbs[$key] = clone $php->mongodb;
    $mongodbs[$key]->setCollection($table_name,$is_gridfs);
  }
  return $mongodbs[$key];
}

/**
 * curl封装
 * @param string $url 资源地址   
 * @param string $method 提交方法，默认为get
 * @param array $postfields  post表单字段        
 * @return string
 */
function curl($url,$method = 'GET',$postFields = array(),$timeout=10){
  $ch = curl_init();
  curl_setopt($ch,CURLOPT_FAILONERROR,false);
  curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
  curl_setopt($ch,CURLOPT_REFERER,$url);
  curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; rv:27.0) Gecko/20100101 Firefox/27.0');
  curl_setopt($ch, CURLOPT_HEADER, 0);
  //超时时间
  curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,5);
  curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
  
  // https 请求
  if(strlen($url) >5 &&strtolower(substr($url,0,5)) =="https"){
    curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
    curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);
  }
  $method = strtolower($method);
  if($method == 'get'){
    if(!empty($postFields)){
      $query = is_array($postFields) ? array2query($postFields) : $postFields;
      $url .= strpos($url,'?') ? '&'.$query : '?'.$query;
    }
  }else{
    if(is_array($postFields) &&0 <count($postFields)){
      $postBodyString = "";
      $postMultipart = false;
      foreach($postFields as $k=>$v){
        if(substr($v,0,1)=='@' && substr($v,1,1)=='/'){
          $postMultipart = true;
          $file = substr($v, 1);
          // 因为需要安装finfo扩展，所以暂时不用
          // $finfo=finfo_open(FILEINFO_MIME_TYPE);
          // $mime = finfo_file($finfo,$file);
          // finfo_close($finfo);
          $mime = get_mime($file);
          $postFields[$k] = new \CURLFile($file,$mime[1],basename($file));
        }else{
          $postBodyString .= "$k=" .urlencode($v) ."&";
        }
      }
      unset($k,$v);
      curl_setopt($ch,CURLOPT_POST,true);
      if($postMultipart){
        curl_setopt($ch,CURLOPT_POSTFIELDS,$postFields);
      }else{
        curl_setopt($ch,CURLOPT_POSTFIELDS,substr($postBodyString,0, -1));
      }
    }elseif(is_string($postFields) && $postFields){
        curl_setopt($ch,CURLOPT_POSTFIELDS,$postFields);
    }
  }

  
  curl_setopt($ch,CURLOPT_URL,$url);
  $reponse = curl_exec($ch);
  if($reponse){
    curl_close($ch);
    return $reponse;
  }elseif($err_str = curl_error($ch)){
    curl_close($ch);
    throw new \Exception($err_str,0);
  }
  curl_close($ch);
  return $reponse;
}

/**
* 输出运行时间
* @param String $type 如：开始用 'start' ，结束用 'end'
* @return
*/
function _time($type){
  static $types = [];
  $types[$type] = microtime(true);
  echo $type.":".$types[$type].'<br>'.PHP_EOL;
  $times = array_values($types);
  if(count($times)>1){
    echo 'runTime:'.($times[1] - $times[0]).'<br>'.PHP_EOL;
    $types = [];
  }
}

/**
* 判断是否ajax请求
* @return Bool
*/
function is_ajax(){
  return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') ||  isset($_REQUEST['ajax']);
}

/**
* 判断是否post请求
* @return Bool
*/
function is_post(){
  return (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD']=='POST') || !empty($_POST) || !empty($_FILES);
}

/**
* 判断是否PUT请求
* @return bool
*/
function is_put(){
  return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD']=='PUT';
}

/**
* 判断是否DELETE请求
* @return bool
*/
function is_delete(){
  return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD']=='DELETE';
}

/**
* 判断是否get请求
* @return Bool
*/
function is_get(){
  return !empty($_GET);
}

/**
* 获取文件的mime类型
* @param String $path 文件的绝对路径
* @return Array [后缀,mime类型]
*/
function get_mime($path) {
    $mimes = \Lib\Mimes::mimes();
    $file_info = exec('file -ib '.escapeshellarg($path));
    //文件存在
    $mime = 'application/zip';
    $type = 'zip';
    if(strpos($file_info,'ERROR')===false){
      $mime = explode(';', $file_info)[0];
      isset($mimes[$mime]) && $type = $mimes[$mime];
    }
    return [$type,$mime];
}

/**
* 302跳转
* @param String $url
* @return void
*/
function redirect($url){
  global $php;
  $php->http302($url);
}

/**
* 将输入转换为绝对整数
* @param numeric $num
* @return Int
*/
function absint($num){
  return abs(intval($num));
}

/**
* 处理长整数
* @param numeric $num
* @return numeric
*/
function longint($num){
  return preg_match('/^\d+$/',$num) ? $num : 0;
}


/**
* 获取cpu的空闲百分比 
* @return String
*/
function get_cpufree(){ 
    $cmd =  "top -n 1 -b -d 0.1 | grep 'Cpu'";//调用top命令和grep命令 
    $lastline = exec($cmd,$output); 
    
    preg_match('/(\S+)%id/',$lastline, $matches);//正则表达式获取cpu空闲百分比 
    $cpufree = $matches[1]; 
    return $cpufree; 
} 

/**
* 获取内存空闲百分比 
* @return String
*/
function get_memfree(){ 
    $cmd =  'free -m';//调用free命令 
    exec($cmd,$output); 
    preg_match('/Mem:\s*(\d+)/',$output[1], $matches); 
    $memtotal = $matches[1]; 
    preg_match('/(\d+)$/',$output[2], $matches); 
    $memfree = sprintf('%0.2f',$matches[1]*100.0/$memtotal); 
    
    return $memfree; 
} 
    
/**
* 获取某个程序当前的进程数
* @return String
*/ 
function get_proc_count($name){ 
    $cmd =  "ps -e";//调用ps命令 
    $output = shell_exec($cmd); 
    
    $result = substr_count($output, ' '.$name); 
    return $result; 
}

/**
* 移动文件到指定位置
* @param String $source 源文件路径
* @param String $dest 目录路径
* @return string
*/
function move_file($source,$dest){
  if(!is_dir(dirname($dest))){
    mkdir($dest,0777,true);
  }
  rename($source, $dest);
  return $dest;
}

/**
* 获取referer地址
* @param String $url
* @return String
*/
function referer($url=null){
  if(!empty($url)){
    session('adm_referer',$url);
    return $url;
  }
  if($url=session('adm_referer')){
    session('adm_referer',null);
    return $url;
  }elseif(isset($_SERVER['HTTP_REFERER'])){
    return $_SERVER['HTTP_REFERER'];
  }
  return 'javascript:window.history.go(-1)';
}

/**
 * 字符串截取，支持中文和其他编码
 * @param string $str 需要转换的字符串
 * @param string $start 开始位置
 * @param string $length 截取长度
 * @param string $charset 编码格式
 * @param string $suffix 截断显示字符
 * @return string
 */
function msubstr($str, $start=0, $length, $charset="utf-8", $suffix=false) {
    if(function_exists("mb_substr"))
        $slice = mb_substr($str, $start, $length, $charset);
    elseif(function_exists('iconv_substr')) {
        $slice = iconv_substr($str,$start,$length,$charset);
        if(false === $slice) {
            $slice = '';
        }
    }else{
        $re['utf-8']   = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
        $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
        $re['gbk']    = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
        $re['big5']   = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
        preg_match_all($re[$charset], $str, $match);
        $slice = join("",array_slice($match[0], $start, $length));
    }
    return $suffix ? $slice.'...' : $slice;
}

/**
* 验证是否EMAIL地址
* @param String $mail 待验证的邮箱地址
* @return bool
*/
function is_mail($mail){
  return filter_var($mail,FILTER_VALIDATE_EMAIL);
}

/**
* 验证是否合法的url
* @param string $url
* @return bool
*/
function is_url($url){
  return filter_var($url,FILTER_VALIDATE_URL);
}

/**
* 验证是否QQ号码
* @param numeric $qq
* @return bool
*/
function is_qq($qq){
  $ret = !empty($qq) && preg_match('/\d{5,15}/', $qq);
  return $ret ? $qq : false;
}

/**
* 验证是否合法的手机号码
* @param string $mobile
* @return bool
*/
function is_mobile($mobile){
  $ret = !empty($mobile) && preg_match("/^(?:\+[\d]{2,3})?1[3|4|5|7|8][0-9][\d]{8}$/",$mobile);
  return $ret ? $mobile : false;
}

/**
* 验证是否合法的电话号码
* @access public
* @param string $phone
* @return bool
*/
function is_phone($phone){
  $ret = !empty($phone) && preg_match('/^0[\d]{2,3}\-[1-9][\d]{6,8}$|^[0|4|8][\d]{2,3}[1-9][\d]{6,8}$/', $phone);
  return $ret ? $phone : false;
}

/**
* 字符串模糊化
* @param string $str 待模糊化的字符串
* @param int $start 开始位置
* @param int $length 模糊化的字数
* @return string
*/
function fuzzy($str,$start=3,$length=5){
  $str1 = mb_substr($str,0, $start);
  $str2 = str_repeat('*', $length);
  $pos = $start + $length;
  $count = mb_strlen($str);
  $str3 = $pos>=$count ? '' : mb_strcut($str, $pos);
  return $str1.$str2.$str3;
}

/**
* 邮箱地址模糊化
* @param string $email 待模糊化的字符串
* @return string
*/
function fuzzy_email($email){
  if(empty($email) || !is_mail($email)){
    return fuzzy($email,3,4);
  }
  list($account,$domain) = explode('@',$email);
  $account = fuzzy($account,2,4);
  return $account.'@'.$domain;
}

/**
* 实时发送邮件
* @param string $to 收件人
* @param string $subject 邮件标题
* @param string $body 邮件内容
* @param array $attachment 邮件附件
* @return bool
*/
function send_mail($to, $subject = '', $body = '', $attachment = null){
  if(!filter_var($to,FILTER_VALIDATE_EMAIL) || $subject=='' || $body==''){
      return false;
  }
  $mail             = new \Lib\Mailer\Mailer(); //PHPMailer对象
  $mail->CharSet    = 'UTF-8'; //设定邮件编码，默认ISO-8859-1，如果发中文此项必须设置，否则乱
  $mail->SMTPDebug  = 0;                     // 关闭SMTP调试功能
                                             // 1 = errors and messages
                                             // 2 = messages only
  $email_host = config('email_host');
  $email_addr = config('email_addr');
  $email_type = config('email_type');
  $email_port = config('email_port');
  $email_user = config('email_user');
  $email_pass = config('email_pass');

  $mail->SetFrom($email_addr, $email_addr);

  if($email_type==\Lib\Mailer\Mailer::SMTP){
      $mail->IsSMTP();  // 设定使用SMTP服务
      $mail->SMTPAuth   = true;                  // 启用 SMTP 验证功能
      $mail->Host       = $email_host;  // SMTP 服务器
      $email_port==465 && $mail->SMTPSecure ='ssl'; //是否使用ssl
      $mail->Port       = $email_port;  // SMTP服务器的端口号
      $mail->Username   = $email_user;  // SMTP服务器用户名
      $mail->Password   = $email_pass;  // SMTP服务器密码
      $mail->Timeout      = 30; //超时时间　秒
  }else{
      /* mail */
      $mail->IsMail();
  }
  
  $mail->Subject    = $subject;
  $mail->MsgHTML($body);
  $mail->AddAddress($to);
  if(is_array($attachment)){ // 添加附件
      foreach ($attachment as $file){
          is_file($file) && $mail->AddAttachment($file);
      }
  }
  return $mail->Send() ? true : $mail->ErrorInfo;
}

/**
* 图片x轴翻转，翻转后替换原图片文件
* @param string $img_file 待翻转的图片路径
* @param string $type 图片类型
* @return void
*/
function trun_x($img_file_path,$type='jpeg'){
  switch($type){
    case 'jpeg':
      $create_fun = 'imagecreatefromjpeg';
      $save_fun = 'imagejpeg';
      break;
    case 'gif':
      $create_fun = 'imagecreatefromgif';
      $save_fun = 'imagegif';
      break;
    case 'png':
      $create_fun = 'imagecreatefrompng';
      $save_fun = 'imagepng';
      break;
    default :
      $create_fun = 'imagecreatefromjpeg';
      $save_fun = 'imagejpeg';
      break;
  }
  $resource = $create_fun($img_file_path);
  $w = imagesx($resource);
  $h = imagesy($resource);
  $new = imagecreatetruecolor($w, $h);
  for ($y=0; $y < $h; $y++) { 
    imagecopy($new, $resource, 0 , $h-$y-1, 0, $y, $w, 1);
  }
  $save_fun($new,$img_file_path);
  imagedestroy($resource);
  imagedestroy($new);
}

/**
* 图片y轴翻转，翻转后替换原图片
* @param string $img_file_path 待翻转的图片路径
* @param string $type 图片类型
* @return void
*/
function trun_y($img_file_path,$type='jpeg'){
  switch($type){
    case 'jpeg':
      $create_fun = 'imagecreatefromjpeg';
      $save_fun = 'imagejpeg';
      break;
    case 'gif':
      $create_fun = 'imagecreatefromgif';
      $save_fun = 'imagegif';
      break;
    case 'png':
      $create_fun = 'imagecreatefrompng';
      $save_fun = 'imagepng';
      break;
    default :
      $create_fun = 'imagecreatefromjpeg';
      $save_fun = 'imagejpeg';
      break;
  }
  $resource = $create_fun($img_file_path);
  $w = imagesx($resource);
  $h = imagesy($resource);
  $new = imagecreatetruecolor($w, $h);
  for ($x=0; $x < $w; $x++) { 
    imagecopy($new, $resource , $w-$x-1, 0, $x,0, 1, $h);
  }
  $save_fun($new,$img_file_path);
  imagedestroy($resource);
  imagedestroy($new);
}

/**
* 验证是否合法的身份证号码
* @param string $id_card 身份证号码
* @return bool
*/
function is_id_card($id_card){
  $id_card = strip_tags($id_card);
  if(empty($id_card)){
    return false;
  }
  $id_card = _to18Card($id_card);
  if(strlen($id_card) != 18){ 
    return false;
  }
  $cardBase = substr($id_card, 0, 17); 

  return _getVerifyNum($cardBase) == strtoupper(substr($id_card, 17, 1)) ? $id_card : false;
}

/**
* 格式化15位身份证号码为18位 
* @param string $card 身份证号码
* @return string
*/
function _to18Card($card) { 
  $card = trim($card); 

  if (strlen($card) == 18) { 
      return $card; 
  } 

  if (strlen($card) != 15) { 
      return false; 
  } 

  // 如果身份证顺序码是996 997 998 999，这些是为百岁以上老人的特殊编码 
  if (array_search(substr($card, 12, 3), array('996', '997', '998', '999')) !== false) { 
      $card = substr($card, 0, 6) . '18' . substr($card, 6, 9); 
  } else { 
      $card = substr($card, 0, 6) . '19' . substr($card, 6, 9); 
  } 
  $card = $card . _getVerifyNum($card); 
  return $card; 
} 

/**
* 计算身份证校验码，根据国家标准gb 11643-1999 
* @param string $cardBase 身份证号码
* @return string
*/
function _getVerifyNum($cardBase) { 
    if (strlen($cardBase) != 17) { 
        return false; 
    } 
    // 加权因子 
    $factor = array(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2); 

    // 校验码对应值 
    $verify_number_list = array('1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'); 

    $checksum = 0; 
    for ($i = 0; $i < strlen($cardBase); $i++) { 
        $checksum += substr($cardBase, $i, 1) * $factor[$i]; 
    } 

    $mod = $checksum % 11; 
    $verify_number = $verify_number_list[$mod]; 

    return $verify_number; 
}

/**
* 验证是否日期时间
* @param string $datetime 待验证的时间日期，格式为：yyyy-MM-dd HH:ii:ss
* @return bool
*/
function is_datetime($datetime){
  if(empty($datetime)){
    return false;
  }
  $arr = explode(' ',$datetime);
  if(!is_date($arr[0])){
    return false;
  }
  if(isset($arr[1]) && !is_time($arr[1])){
    return false;
  }
  return true;
}

/**
* 验证是否合法的日期
* @param string $date 日期，如：2015-01-16
* @return bool
*/
function is_date($date){
  if(empty($date)){
    return false;
  }
  $arr = explode('-',$date,3);
  if($arr<3){
    return false;
  }
  list($year,$month,$day) = $arr;
  if($year<1970 || $year>date('Y')){
    return false;
  }
  if($month<1 || $month>12){
    return false;
  }
  $days = [31,date('Y')%4==0 ? 29 : 28,31,30,31,30,31,31,30,31,30,31];
  if($day<1 || $day>$days[$days[date('m')-1]]){
    return false;
  }
  return true;
}

/**
* 验证是否合法的时间
* @param string $time 时间，如：17:00:00
* @return bool
*/
function is_time($time){
  if(empty($time)){
    return false;
  }
  $arr = explode(':',$time);
  if($arr<3){
    return false;
  }
  list($h,$i,$m) = $arr;
  if($h<0 || $h>23)return false;
  if($i<0 || $i>59)return false;
  if($m<0 || $m>59)return false;
  return true;
}

/**
* 将字符串用逗号分隔成数组
* @param string $string 待转换的数组
* @param string $tag 要分隔的符号
* @return array
*/
function string2array($string,$tag=','){
  if(empty($string)) return [];
  return !is_array($string) ? explode($tag,$string) : $string;
}

/**
* 将数组转换成查询参数
* @param array $array
* @return string
*/
function array2query($array){
  if(empty($array)){
    return '';
  }
  if(!is_array($array) && is_string($array)){
    return $array;
  }elseif(!is_array($array)){
    error('Parameter[1] must be an array,'.gettype($array).' give');
  }
  $query = [];
  foreach($array as $key=>$val){
    $query[] = "{$key}=".urlencode($val);
  }
  return join('&',$query);
}

/**
 * 获取客户端IP地址
 * @param integer $type 返回类型 0返回IP地址 1返回IPV4地址数字
 * @return mixed
 */
function get_client_ip($type = 0) {
  $type       =  $type ? 1 : 0;
  static $ip  =   NULL;
  if ($ip !== NULL) return $ip[$type];
  if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
      $pos = array_search('unknown',$arr);
      if(false !== $pos) unset($arr[$pos]);
      $ip = trim($arr[0]);
  }elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
      $ip = $_SERVER['HTTP_CLIENT_IP'];
  }elseif (isset($_SERVER['REMOTE_ADDR'])) {
      $ip = $_SERVER['REMOTE_ADDR'];
  }
  // IP地址合法验证
  $long = sprintf("%u",ip2long($ip));
  $ip   = $long ? array($ip, $long) : array('0.0.0.0', 0);
  return $ip[$type];
}

/**
 * 递归将对象变成数组
 * @param objecy $objdct          
 * @return array
 */
function get_object_vars_deep($obj){
  if(is_object($obj))
  {
    $obj = get_object_vars($obj);
  }
  if(is_array($obj))
  {
    foreach ($obj as $key => $value)
    {
      $obj[$key] = get_object_vars_deep($value);
    }
  }
  return $obj;
}

/**
* 判断是否合法的IP地址
* @param string $ip 待判断的字符串
* @return bool
*/
function is_ip($ip){
  $long = sprintf("%u",ip2long($ip));
  return !empty($long) && is_numeric($long) ? $ip : false;
}

/**
* 判断给定的值是否不为空字符串
* @param string $val
* @return bool
*/
function not_empty($val){
  return $val==='' || $val===null ? false : $val;
}

/**
* 将时间戳转换成日期时间格式
* @param timestamp $timestamp
* @param String $format
* @return String
*/
function datetime($timestamp,$format='Y-m-d H:i:s'){
  if(empty($timestamp)) return '';
  return date($format,$timestamp);
}

/**
* 检查并返回全数字的数组，如果是参数是字符串，则先用逗号分隔成成数组
* @param Mix $arr
* @return Array
*/
function is_num_array($arr){
  if(empty($arr)) return [];
  if(!is_array($arr)){
    $arr = explode(',',$arr);
  }
  foreach($arr as $item){
    if(!is_numeric($item)){
      return false;
    }
  }
  return $arr;
}

/**
* 检查是否正整数
* @param numeric $num
* @return bool
*/
function is_absint($num){
  $int = longint($num);
  return $int == $num && $int>=0;
}

/**
* 将数字转换成2位数的金钱
* @param numeric $num
* @return float
*/
function price($num){
  return sprintf('%0.2f',$num);
}

/**
* 添加一个异步任务
* @param string $task_name 任务名称
* @param arrau $task_data 任务数据
* @return bool
*/
/**
 * task_mark1 添加一个异步任务
 */
function add_task($task_name,$task_data){
  global $php;
  // 在用户自定义的进程里，是无法调用异步任务
  $data = gzcompress(json_encode(['name'=>$task_name,'data'=>$task_data]),5);
  return $php->serv->task($data);
}

/**
* 判断参数是否字符串的 true,false，并返回该字符串
* @param mix $bool
* @return bool
*/
function _is_bool($bool){
  return ($bool == 'true' || $bool == 'false') ? $bool : false;
}

/**
* 将参数转换成bool类型
* @param mix $val
* @return bool
*/
function to_bool($val){
  return $val == 'true' ? true : false;
}

/**
* 将布尔值转换成0或1
* @param string $strbool
* @return int
*/
function bool2int($strbool){
  switch(strtolower($strbool)){
    case 'true':
    case true:
      return 1;
      break;
    case 'false':
    case false:
      return 0;
      break;
    default:
      return 0;
      break;
  }
}

/**
* 返回带域名的链接
* @param string $path
* @return string
*/
function U($path){
  return site_url().$path;
}

/**
* 根据数组或对象的下标取值，不存在则返回默认
* @param mix $obj
* @return string
*/
function val(&$obj,$key,$default=''){
  if(is_array($obj)){
    return isset($obj[$key]) ? $obj[$key] : $default;
  }elseif(is_object($obj)){
    return isset($obj->$key) ? $obj->$key : $default;
  }
  return $default;
}

/**
* 检查所有入参是否不为空值
* @param mix
* @return bool
*/
function check_not_empty(){
  $argvs = func_get_args();
  if(empty($argvs)){
    return true;
  }
  foreach ($argvs as $key => $argv) {
    if(empty($argv)) return false;
  }
  return true;
}

/**
* 发送404状态码
* @param string $title
* @param string $content
* @return void
*/
function http404(){
  global $php;
  $php->http404();
}

/**
* 返回304状态码
* @param array $params 传入参数，['last_modified_time'=>'最后修改时间戳','etag'=>'文件唯一标识','expires'=>'有效期 单位秒']
* @return void
*/
function http304($params){
  global $php;
  return $php->http304($params);
}

/**
* 发送自定义状态码  
* @param string $title
* @param string $content
* @return void
*/
function http_status($title,$content,$code=502){
  global $php;
  $php->error($title,$content,$code);
}

/**
* 启动多线程处理任务
* @param int $process_num 线程数量
* @param function $callback 处理函数
* @param bool $pipe 是否启用管道，如果启用，在回调函数中一定要调用$worker->write()或echo向管道写数据，否则会导致read方法一直阻塞
* @param bool $blocking  是否阻塞等待，默认为true
* @return void
*/
function start_process($process_num,$callback,$pipe=false,$blocking =true){
  global $php;
  if(empty($process_num)){
    return false;
  }
  try{
    $php->has_process = true;
    // 启动线程
    $procs = [];
    $echo = [];
    for($i=0;$i<$process_num;$i++){
      $process = new \swoole_process(function($worker) use($callback,$i,$pipe){
        try{
          $worker->name('php-process '.APP_NAME);
          $callback($worker,$i);
        }catch(\Exception $e){
          if($pipe){
            $worker->write($e->getMessage() ? $e->getMessage() : 'error');
          }
        }
      },$pipe,$pipe);
      $pid = $process->start();
      $procs[$pid] = $process;
    }
    foreach ($procs as $pid => $proc) {
      $pipe && $echo[] = $proc->read(65536);
      \swoole_process::wait($blocking);
      unset($procs[$pid]);
    }
    if($pipe){
      return $echo;
    }
  }finally{
    $php->has_process = false;
  }
}

/**
* 检测字符串长度
* @param string $var
* @param int $min
* @param int $max
* @param string $charset
* @return mix
*/
function check_length($str,$min,$max,$charset='utf-8'){
  $length = mb_strlen($str,$charset);
  if($length>=$min&&$length<=$max){
    return $str;
  }
  return false;
}

/**
* 向mongodb写日志
* @param string $msg 日志消息
* @param string $collection 自定义要写到哪个集合  默认为 debug_log
* @return bool
*/
function m_log($msg,$collection=''){
  try{
    $collection || $collection = 'debug_log';
    $data = [
      'datetime' => date('Y-m-d H:i:s'),
      'info' => $msg,
    ];
    return mongodb($collection)->insert($data);
  }catch(\Exception $e){
    return false;
  }
}

/**
* 向MONGODB写错误日志
* @param string $msg 日志消息
* @param string $collection 自定义要写到哪个集合  默认为 debug_log
* @return bool
*/
function em_log($msg,$collection=''){
  try{
    $collection || $collection = 'error_log';
    $data = [
      'datetime' => date('Y-m-d H:i:s'),
      'info' => $msg,
    ];
    return mongodb($collection)->insert($data);
  }catch(\Exception $e){
    return false;
  }
}

/**
* 获取本服务器外网IP 只能获取有一个内网地址和外网地址，或只有一个IP地址的情况。多个外网地址默认只能获取第一个
* @return string
*/
function get_internet_ip(){
  $ips = swoole_get_local_ip();
  if(empty($ips)){
    return '';
  }
  $ips = array_values($ips);
  if(count($ips)==1){
    return $ips[0];
  }
  foreach($ips as $ip){
    if(!preg_match('/^(?:10\.|192\.|172\.)[\.0-9]{1,3}/',$ip)){
      return $ip;
    }
  }
  return '';
}

/**
* 创建一张内存表
* @param string $table_name
* @param string $size
* @param array $column 表字段，如 ['字段名'=>['数据类型','长度']]
*                      数据类型只支持：INT、STRING、FLOAT 三种，TYPE_INT的长度只能是1/2/4/8,TYPE_FLOAT可不指定长度
* @return table 一个内存表对象，支持的方法有：
*         get($key):获取一行数据
*         set(string $key, array $array) : 设置行的数据
*         incr(string $key, string $column, mixed $incrby = 1) : 原子自增操作
*         decr(string $key, string $column, mixed $decrby = 1) : 原子自减操作
*         exist(string $key) : 检查table中是否存在某一个key
*         del(string $key) : 删除数据
*         lock() : 锁定整个表
*         unlock() : 释放锁
*/
function create_memory_table($table_name,$size,$column){
  global $php;
  if(empty($table_name)){
    error('table_name cannot be empty',0000);
  }
  if(empty($size)){
    error('size cannot be empty',0001);
  }
  if(empty($column) || !is_array($column)){
    error('column cannot be empty,and must to be an arrays,eg:["id"=>"int"]');
  }
  if(empty($php->$table_name)){
    $php->$table_name = new \swoole_table($size);
    foreach($column as $field=>$info){
      switch(strtolower($info[0])){
        case 'int':
          $type = \swoole_table::TYPE_INT;
        break;
        case 'string':
          $type = \swoole_table::TYPE_STRING;
        break;
        case 'float':
          $type = \swoole_table::TYPE_FLOAT;
        break;
        default:
          $type = \swoole_table::TYPE_STRING;
        break;
      }
      $php->$table_name->column($field,$type,$info[1]);
    }
    $php->$table_name->create();
  }
  return $php->$table_name;
}

/**
* 创建一个内存缓冲区
* @param string $name 缓冲区名称
* @param int $size 缓冲区大小 单位Byte
* @return buffer对象 支持的方法有：
*         append(string $data) : 将一个字符串数据追加到缓存区末尾
*         substr(int $offset, int $length = -1, bool $remove = false) : 从缓冲区中取出内容
*         clear() : 清理缓存区数据,执行此操作后，缓存区将重置,buffer对象就可以用来处理新的请求了
*         expand(int $new_size) : 为缓存区扩容,新的缓冲区尺寸，必须大于当前的尺寸
*         write(int $offset, string $data) : 向缓存区的任意内存位置写数据。此函数可以直接写内存。所以使用务必要谨慎，否则可能会破坏现有数据
*/
function create_buffer($name,$size){
  global $php;
  if(empty($size)){
    error('buffer size must be greater than 0');
  }
  if(empty($php->$name)){
    $php->$name = new \swoole_buffer($size);
  }
  return $php->$name;
}

/**
* 康盛创想的加密解密函数
* @access public
* @param string $string 明文 或 密文  
* @param string $operation DECODE表示解密,其它表示加密 
* @param string $key 密匙
* @param string $expiry 密文有效期
* @return string
*/
function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {   
    // 动态密匙长度，相同的明文会生成不同密文就是依靠动态密匙   
    $ckey_length = 4;   
      
    // 密匙   
    $key = md5($key ? $key : (defined('ADM_ENCRYPT_KEY') ? ADM_ENCRYPT_KEY : 'G2sfRGw9gYAzCKXLf'));   
       
    // 密匙a会参与加解密   
    $keya = md5(substr($key, 0, 16));   
    // 密匙b会用来做数据完整性验证   
    $keyb = md5(substr($key, 16, 16));   
    // 密匙c用于变化生成的密文   
    $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length):substr(md5(microtime()), -$ckey_length)) : '';   
    // 参与运算的密匙   
    $cryptkey = $keya.md5($keya.$keyc);   
    $key_length = strlen($cryptkey);   
    // 明文，前10位用来保存时间戳，解密时验证数据有效性，10到26位用来保存$keyb(密匙b)，解密时会通过这个密匙验证数据完整性   
    // 如果是解码的话，会从第$ckey_length位开始，因为密文前$ckey_length位保存 动态密匙，以保证解密正确   
    $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) :sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;   
    $string_length = strlen($string);   
    $result = '';   
    $box = range(0, 255);   
    $rndkey = array();   
    // 产生密匙簿   
    for($i = 0; $i <= 255; $i++) {   
        $rndkey[$i] = ord($cryptkey[$i % $key_length]);   
    }   
    // 用固定的算法，打乱密匙簿，增加随机性，好像很复杂，实际上对并不会增加密文的强度   
    for($j = $i = 0; $i < 256; $i++) {   
        $j = ($j + $box[$i] + $rndkey[$i]) % 256;   
        $tmp = $box[$i];   
        $box[$i] = $box[$j];   
        $box[$j] = $tmp;   
    }   
    // 核心加解密部分   
    for($a = $j = $i = 0; $i < $string_length; $i++) {   
        $a = ($a + 1) % 256;   
        $j = ($j + $box[$a]) % 256;   
        $tmp = $box[$a];   
        $box[$a] = $box[$j];   
        $box[$j] = $tmp;   
        // 从密匙簿得出密匙进行异或，再转成字符   
        $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));   
    }   
    if($operation == 'DECODE') {   
        // substr($result, 0, 10) == 0 验证数据有效性   
        // substr($result, 0, 10) - time() > 0 验证数据有效性   
        // substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16) 验证数据完整性   
        // 验证数据有效性，请看未加密明文的格式   
        if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {  
            return substr($result, 26);   
        } else {   
            return '';   
        }   
    } else {   
        // 把动态密匙保存在密文里，这也是为什么同样的明文，生产不同密文后能解密的原因   
        // 因为加密后的密文可能是一些特殊字符，复制过程可能会丢失，所以用base64编码   
        return $keyc.str_replace('=', '', base64_encode($result));   
    }   
}

/**
* 提取一维或多维数组里面的某些字段
* @param array $array
* @param mix $fields
* @return void
*/
function get_array_field($array,$fields){
  if(empty($array) || !is_array($array)){
    return [];
  }
  if(!is_array($fields)){
    $fields = string2array($fields);
  }
  $ret = [];
  array_walk_recursive($array, function($v,$k) use(&$ret,$fields){
    if(count($fields)==1){
      if(in_array($k, $fields)){
        $ret[] = $v;
      }
    }elseif(in_array($k, $fields)){
      $ret[$k][] = $v;
    }
  });
  return $ret;
}

/**
* 将数组重新组合成以某字段为索引的新数组
* @param string $field
* @param array $array
* @return array
*/
function rebuild_array_by($field,$array){
  $new_arr = [];
  foreach ($array as $item) {
    $new_arr[$item[$field]] = $item;
  }
  return $new_arr;
}

/**
* 生成二维码
* @param string $text 文字
* @param int $size 1-9
* @return void
*/
function create_qrcode($text,$size=3){
  if(!class_exists('QRcode')){
    include LIB_PATH.'Qrcode/phpqrcode.php';
  }
  return \QRcode::png($text,false,0,$size,1);
}

/**
* 生成临时文件名
* @param string $path 保存路径
* @param string $prefix 前缀
* @param string $suffix 后缀
* @return string
*/
function temp_file_name($path,$prefix='',$suffix=''){
  $uniqid = md5(uniqid(microtime().rand(1,99999999),true));
  $file = rtrim($path,'/').'/'.$prefix.$uniqid;
  if(!empty($suffix)){
    $file .= '.'.$suffix;
  }
  return $file;
}

/**
* 从链接里获得查询参数并转换成数组
* @param string $url
* @return array
*/
function get_url_query($url){
  if(!is_url($url)){
    return [];
  }
  $url_info = parse_url($url);
  if(!isset($url_info['query']) || empty($url_info['query'])){
    return [];
  }
  $query_array = [];
  parse_str($url_info['query'],$query_array);
  return $query_array;
}

/**
* 修改URL查询参数并返回URL
* @param string $url
* @param array $params
* @return string
*/
function build_url($url,$params=[]){
  if(empty($url) || !is_url($url)){
    return '';
  }
  if(!is_array($params)){
    _exit('Params must be an array');
  }
  $url_info = parse_url($url);
  parse_str(val($url_info,'query',''),$query_info);
  $query_info = array_merge((array)$query_info,$params);
  $query_info = array_filter($query_info,function($v){return !empty($v);});
  return $url_info['scheme'].'://'.$url_info['host'].$url_info['path'].($query_info ? '?'.array2query($query_info) : '');
}

