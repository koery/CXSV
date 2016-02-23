<?php
/*
Auth:Sang
Desc:日志类
Date:2014-11-01
*/
namespace Lib;

abstract class Log{

    protected $level_line;
    const TRACE   = 0;
    const INFO    = 1;
    const NOTICE  = 2;
    const WARN    = 3;
    const WRONG   = 3;
    const ERROR   = 4;

    protected static $level_code = array(
        'TRACE' => 0,
        'INFO' => 1,
        'NOTICE' => 2,
        'WARN' => 3,
        'WRONG' => 3,
        'ERROR' => 4,
    );

    public static $level_str = array(
        'TRACE',
        'INFO',
        'NOTICE',
        'WARN',
        'WRONG',
        'ERROR',
    );

    static $date_format = '[Y-m-d H:i:s]';

    static function convert($level){
        if (!is_numeric($level)){
            $level = self::$level_code[strtoupper($level)];
        }
        return $level;
    }

    function __call($func, $param){
        $this->put($param[0], $func);
    }

    function format($msg, $level){
        $level = self::convert($level);
        $level_str = self::$level_str[$level];
        return date(self::$date_format)." {$level_str}: {$msg}\n";
    }

    abstract function put($msg, $level = self::INFO);
}