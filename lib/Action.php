<?php
/*
* Action 的基类，所有Action都必须继承该类
*/
namespace Lib;
class Action{
	public $serv;
	// 模板目录
	protected $tpl_path;
	// 主题
	protected $theme;
	// 挂件
	protected $widgets;
	// 控制器
	public $act_name;
	// 页面ID
	public $page_id;
	// 请求方式
	public $request_method;
	// 模板变量
	protected $data;
	// 动作
	public $act;
	public function __construct($server,$request_method){
		$this->serv = $server;
		$this->data = [];
		$this->theme = isset($this->serv->config['app']['theme']) && !empty($this->serv->config['app']['theme']) ? $this->serv->config['app']['theme'] : 'default';
		$classed_class = explode('\\',get_called_class());
		array_shift($classed_class);
		$this->tpl_path = strtolower(join('/',$classed_class));
		$this->act_name = \Lib\Common::getActionUrl();
		$this->page_id = abs(crc32($this->act_name));
		$this->assign('page_id',$this->page_id);
		$this->request_method = $request_method;
		$this->act = request('act','','strip_tags,trim');
		/**
		 * 如果子类有__costruct 会覆盖父类的__construct 
		 * 子类申明_init 会在父类的__coustruct结束之后
		 * 首先调用_init函数 通常用来初始化数据而又不想影响
		 * 父类的构造函数
		 */
		if(method_exists($this, '_init')){
			call_user_func(array($this,'_init'));
		}
	}	
	//当子类中没有实现doGet(下同)方法，会隐式的调用父类的doGet 转到报错页面
	public function doGet(){
		return $this->serv->http404();
	}
	public function doPost(){
		return $this->serv->http404();
	}
	public function doUpdate(){
		return $this->serv->http404();
	}
	public function doDelete(){
		return $this->serv->http404();
	}
	public function doAdd(){
		return $this->serv->http404();
	}
	/**
	 * [assign 参数存贮池 可多次调用]
	 * @return [type] [description]
	 */
	protected function assign(){
		/**
		 * func_get_args  动态获取参数个数
		 * 实用场景参数个数不确定 参数类型不需要确定
		 */
		$argvs = func_get_args();
		if(empty($argvs)){
			return;
		}
		if(is_array($argvs[0])){
			return $this->data = array_merge($this->data,$argvs[0]);
		}elseif(count($argvs)>1){
			//传入参数写入参数池
			return $this->data[$argvs[0]] = $argvs[1];
		}
	}
	/**
	 * [display 显示函数]
	 * @param  [type] $tpl_file [模板名称]
	 * @param  array  $data     [参数数组 可选]
	 * @return [type]           [description]
	 */
	protected function display($tpl_file,$data = array()){
		if(!is_array($data)){
			_exit('template data must to be an arrays!');
		}
		//合并到参数池
		$data = array_merge($this->data,$data);
	    //参数解耦
		extract($data);
		//引入显示模板
		include($this->tpl($tpl_file));
		unset($data);
		//清空参数池
		$this->data = [];
	}

	public function tpl($tpl_file){
		/**
		 * 模板路径用：分割
		 */
		if(strpos($tpl_file, ':')){
			list($dir,$tpl_file) = explode(':',$tpl_file,2);
		}else{
			$dir = $this->tpl_path;
		}
		/**
		 * 为开启debug 模式 会首先在内存中寻找模板
		 * 开启debug 会调用真实的模板
		 */
		//请求的模板在内存主题中的位置
		$shm_file = SHM_PATH.'tpls/'.$this->getTemplate().'/'.$dir.'/'.$tpl_file;
		//请求的模板在内存中默认主题的位置
		$default_shm_file = SHM_PATH.'tpls/default/'.$dir.'/'.$tpl_file;
		//请求的真实模板位置
		$real_tpl_file = TPL_PATH.$this->getTemplate().'/'.$dir.'/'.$tpl_file;
		//请求的模板在默认主题中的位置
		$default_real_tpl_file = TPL_PATH.'default/'.$dir.'/'.$tpl_file;
		//在内存主题中存在该模板&&开启了debug
		if(is_file($shm_file)){
			if(!defined('DEBUG')){return $shm_file;}
		}elseif($this->getTemplate()!='default' && is_file($default_shm_file)){
			if(!defined('DEBUG')){return $default_shm_file;}
		}

		//找不到主题的模板默认调用默认模板
		if($this->getTemplate() != 'default' && !is_file($real_tpl_file)){
			$real_tpl_file = $default_real_tpl_file;
			$shm_file = $default_shm_file;
		}
		
		if(!is_file($shm_file) || filemtime($real_tpl_file)>filemtime($shm_file)){
			if(!is_file($real_tpl_file)){
				if(is_file($shm_file))
					unlink($shm_file);
				throw new \Exception("Template file <font color='red'>".str_replace(APP_PATH,'',$real_tpl_file)."</font> not exist!", 300);
			}
			$shm_dir = dirname($shm_file);
			if(!is_dir($shm_dir)){
				mkdir($shm_dir,0755,true);
			}
			//将真实的模板copy到内存中
			copy($real_tpl_file, $shm_file);
		}
		return $shm_file;
	}

