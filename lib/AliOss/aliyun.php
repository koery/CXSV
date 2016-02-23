<?php
/*
Auth:Sang
Desc:阿里云OSS访问封装
Date:2014-11-01
*/
require_once __DIR__.'/libs/symfony/class-loader/Symfony/Component/ClassLoader/UniversalClassLoader.php';

use Symfony\Component\ClassLoader\UniversalClassLoader;

$loader = new UniversalClassLoader();

$loader->registerNamespaces(array(
    'Guzzle\\Common' => __DIR__.'/libs/guzzle/common',
    'Guzzle\\Parser' => __DIR__.'/libs/guzzle/parser',
    'Guzzle\\Plugin' => __DIR__.'/libs/guzzle/plugin',
    'Guzzle\\Stream' => __DIR__.'/libs/guzzle/stream',
    'Guzzle\\Http' => __DIR__.'/libs/guzzle/http',
    'Symfony\\Component\\EventDispatcher' => __DIR__.'/libs/symfony/event-dispatcher',
    'Symfony\\Component\\ClassLoader' => __DIR__.'/libs/symfony/class-loader',
    'Aliyun' => __DIR__.'/src',
));

$loader->register();
