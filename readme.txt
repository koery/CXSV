框架说明
Cxsv框架是用swoole扩展编写的PHP应用服务框架，运行后以进程形式长驻后台，通过NGINX代理，接受http请求，并调用相应的控制器处理请求。
由于常驻后台，并且没有用到PHP-FPM或APACHE，有一些操作需要注意；
上式上线应用不能用exit或die退出脚本，否则会导致进程重启，取之代之的是_exit($msg)函数
Header()函数将失败，取而代之的是set_header($header_key,$header_value)函数
在函数内使用static声时变量要注意，由于常驻后台（常驻内存）的特性，会导致声明static变量后，该变量也常驻内存，如果需要对该变量进行相加或数组赋值操作，将会造成该变量在内存中一直增长。解决方法是用类，定义一个类，将static变量用类的属性代替。在类中对该属性进行初始化。每次使用时，NEW这个类。
全框架实行命名空间，每定义一个类，都要有命名空间约束，命名空间对应目录如下：
Lib:全局公共类库，对应框架根目录/lib
Mod:全局公共model，对应框架根目录/mod
App: 应用目录
Act:应用目录/Act
Widget:应用根目录(即DOCUMENT_ROOT)/Widget
Task:应用根目录(即DOCUMENT_ROOT)/Task
如要调用应用的Lib目录下的类，只需：use App\Lib\Classname;
自定义公共类库时，也要遵循命名空间约束。例如要定义一个叫 SomeThing的类，要这样做：namespace App\Lib\SomeThing;
框架支持异步任务，只需简单调用 add_task($task_name,$data)即可调用 Task/目录下的任务脚本
框架支持定时器，在应用开启定时器的情况下，将定时脚本写入到应用根目录/Cron下即可。任务脚本的写法和定时器的写法最后有介绍。
 
 
 
 
基础说明
以命名空间为基础
每个Action的命名空间为Act开头，类名为首字母大写的Action名，继承原Action类，如http://www.ABC.com/index 对应的Action为Index
 
每个Action只提供 get , post , add , (put , update) , delete , ajax等6种方法
 
命名方式分别对应为doGet , doPost , doAdd , doUpdate , doDelete , doAjax
 
---------------------------- Action调用方式 ------------------------------
/index/get
/index/post (当请求方式为POST时，可省略为：/index)
/index/add
/index/put
/index/update
/index/delete
/index/ajax
 
当Action为  /index/method 并且method不出在默7种方法里的，对应路径为
/index/method/index  二级目录下
当Action 为 /index/path/ 时，对应路径为 /index/path/index
 