	/**
	* 向页面插入一个挂件
	* @access public
	* @param string $name 挂件名称
	* @param array $data 参数
	* @param string $page 所在页面名称，如该挂件为所有页面公共显示的，可在多个页面上输入一个相同的名称，以保持数据统一
	* @return void
	*/
	public function widget($name,$data=[],$page=''){
		//不存在该模板则写入模板池中
		if(!isset($this->widgets[$name])){
			$arr = preg_split("#[\/\\\]#", $name);
			$w_name = ucwords(array_pop($arr));
			$path = join('\\',$arr);
			$widget = '\\Widget\\'.($path ? $path.'\\' : '').$w_name.'\\'.$w_name;
			if(!class_exists($widget)){
				throw new \Exception("Widget <font color='red'>{$name}</font> not exist",400);
			}
			$this->widgets[$name] = new $widget(empty($page) ? $this->page_id : abs(crc32($page)),$this);
		}
		return $this->widgets[$name]->init()->show($data);
	}

	public function checkParam($param,$to_url=null){
		if($to_url == null ){
			if(array_key_exists('HTTP_REFERER',$_SERVER)){
				$referer = $_SERVER['HTTP_REFERER'];
			}
			if(!empty($referer)){
				$start = strpos($referer,site_url());
				$to_url = substr($referer,$start+strlen(site_url()));
			}else{
				$to_url = '/os/index';
			}
		}
		
		if (empty ( $param )) {
			throw new \Exception("缺少必要的参数", 0);
		}
	}
	//返回json 形式的错误
	public function jsonError($msg){
		return $this->json(array('error'=>1,'msg'=>$msg));
	}

	public function jsonSuccess($msg=''){
		return $this->json(array('success'=>1,'msg'=>$msg));
	}

	public function json($data,$unescaped_unicode=true){
		if($unescaped_unicode){
			return json_encode($data,JSON_UNESCAPED_UNICODE);
		}else{
			return json_encode($data);
		}
	}

	public function jsonp($data,$unescaped_unicode=true){
		$callback = request('callback','','trim');
		$ret = $this->json($data,$unescaped_unicode);
		if(!empty($callback)){
			set_header('Content-Type','text/javascript');
		}
		return $callback ? $callback.'('.$ret.')' : $ret;
	}

	public function addScript(){
		$scripts = func_get_args();
		$remote_script = $local_script = "";
		foreach($scripts as $script){
			if(strpos($script, 'http')===0){
				$remote_script.="<script type=\"text/javascript\" src=\"{$script}\"></script>\n";
			}else{
				$local_script .= $local_script ? ','.$script : $script;
			}
		}
		$this->assign('local_script',$local_script);
		$this->assign('remote_script',$remote_script);
	}

	public function addCss(){
		$csss = func_get_args();
		$remote_css = $local_css = "";
		foreach($csss as $css){
			if(strpos($css, 'http')===0){
				$remote_css.="<link rel=\"stylesheet\" type=\"text/css\" href=\"{$css}\"/>\n";
			}else{
				$local_css .= $local_css ? ','.$css : $css;
			}
		}
		$this->assign('local_css',$local_css);
		$this->assign('remote_css',$remote_css);
	}

	public function setTitle($title){
		$this->assign('page_title',$title);
	}

	public function setKeywords($keywords){
		$this->assign('page_keywords',$keywords);
	}

	public function setDescription($description){
		$this->assign('page_description',$description);
	}

