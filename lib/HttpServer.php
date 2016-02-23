<?php
/*
 用PHP实现的服务器，依赖swoole扩展
 直接做为裸露服务器可能会出现某些无法预料的问题
 可以在前面加一级nginx，以实现类似于nginx+php-fpm的功能
 性能上比nginx+php-fpm强大一些。
*/
 namespace Lib;
 use \Lib\EchoLog;
class HttpServer{
    // 配置
    public $config;
    // 请求回调函数
    protected $_onRequest;
    // \swoole_server
    public $serv;
    // 记录日志类
    protected $loger;
    // 插件
    public $plugins = [];

    // response对象
    public $rs;

    // 当前连接号
    public $fd;

    /**
    * 是否启用了子进程，如果启用，则在shutdown_function处理函数中，要忽略向客户端输出，
    * 否则子进程退出，会调用shutdown_function函数向客户端输出数据，然后关闭链接，导致后面的正常输出被忽略
    */
    public $has_process = false;

    public function __construct($config){
        $this->config = $config;
        $this->loger = new EchoLog('');
        register_shutdown_function(array($this, 'handleFatal'));
    }

    /**
    * 运行服务器
    * @access public
    * @return void
    */    
    public function run(){
        $swcfg = array_merge(
            [
                'log_file' => '/dev/null',
                'worker_num' => 8,
                'max_request' => 100000,
                'max_conn' => 10000,
                'daemonize' => 0,
            ],$this->c('global'));
       
        $server = new \swoole_http_server($this->c('server.host'), $this->c('server.port'));
        $this->serv = $server;
        $server->set($swcfg);
        $this->config = array_merge($this->config,$server->setting);
        $server->on('Start',array($this,'onStart'));
        $server->on('ManagerStart', array($this,'onManagerStart'));
        $server->on('ManagerStop', array($this,'onManagerStop'));
        $server->on('WorkerStart',array($this,'onWorkerStart'));
        $server->on('Request', array($this, 'onRequest'));
        $server->on('Close', array($this, 'onClose'));
        $server->on('Shutdown', array($this, 'onShutdown'));
        /**
         * task_mark2 调用OnTask 执行任务
         */
        $server->on('Task', array($this, 'onTask'));
        $server->on('Finish', array($this, 'onFinish'));
        $server->on('WorkerStop',[$this,'onWorkerStop']);
        $server->on('WorkerError',[$this,'onWorkerError']);
        // $server->on('Timeout',[$this,'onTimeout']);
        $server->start();
    }

    /**
    * 主进程启动时回调函数
    * @access public
    * @param \swoole_server $serv
    * @return void
    */
    public function onMasterStart($serv){
        $this->log(SERVER_NAME."[#master] start");
    }

    /**
    * 管理进程启动时回调函数
    * @access public
    * @param \swoole_server $serv
    * @return void
    */
    public function onManagerStart($serv){
        $this->setProcessName('php-manager: '.APP_NAME.' ('.__DIR__.'/etc/'.APP_NAME.'.ini)');
        $this->log(SERVER_NAME."[#manager] start");
    }

    /**
    * 管理进程结束时回调函数
    * @access public
    * @param \swoole_server $serv
    * @return void
    */
    public function onManagerStop($serv){
        $this->log(SERVER_NAME."[#manager] stop");
    }

    /**
    * 服务器关闭时回调函数
    * @access public
    * @param \swoole_server $serv
    * @return void
    */
    public function onShutdown($serv){
        exec('rm -rf '.SHM_PATH);
        $this->log(SERVER_NAME." shutdown");
        apply_action('on_shutdown',$this,$serv);
    }

    /**
    * 服务器启动时回调函数
    * @access public
    * @param \swoole_server $serv
    * @return void
    */
    public function onStart($serv){
        $this->setProcessName('php-master: ' . APP_NAME . ' host=' . $this->config['server']['host'] . ' port=' . $this->config['server']['port']);
        apply_action('server_start',$serv);
    }

    /**
    * work进程启动时回调函数
    * @access public
    * @param \swoole_server $server
    * @param int $worker_id
    * @return void
    */
    public function onWorkerStart($server,$worker_id){
    	
    	/**
    	 * plugin_mark1 work进程启动 加载全局插件
    	 */
        $this->loadPlugin();
        if ($worker_id >= $this->c('global.worker_num')){
            $this->setProcessName('php-task: '.APP_NAME.' #'.$worker_id);
            $this->log("php-task[#{$worker_id}] running on ".$this->c('server.host').":".$this->c('server.port'));
        }else{
            $this->worker_id = $worker_id;
            $this->setProcessName('php-worker: '.APP_NAME.' #'.$worker_id);
            $this->log("php-worker[#{$worker_id}] running on ".$this->c('server.host').":".$this->c('server.port'));
            apply_action('on_worker_start',$server,$worker_id);
        }
    }