---------------------------- 模块调用方式 ----------------------------
model(“#类名”)  调用公共mod
model(“类名”)   调用应用的mod
model(“类名”,”数据表前缀”)  调用带数据表前缀的mod，例如 model(‘admin’,’adm_’);将操作 adm_admin的表
 
---------------------------- 缓存调用方式 ----------------------------
 
cache(“缓存名”)  获取对应的cache内容
cache(“缓存名”,”设置内容”)   设置对应缓存名的内容
cache(“缓存名”,”设置内容”,过期时间)   设置对应缓存名的内容，过期时间单位为秒
cache(“缓存名”,null)   删除对应缓存的内容
 
---------------------------- Cookie调用方式 -------------------------
 
cookie(“cookie名”)  获取对应的cookie内容
cookie (“cookie名”,”设置内容”)   设置对应cookie的内容
cookie (“cookie名”,”设置内容”,过期时间)   设置对应cookie的内容，过期时间单位为秒
cookie (“cookie名”,null)   删除对应cookie内容
 
 
 
---------------------------- Session调用方式 -------------------------
Session(‘@id’) 返回session_id
session(“session名”)  获取对应的session内容
session (“session名”,”设置内容”)   设置对应session的内容
session (“session名”,”设置内容”,过期时间)   设置对应session的内容，过期时间单位为秒
session (“session名”,null)   删除对应session内容
session (null)   删除所有session内容
 
 
---------------------------- storage储存调用方式 -------------------------
storage(‘@本地文件绝对路径’,[‘path’=>’保存路径’]);  //保存一个本地文件到存储系统
storage(‘文件访问子路径’,’字符串内容’,[‘path’=>’保存路径’]); //保存内容到文件文件系统的‘文件访问子路径’
storage(‘文件访问路径’);   //从存储系统中获取一个文件
storage(‘文件访问路径’,null) //从存储系统中删除一个文件
                  
---------------------------- config配置中心 -------------------------
config(‘键’);  //获取一个配置项
config(‘键’,’值’);   //设置一个配置项
config(‘键’,null);   //删除一个配置项
注：基于memcache和mysql的持久化缓存
 
 
                  
------------------------------- 应用目录结构不分前后台 -------------------------------
当应用为单目录时，即不分前台、用户中心、前台时，目录结构以下：
 
/Act (控制器存放目录)
  ├─ Index.php
  ├─ Test.php
 
/Mod (模块存放目录)
  ├─ Goods.php
 
/Lib (应用公共库目录)
  ├─ Net.php
 
/Plugin (插件目录)
  ├─ onResponse
      ├─ onResponse.php
 
/Widget (挂件目录)
  ├─ Ad
      ├─ Ad.php
 
/Tpl (模板文件目录)
  ├─ default (模板样式)
      ├─ index (对应一个Action，全小写)
          ├─ index.php (模板文，全小写)
          ├─ form.php
/Task
         |-- Task.php
/Cron
         |-- Cron.php
 
                  
 
 
 
------------------------------- 应用目录结构有前后台 -------------------------------
当应用分前台、用户中心、前台时，目录结构以下：
 
/admin
  ├─ Act (Action存放目录)
                     ├─ Index.php
                     ├─ Test.php
├─ Tpl (模板文件目录)
                     ├─ default (模板样式)
                         ├─ index (对应一个Action，全小写)
                             ├─ index.php (模板文，全小写)
                             ├─ form.php
/front
  ├─ Act (Action存放目录)
                     ├─ Index.php
                     ├─ Test.php    
├─ Tpl (模板文件目录)
                     ├─ default (模板样式)
                         ├─ index (对应一个Action，全小写)
                             ├─ index.php (模板文，全小写)
                             ├─ form.php
/Mod (应用模块存放目录)
         ├─ Goods.php
        
/Lib (应用公共库目录)
         ├─ Net.php
 
/Plugin (插件目录)
├─ onResponse
                   ├─ onResponse.php
        
/Widget (挂件目录)
         ├─ Ad
                   ├─ Ad.php
 
/Task
         |-- Task.php
/Cron
         |-- Cron.php
 
 
 
 
----------------------------------- 开发示例：--------------------------------------
$model=model(“类名”);
-----增-------
$result = $model->insert(array('name'=>'value','name2'=>'value2'));
$result = $model->multiInsert(array(array('name'=>'value','name2'=>'value2')),array('name'=>'value','name2'=>'value2'))); //同时插入多条
成功返回插入的ID，失败返回FALSE
 
-----删-------
$result = $model->where($where)->delete();
成功返回受影响行数，失败返回FALSE
 
-----改-------
$result = $model->where($where)->update(array('name'=>'value','name2'=>'value2'));
成功返回true或受影响行数，失败返回FALSE
 
-----查-------
返回数据集（多条记录）
$data=$model->where($where)->limit(offset,resultNumrows)->order(“id desc”)->select(); 
单条记录
$data=$model->where($where)->limit(offset,resultNumrows)->order(“id desc”)->fetch();
 
 
注：where条件为一个sql字符串，如：id=1 或  id>1 and id<1、id!=1 、id in(1,2,3) and title like '%test%'等
 
 
 
 
 
 
 
 
常用公共函数
 