	protected function message($message_detail, $forward_url='', $second = 5,$type="message") {
		if(empty($forward_url)){
			$forward_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'javascript:window.history.go(-1);';
		}
		if(self::isMobile()){
			switch($type){
				case 'success':
					$icon = '<span style="border:1px solid #6ACA32;border-radius:30px;width:50px;height:50px;color:#6ACA32;line-height:50px;text-align:center;display:inline-block;font-size:30px;">✔</span>';
					$color = '#6ACA32';
					break;
				case 'error':
					$icon = '<span style="border:1px solid #FF9190;border-radius:30px;width:50px;height:50px;color:#FF9190;line-height:50px;text-align:center;display:inline-block;font-size:40px;">×</span>';
					$color = '#FF9190';
					break;
				default:
					$icon = '';
					$color = '#2A74C9';
					break;
			}
			$msg = '<div style="text-align:center;margin-top:17px;">'.$message_detail.'</div>';
			$btn = '<div style="text-align:center;margin-top:17px;"><a href="'.$forward_url.'" style="border:1px solid '.$color.';border-radius:5px;width:100px;height:30px;text-align:center;line-height:30px;color:#ffffff;background:'.$color.';padding:0;margin:0;display:inline-block">确定</a></div>';

			$content = '<div style="border:2px solid '.$color.';border-radius:5px;padding:10px;text-align:center;background:#ffffff;">'.$icon.$msg.$btn.'</div>';
			
			$html = '<!DOCTYPE html>
			<html>
			<head>
			    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
			    <meta charset="utf-8">
				<title>提示信息</title>
				<style>
					body{padding:50px 20px 0 20px;background:#666666}
					a{text-decoration: none;}
				</style>
			</head>
			<body>
			'.$content.'
			</body>
			</html>';
			return $html;

		}
		switch ($type) {
			case "success" :
				$page_title="操作成功！";
				$img = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAASbSURBVEhL3VRtUJRVFL4NE+yyCMgiLMvSonzErhlCKx/VZB9O6aCVOWgYZOW/RjJnLG1AbZgEMpWYJifMAUwGAZnagPgYIBdWIVlRWAWCJEJWPqJWbHeBdT+e7n1XqB1I1J/dmXfufd9z3vOc+5znHEL+1wtn5R5TdfK3TLVylalOPmCqlcFYHWEwVEd0G354tOqvStmHN5WyqAcigQbdMVkju2k+Hwnr1VjY+p4Efn0a9t442DqjYflpOSYbwjFSGqruPxWafM8g9lIJ31gToTa3rIRt4AVgbAPw+6v0eYWeXwZGEgDdi8DAs0BfPG6rwzFaEmrSFYdcWBAEZcSFXr1lujWKBnwNGN8E6LcAEynArW2Onb0zwBsvAYPPcSD2jkhMKKXo/Tq45a4gExUReyfPRXKB9W2roT0iQVuaN7RHg6G/kgxM7QFMqdBfWgvtYbHDdsgP+ppQ2K8oMJAvvX0xR/LNvCBjVXKRXhlmsOsoBTRDTWYAhlWfgS22a7KWApYvAXMWBZTO2v7sLENHpi+gCYe5ORydX0i6PtnsFT0HZLw8fO+kJoZGW8vR0LTbmws+s7h3WwVgLZzfdlkOqIMwXuyH5oOioicIedgJZKwkVGvtfQYYXc9xzQKe+SgZ6SsIt3MA9lYOZF6bdgVwXgpzpRAXs0XXFWIS5ASgKwqxYvB5h2poQTVZQVzwlBDC7ZpsGQXooAC10OaEOdlYHaB9HGgJhr1WiLZskU3hTx5zAhg8STmmOp+5gV6bBOX7gfh0nQeUu5ZC35M3ewN9105qE+FQgjuU7wmhV1Fh3LkB6nzQmuGH6ADiXIf+E1Lgl/jZGjC1sIIyzjnuaeYzNeC+MzuTLKsZ++9ODVCzGM3pvogNILQM/1o/H3vEim4FcJ3SxHTOdD+52wHC1GM5Pqsi7juzMz/m30P/oyrC2QDYqzzR8IGPbQ7A1dxA7VQrVcI1OhJoEzVlSjB9jRacZcr0P5022wdc8D9oE1K/kR8jqW8gxz/ql8B4WoDKVO+BORR1HhanDZdRmrpoF9NaWMZ3omHXIhjbV/9nJzPbt1sJzEPvACoxUO2FvqPuyE8R5K3yJ8udKLp8RBJ4IVNkNJ8Lc4DQm8yAqA/4Ylz1lEMAlHN2Vqd7/ROc6h91QkycEqB0u6DnbYXruqggIp7TbM0H/PbryimAhj5MFYzboY3oLopH7TaCnASC3PWEO99oXEOH3mauuVDvC9v3HijfLrDkvc7PjxUR2ZxGY2hlicSlfo+w87fTyxycssIxdQzS3tDR4TdKhx172Hko0UELzdzynQcufcxDwVZ+I+M+TkIC7zb0HqpM9WzvPymFocLfkSHtUA6Q7eydqoUVlHGu+8odxcl80/FEXgVTTvQSErLg2GYOJ5IFGaoMibH9oCeGC7xx64wPzdYH1qrFMJQuwtAxd2j281C4hde3b43bjlVisjLWnwTfU/AZp3fj3EI/38jbV5DEbypK4feXvMlH0Ru86cIkt8G8Ta6NuRtc81nWMSIij/EhnvcVfMY5kRAXmp2QXZ1JjwWkgyyKzRqFH1m2EN8PBHo/P/0NqLG/pCNz4j4AAAAASUVORK5CYII=';
				break;
			case "error":
				$page_title="错误!";
				$img = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAASgSURBVEhL3VRtUJRVFL6NE7AsAgLCsiyB8hG7ZgitfPRlH07poJU5ZBhkjVM/GqmcsbQRtWESyFRkmpwwBzAZBGRqA+JjgBFZhWRFYRUIijZ0RYhcMXbBhd19uuddYdqBEfVnd+ade+97zj3Puc95zmXsfz1wSuE2Xqt4x1SjaDTVKnSmGjmMVRGjo1URXaM/P1r5T4X805sqedQDkcCDbhmrlt80n42E5XIsrL1PAn88DVtPHKwd0Zj8ZQnG6sNxvSRU3Xc8NPmeQWwlMpGxOkJtbl4Gq+5FYGgt8Ndr/HuVr18BricA+pcA3XNAbzwm1OEYLA416YtCzs0JglI2j1+9+XZLFA/4OjC8HjBsAEZSgFub7DPtCfDay0D/8wKIrT0SI6og9HwX3HxXkJHyiB1jZyKFwIbWFdAekKF1pye0B4NhuJQMjG8HTKkwXFgF7X6p3bbPF4bqUNguKaHLC5o4ny37flaQoUqFxKAKG7XpOQU8Q02GPwYavwINmjWZi4DJbwBzJgcMmrbd6ChFe4YPoAmHuSkcHV/LOr94wyN6BshwWfiOMU0Mj7ZKoOH0Nk8h+NQQ9tZywFIwu+2iAlAHYrjIF017JYVPMPawA8hQcajW0vMsMLhG4JoCnvwsGWlLmTALALYWAWRWm3YpcDYI5gpvnM+SXFFKWaADgL4wxIL+F+yq4QXVZAYKwVNCmDBrsuQcoJ0D1ECbHeZgozpA+zjQHAxbjTdasyRWpR97zAGg/xjnmOt86gYGbRJUHwfgy9VuUG1dBEN37vQNDJ0fcZsE+xJcofrQG4ZGLow7N0CtF1rSfRHtzxzr0Hc0CPgtfroGpBYqKHEucM8zn6qB8J/sJFmqGZ27UwNUL0BTmg9i/Rkvw3/Gr4cfsaBLCVzhNJHOSfdj2+wgpJ7JI9MqEv6TnfzIv5uf4yrCKX/YKt1R/4mXdQbA5ZwA7XgLV8Lv/EmgJvqbNxkFoUxvvMe7lzfeIG80+mg9xPuC/Mif6OH8o24hjCfEqEj11M2gqGO/dOdAKaepk3cx1YIOU4ZX16GrMB41mxiyExhy1jBhfa1hJX82kuz+mjCgUQpUeaD3oCvyUsS5y/3YEgeKLh6QBZzLkBjNZ7gzHeKZWYbeR/3W+VDv8cFw41N2AXDOaa1O88APGxkmdLwOXP+o9cbIcTFKNou731U6rY4KZNIZzda0x3e3vowDUEb82hTc2LbCfiPdM/aZCkqcczvZyAd1PrD+5IayzeLJ3DdFebESJp/RaIRWmsjm1W337vjzxGI7p1Q4UgdxTDqnmfb0n+xEC8988kc3XPjcBfkbRQ3EfZyMBdzt0XuoItW9re9YEEbL/ezX5x0qBKSZ9lwtVFDiXP+tK4qSRaYjiS7lpJzohSxkzmebHI4mi9Mb02XGtr3uGMj3xK2TXjxbL1gqF2C0ZD6uHnaFZrcLCja49O5a6bxluZQti/VjwfcUfMrpgzjn0EPrXHblJ4lOF6aI+orfFqHwLZfbBUnO/bnrnRpy1jrlUdYxEqaI8WLu9xV8yjmRsXk8O2+6OkmPAvKHLIreGqUvWzwX3w8Eej+H/gW+a74fDohtDQAAAABJRU5ErkJggg==';
				break;
			default:
				$page_title="提示信息！";
				$img = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAASnSURBVEhL3VV7UFR1FL4GCatIKvJaQUh5LooQC4SGBOlkklNWZAiYpTWTCTY5WkwpVEPiJBQKoZRACg5LBCLII+VRJioMoATogMvKww1yQBdYXsvu17n37oKMjqh/tjNnfo89v+8778sw/+sfKkRGw6WiLcoSUaWyVCRTljhjsMhpYKDIqXngjGNhf4HznjunnN2fKAgEumP4T49+1d++UEtfgrrtRaDdHxrpKqiv+WC8XoyRKjfczrGv7M11DHpkEo3ESjBY7HRefXMdNO0vA/K1QPerQM96fmXPHQHAjRVAiw9UNW7oLXYd+kdid2FaEmQzeuR6lbqLBSOg3teBu28DilCSd2kfBvRtBP6lezmRd/gRkRc0V1ygvCRGx/Fnzz2U5G6+U+RYy2qydi2OvaI/IRj+BBj+jJfBcC3Rhqk6NQ6Q5y9VSY/ZHHogSU+hyEJR7jaE9gDuIQZDCPAjYGw3f1Z9D4wn0fkbug/n7/o2kDFrKDe+3Hms0hZNidZXGxNtLO4j6ZbYfzHc4MM/VAQRyAf8fvQAv6ozSE5zJNyZ/mf1uP0tf34lL26fNEPDDwsT7yPoylzcrJZpFftDtOB7CTCB34+fBTSXaE3XEui8oHzdQzBasAC13wllUwjAYEbXCftx3NSGp28L0L9rMges5Sw4R5CjJaC8UNJ1uRpvoqq6uAyaUiFqYoVqFnOCBEHZep2pTsCVdUBTKA/Q+jlZto9ADlM4Mie9IIKJsMnJkFaqsKsU0tpAXueMM6qjF4HF5AhYJk14kUF70nKgmBKbvRvI3TulQvhcUA5YcAoRhmKojCPu15G8Bxz3w+VIB7CYnBccwVsSgTTeUw3J+0DSV0AcVUzKQSAvFrhMIo8n0GSqIKqikf2U3D1U/9uBigggi0o3JQpIIG8P7oQmMRBVn7qoWcxJAmJr/HaZbFRCDXRiJ/BTNJBKVuZ+DVTT41uU7MEviUTbB71k6Q1quvJtvP4h8jqO1oQQDB9egXMf23dOeMCFieJVHrkkuecXd3pEeah4B/iL4lu3lYDIqx4CvENnXSd3U/230dioI11WP5+6OmsVkLkcsthFyNosTJnIgS4PCZssPapjrJRjFz3poQ/Q6E/g7DgIpBCxo+OeWUTNiNaVQIOYqxyUOQCF1lCkm+C3bXOvR602Fk+pIpbEg2GezotYkCj92ZKqwo3EFbhGAK1E1vYCIPPlV/bM3jcQMDUWzlPF/G4K9ak5yNlqpEoNnn2UxXrguPCzY6xKds1rvp5kSl648AD1Ih6swRUFYQacQEbjocoWqBQCpSZQ5RmhLtoQaZsEZc9bMQsfNvBmeFswzvk7jOub4udBWW5HFloDF2w4QBY8OeApnuAsGVH0DLqOzMLJUIEyJcjw9HOmzJJpRzarIBYyjkeCZx/I/dBIURtjDHnaXCh+nc8DkwxI5qDzx1mo2WeI9I2GLVFr9Ld7mzO2jwSuU/IxZ8zecNFfGfeaIDotWPBHRphAmrVZgIwQw5H0YIP2o2/OLEtYPzPVy4IRec1njB8LXKdM30E9TyFjwrruac64eFsyHuSdu9icWSo2YxZPF+8nIn3cR/8BM7vinQPuEUcAAAAASUVORK5CYII=';
				break;
		}
		
		
		$str = join("\n",['<!DOCTYPE html>',
						' <html>',
						'	<head>',
						'		<meta charset="utf-8">',
						$second && $forward_url ? "<meta http-equiv=\"refresh\" content=\"{$second};url={$forward_url}\">" : "",
						"		<title>{$page_title}</title>",
						'		</head>',
						]);
		$str .= <<<eof
				<body>
					<div style="position:absolute;left:50%;top:10%;margin-left:-250px;width:500px;min-height:80px;border:1px solid #09AFFF;">
					<h1 style="padding:0;margin:0;height:30px;line-height:30px;text-indent:7px;font-size:14px;font-family:'Microsoft YaHei';background:#09AFFF;color:#ffffff;">提示信息</h1>
					<h2 style="font-size:16px;font-weight:normal;font-family:'Microsoft YaHei';text-align:center;">
					<img src="{$img}" valign="middle"> {$message_detail}
					</h2>
					<h3 style="font-size:12px;padding:0;margin:0;text-align:center;margin-top:20px;font-weight:normal;margin-bottom:20px;">
						<a href="/" style="color:#999999;">返回首页</a>&nbsp;&nbsp;<a href="{$forward_url}" style="color:#999999;">返回上一页</a>&nbsp;&nbsp;<a href="javascript:window.close();" style="color:#999999;">关闭</a>
					</h3>
					</div>
				</body>
				</html>
eof;
		echo $str;
	}

	protected function error($message_detail, $forward_url='',$second = 5) {
		return $this->message($message_detail, $forward_url, $second ,'error');
	}

	protected function success($message_detail, $forward_url='',$second = 5 ) {
		return $this->message($message_detail, $forward_url, $second, 'success');
	}
	
	public function addFormVerify(){
		$verify_code = \Lib\Encrypt::encrypt($_SERVER['REMOTE_ADDR'].'@'.dom().'@'.microtime(true));
		return '<input type="hidden" value="'.urlencode($verify_code).'" name="_fvc_">'."\n";
	}

	public function verifyForm(){
		$verify_code = request('_fvc_','','urldecode');
		$real_data = \Lib\Encrypt::decrypt($verify_code);
		if(!empty($real_data)){
			$arr = explode('@',$real_data,3);
			if(isset($arr[0]) && isset($arr[1]) && isset($arr[2]) && is_ip($arr[0]) && $arr[1]==dom() && is_numeric($arr[2])){
				return true;
			}
		}
		return false;
	}

	public function hasFormVerify(){
		return request('_fvc_') ? true : false;
	}

	public function log($msg,$level='info',$prefix=''){
		return Loger::getInstance($prefix)->$level($msg);
	}

	public function setTemplate($template){
		$this->theme = $template;
	}

	public function getTemplate(){
		return $this->theme;
	}

	public static function isMobile() {
	    $theusagt = val($_SERVER,"HTTP_USER_AGENT");
	    if(empty($theusagt)){
	    	return false;
	    }
		$is_mobile = false;
		if(stripos($theusagt , "iPhone") !== false || stripos($theusagt , "iPod") !== false){
		    //$thetargetsite = $siteurl_mobile;
		    $is_mobile = true;
		}
		else if(stripos($theusagt , "Mac OS") !== false){
		    //$thetargetsite = $siteurl_pc;
		    $is_mobile = false;
		}
		else if(stripos($theusagt , "Mobile") !== false){
		    //$thetargetsite = $siteurl_mobile;
		    $is_mobile = true;
		}
		else if(stripos($theusagt , "Android") !== false){
		    //$thetargetsite = $siteurl_pc;
		    $is_mobile = false;
		}
		else if(stripos($theusagt , "Windows Phone") !== false){
		    //$thetargetsite = $siteurl_mobile;
		    $is_mobile = true;
		}
		else {
		    //$thetargetsite = $siteurl_pc;
		    $is_mobile = false;
		}
		return $is_mobile;
	}
}