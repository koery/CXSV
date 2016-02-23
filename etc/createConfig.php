<?php
/**
* @Auth Sang
* @Desc 创建应用配置文件   用法：php createConfig.php 应用名称 [端口]
* @Date 2014-12-21
*/

$src_config_file = './swoole.ini';
$app_name = $argv[1];
$port = isset($argv[2]) ? $argv[2] : 9501;
$desc_config_file = './'.$app_name.'.ini';
$content = file_get_contents($src_config_file);
$content = str_replace(['{app_name}','{port}'],[$app_name,$port],$content);

file_put_contents($desc_config_file,$content);

echo 'create a new app config file at '.$app_name.'.ini'.PHP_EOL;