<?php
/*
Auth:Sang
Desc:应用服务器类
Date:2014-11-01
*/
namespace Lib;

class AppServer extends HttpServer{
    private $auto_load = array('cache','session','db','storage','mongodb','redis');
    private $in_request = false;
    public function __construct($config){
        parent::__construct($config);
        Loader::setRootNS('App',DOCUMENT_ROOT);
        Loader::setRootNS('Act',APP_PATH.'Act/');
        Loader::setRootNS('Widget',WIDGET_PATH);
        Loader::setRootNS('Task',DOCUMENT_ROOT.'/Task/');
        Loader::setRootNS('Cron',CRON_PATH);
        Loader::setRootNS('AppMod',APP_PATH.'Mod/');
        Loader::setRootNS('Plugin',APP_PATH.'Plugin/');
        $_SERVER['DOCUMENT_ROOT'] = DOCUMENT_ROOT;
        if(!empty($this->c('app.charset'))){
            $this->charset = $this->c('app.charset');
        }
        \Lib\Error::$echo_html = true;
        set_error_handler(array($this,'errorHandler'));
    }

    public function __get($name){
        if(empty($this->$name) && in_array($name,$this->auto_load)){
            $class = '\\Lib\\'.ucwords($name);
            $this->$name = new $class(isset($this->config[$name]) ? $this->config[$name] : array());
        }else{
            $this->$name = null;
        }
        return $this->$name;
    }

    public function __set($name,$val){
        $this->$name = $val;
    }
    public function __isset($name){
        return isset($this->$name);
    }
	public function start($serv){
        try{
            $this->in_request = true;
            $path_info = Common::getActionUrl();
            $request_methods = array('get'=>'doGet','post'=>'doPost','add'=>'doAdd','update'=>'doUpdate','put'=>'doUpdate','delete'=>'doDelete');
            $method = 'get';
            $path_info = trim($path_info,'/');
            $reqs = explode('/',$path_info);
            if(!empty($path_info) && count($reqs)==1){
                $act = $reqs[0];
                if(is_post()){
                    $method = 'post';
                }elseif(is_put()){
                    $method = 'put';
                }elseif(is_delete()){
                    $method = 'delete';
                }
            }elseif(count($reqs)>1){
                $m = array_pop($reqs);
                if(in_array($m,array_keys($request_methods))){
                    $method = $m;
                }else{
                    $reqs[] = $m;
                    if(is_post()){
                        $method = 'post';
                    }elseif(is_put()){
                        $method = 'put';
                    }elseif(is_delete()){
                        $method = 'delete';
                    }
                }
                array_walk($reqs,function(&$v,$k){$v = ucwords($v);});
                $act = join('\\',$reqs);
            }else{
                $act = 'index';
            }

            $act = ucwords($act);
            $act_class = '\\Act\\'.$act;
            $act_file = APP_PATH.'Act/'.str_replace('\\','/',$act).'.php';
            if(!in_array($method,array_keys($request_methods)) || !is_file($act_file) || !class_exists($act_class)){
            	return $this->http404();
            }
            ob_start();
            apply_action('app_start',$act_file);
            $this->session->start();
            $act_obj = new $act_class($this,$method);
            $handler = $request_methods[$method];
            $data = $act_obj->$handler();
            $data_type = gettype($data);
            if($data_type=='array' || $data_type == 'object'){
                $data = var_export($data,true);
            }
            if(($obcache = ob_get_contents())!==false){
                $data = $obcache . $data;
                ob_end_clean();
            }
            apply_action('on_response',$data);
            $this->response($data);
        }catch(\Exception $e){
            // 因为start方法开启了缓冲区，这里要关闭它。否则会导致日志不输出
            if(($data = ob_get_contents())!==false){
                ob_end_clean();
            }
            $error_msg = $e->getMessage();

            $code = $e->getCode();

            // 调用_die()函数时退出
            if($code == PHP_INT_MAX){
                $this->response('');
                return;
            }
            // 当错误代码为0时，表示是人为抛出错误，此时模仿PHP原生EXIT函数，退出并输出信息  调用_exit($msg)函数时产生
            if($code==0){
                $this->response($data.$error_msg);
                return;
            }else{
                $info = 'Code:'.$code.' '.$error_msg;
                $file = str_replace('.php','',$e->getFile());
                $more = ' in '.str_replace([SHM_PATH,APP_PATH,FRAME_ROOT],'',$file).' line:'.$e->getLine().\Lib\Error::trace();
                em_log('Runtime Exception : '.$info.$more.' . [URL]:'.cur_url());
                if(defined('DEBUG')){
                    // 当错误代码大于1时，输出出错的文件和行号，否则只输出出错信息
                    if($e->getCode()>1){
                        $info .= $more;
                    }
                    $error = \Lib\Error::info('Runtime Exception:',$info,$code>1?$code:500);
                }else{
                    $error = \Lib\Error::info('','页面发生错误',500);
                }
            }
            $this->response($error,500);
        }finally{
            $this->in_request = false;
            unset($data,$act_obj);
            apply_action('app_end',$act,$method);
            //自动保存session
            $this->session->save();
            //当内存占用率过大时，自动退出重启
            $memory_used = memory_get_usage();
            if($memory_used/1024/1024 > 100){
                em_log('memory_used soo large! worker process exited! memory_used:'.$memory_used);
                exit;
            }
        }
	}

    public function errorHandler($errno, $errstr, $errfile, $errline){
        $errfile = str_replace('.php','',$errfile);
        $errorMsg = "{$errstr} (".str_replace(array(SHM_PATH.'tpls/',SHM_PATH.'widget/tpl/',APP_PATH,FRAME_ROOT,DOCUMENT_ROOT),array('tpl:','widget_tpl:','app:','root:','app:'),$errfile).":{$errline})";
        em_log('Runtime Error : '.$errorMsg.\Lib\Error::trace().' . [URL]:'.cur_url());
        // 如果错误级别为E_NOTICE，则不退出，只记录到日志
        if($errno == E_NOTICE){
            return;
        }
        if(ob_get_contents() !== false){
            ob_end_clean();
        }
        if(defined('DEBUG')){
            $this->response(\Lib\Error::info("Runtime Error", $errorMsg),200);
        }else{
            $this->response(\Lib\Error::info("", '页面发生错误'),200);
        }
        if(cur_url() && $this->in_request){
            _die();
        }
    }
}