获取$_GET变量的值：
get('变量名','默认值','处理函数，多个之间用逗号分隔');
如：get('foo',0,'intval,abs');处理函数的顺序是从左到右。
 
获取$_POST变量的值：
post('变量名','默认值','处理函数，多个之间用逗号分隔');
如：post('foo',0,'intval,abs');处理函数的顺序是从左到右。
 
对用户的输入要严格过滤。程序需要的变量是什么类型，就要过滤成什么类型。
 
获取$_GET或$_POST的值
request($key,$default,$funs);
如：request(‘foo’,0,’intval,abs’);
 
添加一个钩子，参数：类型，如：onrequest；处理函数；权重
add_action($type,$handler,$weight);
 
执行钩子，参数：类型
apply_action($type);
 
输出调试信息并结束脚本运行，传入一个或多个参数;可以是任意数据类型
debug();
 
抛出一个错误，参数：错误提示，代码
error($msg,$code);
 
退出脚本，不能用exit  要用 _exit()
_exit(退出提示);
 
设置响应头;用以代替原header函数；
                   set_header($key,$val)
                   如：set_header(‘Content-Length’,1024);
 
                   输出自定义二级域名的网址，参数：二级域名，如：gz
                   dom($dom_name);
例：dom(‘gz’)
输出：gz.domain.com
dom()
输出：domain.com
 
获取带http:// 的网站根网址
site_url()
 
 
 
获取当前访问的链接
cur_url()
 
获取网址的内容-采集，参数：网址，方法（Get或Post），post表单字段，
curl($url,$method,$postfields)
 
 
判断是否ajax请求
is_ajax()
 
判断是否Post请求
is_post()
 
判断是否get请求
is_get();
 
判断是否DELETE请求
Is_delete();
 
获取文件资源的mime类型，参数：文件绝对路径
get_mime($path);
 
URL跳转，代替header(‘location:url’)
redirect($url)
 
数参数转换为正整数，参数：任意数字和字符串。非数字返回0
absint($num)
 
处理长整数，解决系统对数字的最大限制问题
longint($num)
 
移动文件到指定路径，参数：源文件路径，目录路径
move_file($source,$dest);
 
获取来路地址，参数$url不为空时，为保存$url到session，下次获取时会返回该url
referrer($url)
 
 
截取字符串，支持中文和utf8编码，参数：要截取的字符串，开始位置，长度，编码（默认为utf8），超出字符用什么代替
msubstr($str,$start,$length,$charset,$suffix);
 
判断是不是有效的email地址
is_mail($email)
 
 
判断是否有效的url
is_url($url)
 
判断是否有效的QQ号码
is_qq($qq);
 
判断是否有效的手机号码
is_mobile($mobile)
判断是否有效的电话号码
is_phone($phone);
 
字符串模糊化，参数：要模糊化的字符串，开始位置，长度
fuzzy($str,$start,$length)
 
邮箱地址模糊化
fuzzy_email($email)
 
发送邮件，参数：接收方邮箱，标题，正文，附件（以数组形式）
send_mail($to,$subject,$body,$attachment);
 
图片x轴翻转，参数：图片绝对路径，图片类型（如：jpeg,gif,png）
trun_x($img_file_path,$type)
 
图片y轴翻转，参数：图片绝对路径，图片类型（如：jpeg,gif,png）
trun_y($img_file_path,$type);
 
判断是否有效的身份证号码，参数：身份证号码
is_id_card($id_card)
 
判断是否有效的时间格式，参数：0000-00-00 00:00:00 格式的时间
is_datetime($datetime)
 
判断是否日期
is_date($date)
 
判断是否时间
is_time($time)
 
判断是否IP地址
is_ip($ip);
 
 
以逗号分隔的字符串转换成数组
string2array($str)
 
获取客户端IP地址
get_client_ip();
 
JSON数据转换成数组
json_array($json)
 
 
递归将对象变成数组
get_object_vars_deep($object);
 