    /**
    * work进程退出时执行
    * @access public
    * @param string $var
    * @return void
    */
    public function onWorkerStop($serv,$worker_id){
        $this->log("php-worker[#{$worker_id}] Stoped");
        exec('rm -rf '.SHM_PATH);
        while(\swoole_process::wait());
    }

    /**
    * 当work遇到错误时
    * @access public
    * @param swoole_server $serv
    * @return void
    */
    public function onWorkerError($serv,$worker_id,$worker_pid,$exit_code){
        em_log("php-worker[#{$worker_id}] Exit! pid:{$worker_pid} code:{$exit_code}, app_name:".APP_NAME.",traces: ".\Lib\Error::trace());
        $this->log("php-worker[#{$worker_id}] Exit! code:{$exit_code}");
    }

    /**
    * 异步任务回调函数
    * @access public
    * @param \swoole_server $serv
    * @param int $task_id
    * @param int $from_id
    * @param string $data
    * @return void
    */
    /**
     * task_mark3 执行任务
     */
    public function onTask($serv, $task_id, $from_id, $data){
        try{
            $task_data = @json_decode(gzuncompress($data),true);
            if(empty($task_data)){
                return;
            }
            $task_name = $task_data['name'];
            $data = $task_data['data'];
            $params = '';
            $task = '\\Task\\'.ucwords($task_name);//task_mark4寻任务类名执行
            $task::run($data);
        }catch(\Exception $e){
            $this->log($e->getMessage());
        }
        // $serv->finish();
    }

    /**
    * 异步任务结束时回调函数
    * @access public
    * @param \swoole_server $var
    * @param int $task_id
    * @param string $data
    * @return void
    */
    public function onFinish($serv, $task_id, $data){
        echo 'task data:'.$data;
    }

