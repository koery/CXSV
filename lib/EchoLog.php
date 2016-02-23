<?php
/*
Auth:Sang
Desc:日志输出类
Date:2014-11-01
*/
namespace Lib;
class EchoLog extends \Lib\Log{
    protected $display = true;
    function __construct($conf=[]){
        if (isset($conf['display']) and $conf['display'] == false){
            $this->display = false;
        }
    }

    function put($msg, $level = self::INFO){
        if ($this->display){
            $log = $this->format($msg, $level);
            if ($log) echo $log;
        }
    }
}