/**
* 检查是否正整数
* @param numeric $num
* @return bool
*/
is_absint($num);
 
/**
* 将数字转换成2位数的金钱
* @param numeric $num
* @return float
*/
price($num);
 
/**
* 添加一个异步任务
* @param string $task_name 任务名称
* @param arrau $task_data 任务数据
* @return bool
*/
add_task($task_name,$task_data);
 
 
 
 
 
 
/**
* 返回带域名的链接
* @param string $path
* @return string
*/
U($path);
 
/**
* 根据数组或对象的下标取值，不存在则返回默认
* @param mix $obj
* @return string
*/
val($obj,$key,$default='');
 
/**
* 检查所有入参是否不为空值
* @param mix   传入任意要检测的变量
* @return bool
*/
check_not_empty();
 
/**
* 发送404状态码
* @param string $title
* @param string $content
* @return void
*/
http404();
 
/**
* 发送自定义状态码 
* @param string $title
* @param string $content
* @return void
*/
http_status($title,$content,$code=502);
 
 
 
 
 
 
 
 
高级应用
 
每个Action的命名空间为：
namespace Act;   对应 Act下的文件名如果有二级目录，则变成：Act\Path;
namespace一定要放在php文件开始的第一行（不包含注释）
 
引用命名空间为：
use Space;
 
----------------------Lib的一些工具类：-------------------------
调用方法为：
引用命名空间方式：use Lib\ClassName; $class = new ClassName();
需要namespace 下面加一行：use Lib\ClassName;
直接使用方式：$class = new \Lib\ClassName();
 
判断IP地址所在地的类库
use Lib\Ip\Ip;
$ipquery = new Ip();
$location = $ipquery->getLocation($client_ip);
返回：Array ( [ip] => 219.136.32.110 [beginip] => 219.136.32.0 [endip] => 219.136.34.255 [country] => 广东省广州市海珠区 [area] => 电信ADSL )
 
 
输出验证码
use Lib\VerifyCode\VerifyCode
VerifyCode::show($ch);  //当$ch为true时，输出带中文的验证码
 
输出分页代码
use Lib\Page;
$page = new Page($count,$size);  //$count:数据总数，$size:每页数据量
$page->offset //数据偏移量
$page->size //每页数据量
$page->show() //输出分页代码
 
中文转拼音
use Lib\Pinyin;
$pinyin = Pinyin::Pinyin($str,$charset) //$str:中文字符串,$charset:字符串编码，默认为utf8
 
数据验证类
use Lib\Validator;
$validator = new Validator($data); //$data:要验证的数据
$validator->setRules([      //设置验证规则
'field' => ['required'=>true,'validate'=>'trim'],
        ])->setMessages([  //设置验证失败时提示语
            'field' => ['required'=>'字段不能为空','validate'=>'字段数据不合法'],
        ]);
   
        $data = $validator->validate();
        if(!$data){
            $this->error = $validator->getError();
            return false;
            }
 
 
 
异步任务脚本的写法示例：
/**
* Auth Sang
* Desc 商品PV
* Date 2015-02-12
*/
namespace Task;
class Goods{
   public static function run($data){
            global $php;
            try{
                     $data = $data;
                     $mod = model('Goods');
                     //PV+1
                     $mod->pv($data['id'],1);
            }catch(\Exception $e){
                     $php->log($e->getMessage.' | '.$e->getFile());
            }
   }
}
 
 
 
 
 
 
 
 
 
 
 
 
 
 
定时脚本写法示例：
/**
* @Auth Sang
* @Desc test
*       时间格式，秒分时日月周,用法与linux的crontab相同
*                   一定要定义公有变量 $time，否则该定时任务不工作
* @Date 2015-03-13
*/
namespace Cron;
class Test{
   public $time = '*/3 * * * * *';
   private $start_time;
   public function __construct($start_time){
            $this->start_time = $start_time;
   }
   public function run(){
            #do something
   }
}