    /**
    * 请求处理函数
    * @access public
    * @param swoole_request $rq
    * @param swoole_response $rs
    * @return void
    */
    public function onRequest(\swoole_http_request $rq,\swoole_http_response $rs){
        $this->rs = $rs;
        $_GET = $_POST = $_FILES = $_COOKIE = $_SERVER = $_REQUEST = $GLOBALS = []; //对每一个请求 初始化全局$_GET $_POST... 
        if(!isset($rq->fd) || empty($rq->fd)){
            return false;
        }
        $this->fd = $rq->fd;
        $connection_info = $this->serv->connection_info($rq->fd);
        if($connection_info==false){
            return false;
        }
        try{
        	//获取cookie files get post数据
            isset($rq->cookie) && $_COOKIE = $rq->cookie;
            isset($rq->files) && $_FILES = $rq->files;
            isset($rq->get) && $_GET = $rq->get;
            isset($rq->post) && $_POST = $rq->post;
            
            if($this->isMulFormData(val($rq,'header')) && $this->isArrayPost($_POST)){
                $_POST = $this->post2Array($_POST);
            }
            $_REQUEST = array_merge($_GET,$_POST);
            $GLOBALS['rawContent'] = '';
            $connection_info && $GLOBALS['rawContent'] = $rq->rawContent();
            $header = $server = [];
            if(isset($rq->header)){
                foreach($rq->header as $key=>$val){
                    $header['HTTP_'.strtoupper(str_replace('-','_',$key))] = $val;
                }
            }
            if(isset($rq->server)){
                $server = array_change_key_case($rq->server,CASE_UPPER);
            }
            $_SERVER = array_merge($header,$server);
            unset($server,$header);
            $_SERVER['REMOTE_ADDR'] = val($_SERVER,'HTTP_X_FORWARDED_FOR',$_SERVER['REMOTE_ADDR']);
            $_SERVER['SERVER_SOFTWARE'] = SERVER_NAME;
            if(defined('DEBUG')){
                $query_str = val($_SERVER,'QUERY_STRING');
                $query_str && $query_str = '?'.$query_str;
                $this->log("new request from [#{$rs->fd}]:\"".$_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI'].$query_str);
            }
            apply_action('on_request');
            /**
             * call_user_func 參數不匹配不會影響函數的調用
             * func_get_args() 動態獲取參數
             */
            call_user_func($this->_onRequest,$rq,$rs);
        }catch(\Expectation $e){
            $rs->status(400);
            $rs->end($e->getMessage());
        }finally{
            $this->rs = null;
            $this->fs = null;
            $rq = null;
            $rs = null;
        }
    }

    /**
    * 当表单中有文件上传时，POST的数据不能解析name[key]的情况，需要检测，然后手工处理
    * @access private
    * @param array $post_data
    * @return bool
    */
    private function isArrayPost($post_data){
        foreach ($post_data as $key => $value) {
            if(strpos($key, '[')){
                return true;
            }
        }
        return false;
    }

    /**
    * 检测是否表单multipart/form-data提交方式
    * @access private
    * @return bool
    */
    private function isMulFormData($header){
        if(explode(';',val($header,'content-type',''),2)[0]=='multipart/form-data'){
            return true;
        }
        return false;
    }

    /**
    * 当表单中有文件上传时，POST的数据不能解析name[key]的情况，需手工处理
    * @access private
    * @param array $post_data
    * @return array
    */
    private function post2Array($post_data){
        $data = array2query($post_data);
        $ret = [];
        parse_str($data,$ret);
        return $ret;
    }

    /**
    * 处理请求回调函数
    * @access public
    * @param function $callback
    * @return void
    */
    public function setProcReqFun($callback){
        $this->_onRequest = $callback;
    }

    /**
     * 连接关闭时回调函数
     * @param \swoole_server $serv
     * @param int $fd
     * @param int $from_id
     * @return void
     */
    public function onClose($serv, $fd, $from_id){
        $this->clear();
        if(defined('DEBUG')) $this->log("client[#$fd@$from_id] close");
    }

    /**
    * 更改进程名称
    * @access public
    * @param string $name
    * @return void
    */
    public function setProcessName($name){
        swoole_set_process_name($name);
    }

    /**
    * 获得配置项
    * @access public
    * @param string $key
    * @return string
    */
    public function c($key){
        $key = explode('.',$key);
        $val = isset($this->config[$key[0]]) ? $this->config[$key[0]] : '';
        if(isset($key[1])){
            isset($val[$key[1]]) && ($val = $val[$key[1]]) || ($val = '');
        }
        return $val;
    }

    /**
    * 清理
    * @access protected
    * @param int $fd
    * @return void
    */
    protected function clear(){
        $this->rs = null;
        $this->fd = null;
    }


    /**
    * 加载插件
    * @access private
    * @return void
    */
    
    /**
     * plugin_mark2  全局插件加载具体方式
     */
    private function loadPlugin(){
        $paths = array('global'=>FRAME_ROOT.'plugin/','app'=>DOCUMENT_ROOT.'Plugin/');
        foreach($paths as $type => $plugin_path){
            if(!is_dir($plugin_path)){
                continue;
            }
            $odir = opendir($plugin_path);
            while($file = readdir($odir)){
                if($file{0}!='.' && ($path=$plugin_path.$file.'/') && ($plugin_file=$path.$file.'.php') && is_file($plugin_file) && (is_file($path.'enabled') || $type == 'global')){
                    include $plugin_file;
                }
            }
            closedir($odir);
        }
    }

    /**
    * 输入404错误
    * @access public
    * @return void
    */
    public function http404(){
        return $this->error('404 Not Found','你所请求的页面已去了火星，不再回来了~~',404);
    }

    /**
    * 返回302状态
    * @access public
    * @param string $url
    * @return void
    */
    public function http302($url){
        $this->a++;
        set_header('Location',$url);
        set_header('Content-Length',0);
        $this->response('',302);
        //添加这一句，是为了终止程序继续执行
        _die();
    }

    /**
    * 返回304
    * @access public
    * @param array $params 传入参数，['last_modified_time'=>'最后修改时间戳','etag'=>'文件唯一标识','expires'=>'有效期 单位秒']
    * @return void
    */
    public function http304($params=[]){
        $last_modified_time = val($params,'last_modified_time');
        $etag = val($params,'etag');
        $expires = val($params,'expires',0);
        if(!$last_modified_time || !$etag){
            return;
        }
        set_header('Last-Modified',gmdate("D, d M Y H:i:s", $last_modified_time)." GMT");
        set_header('Etag',$etag);
        set_header('Cache-Control','max-age='.$expires);
        if(!empty($expires)){
            $expires && $expires = $last_modified_time+$expires;
            set_header('Expires',gmdate("D, d M Y H:i:s", $expires)." GMT");
        }
        if((isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $_SERVER['HTTP_IF_MODIFIED_SINCE']==$last_modified_time || isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH'])==$etag) && time()<$expires){
            $this->rs->status(304);
            $this->rs->end('');
            return true;
        }
        return false;
    }

    /**
    * 设置发送内容头部
    * @access public
    * @param string $key
    * @param string $value
    * @return void
    */
    public function setHeader($key, $value){
        $this->rs->header($key,$value);
    }

    /**
    * 设置发送内容的cookie
    * @access public
    * @param string $name
    * @param string $value
    * @param int $expires
    * @param string $path
    * @param string $domain
    * @return void
    */
    public function setCookie($name,$value,$expires=0,$path='/',$domain='',$secure = false,$httponly = false){
        $this->rs->cookie($name,$value,$expires ? time()+$expires : 0,$path,$domain,$secure,$httponly);
    }

    /**
    * 发送内容
    * @access public
    * @param \swoole_server $serv
    * @param int $fd
    * @param string $respData
    * @param int $code
    * @return void
    */
    public function response($respData, $code = 200){
        if(empty($this->rs)){
            return false;
        }
        $connection_info = $this->serv->connection_info($this->fd);
        if($connection_info==false){
            return false;
        }
        try{
            $this->c('gzip_level') && $this->rs->gzip($this->c('gzip_level'));
            $this->rs->status($code);
            $this->c('server.keepalive') && $this->setHeader('Connection','keep-alive');
            $strlen = strlen($respData);
            if($strlen > 1024*1024*2){
                $this->setHeader('Content-Length',$strlen);
                $p=0;
                $s=2000000;
                while($data = substr($respData, $p++*$s,$s)){
                    $this->rs->write($data);
                }
                $this->rs->end();
            }else{
                $this->rs->end((string)$respData);
            }
            return true;
        }catch(\Exception $e){
            $this->rs->status(500);
            $rs->end($e->getMessage());
            return true;
        }finally{
            $this->rs = null;
        }
        return true;
    }


    /**
    * 写日志
    * @access public
    * @param string $msg
    * @return void
    */
    public function log($msg,$type='info'){
        if(empty($type) || !in_array(strtoupper($type), \Lib\Log::$level_str)){
            throw new \Exception("Log type must in (".join(',',\Lib\Log::$level_str).")",110);
        }
        $this->loger->$type($msg);
    }

    /**
    * 发送错误信息
    * @access public
    * @param string $msg
    * @param string $content
    * @param int $code
    * @return void
    */
    public function error($msg,$content,$code = 200){
        $SERVER_NAME = SERVER_NAME;
        $str = <<<EOF
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8"/>
        <title>{$msg}</title>
    </head>
    <body>
    <h1>{$msg}</h1><p>{$content}</p><hr>power by {$SERVER_NAME}
    </body>
    </html>
EOF;
        $this->response($str,$code);
        _die();
    }

    /**
     * Fatal Error的捕获
     * @access public
     * @return void
     */
    public function handleFatal(){
        $error = error_get_last();
        $data = '';
        if(($data = ob_get_contents())!==false){
            ob_end_clean();
        }
        /**
        * 如果脚本退出时，检测到has_process为true，说明是子进程退出
        * 此时直接写到日志，而不是向客户端发送数据
        */
        if($this->has_process){
            if(!empty($data) || !empty($error)){
                $msg = $data.$error['message'];
                $errorMsg = "{$error['message']} ({$error['file']}:{$error['line']})";
                global $php;
                $sql = $php->db->getLastSql();
                em_log('child process fatal error : '.$errorMsg.\Lib\Error::trace().($sql ? ' [SQL]:'.$sql : ''));
            }
            return;
        }
        if (empty($error) || !isset($error['type'])){
            $new_data = json_decode($data,true);
            if(gettype($new_data)=='array'){
                $new_data['_worker_info'] = 'worker restarted';
                $data = json_encode($new_data,JSON_UNESCAPED_UNICODE);
            }else{
                $data .= '<font color="red">worker restarted</font>';
            }
            $this->response($data);
            return;
        }else{
            $error['file'] = str_replace('.php','',$error['file']);
            $errorMsg = "{$error['message']} (".str_replace([SHM_PATH.'tpls/',SHM_PATH.'widget/tpl/',APP_PATH,FRAME_ROOT,DOCUMENT_ROOT],['tpl:','widget_tpl:','app:','root:','app:'],$error['file']).":{$error['line']})";
            $message = \Lib\Error::info("Server fatal error", $errorMsg);
            em_log('Server fatal error : '.$errorMsg.\Lib\Error::trace().' URL:'.cur_url());
            if(defined('DEBUG')){
                $this->response($message);
            }else{
                $this->response(\Lib\Error::info("", '页面发生错误'),200);
            }
        }
    }
}
