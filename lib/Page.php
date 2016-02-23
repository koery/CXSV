<?php
/*
Auth:Sang
Desc:分页类
Date:2014-11-01
*/
namespace Lib;
class Page{
	//显示当前页的前后页数  4,5,6,七,8,9,10
	private $page_offset;
	public $offset;
	public $size;
	public $page_no;
	private $params = [];
	private $max_page = 1000;
	public function __construct($count,$page_size,$page_offset=7){
		$this->page_offset = $page_offset;
		$this->count = $count;
		$this->size = $page_size;
		$this->page_total = ceil($count/$page_size);
		$this->page_total =  $this->page_total > $this->max_page ? $this->max_page : $this->page_total;
		$this->page_no=get('page_no',1,'absint');
		$this->offset = ($this->page_no-1)*$page_size;
		if(isset($_SERVER['QUERY_STRING'])){
			parse_str($_SERVER['QUERY_STRING'],$params);
			if(!empty($params)){
				if(isset($params['page_no']))
					unset($params['page_no']);
				$params = array_map(function($v){return htmlspecialchars($v);},$params);
			}
			$this->params = $params;
		}
	}
	public function show($show_count = true){
		if($this->page_total<=1){
			return;
		}
		$params=$this->array2queryStr($this->params);
		$params && $params .= '&amp;';
		$url = htmlspecialchars(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : $_SERVER['PATH_INFO']);
		$navibar = "<div class=\"pagination\"><ul>";
		$offset=$this->page_offset;
		$total_page=$this->page_total;
		$page_no=$this->page_no>($total_page)?($total_page):$this->page_no;
		if ($page_no > 1){
			$navibar .= "<li><a href=\"$url?{$params}page_no=1\">首页</a></li>\n <li><a href=\"$url?$params&page_no=".($page_no-1)." \">上一页</a></li>\n";
		}
		/**** 显示页数 分页栏显示11页，前5条...当前页...后5条 *****/
		$start_page = $page_no -$offset;
		$end_page =$page_no+$offset;
		if($start_page<1){
			$start_page=1;
		}
		if($end_page>$total_page){
			$end_page=$total_page;
		}
		for($i=$start_page;$i<=$end_page;$i++){
			if($i==$page_no){
				$navibar.= "<li class=\"current\"><span>$i</span></li>";
			}else{
				$navibar.= "<li><a href=\" $url?{$params}page_no=$i \">$i</a></li>";
			}
		}
		
		if ($page_no < $total_page){
			$navibar .= "<li><a href=\"$url?{$params}page_no=".($page_no+1)."\">下一页</a></li>\n ";
		}
		if($total_page>0){
			$navibar.="<li><a>共". $total_page."页</a></li>";
		}
		$show_count && $navibar.="<li><a>共".$this->count."条</a></li>";
		$jump ="";
		//$jump ="<li><form action='$url' method='GET' name='jumpForm'><input type='text' name='page_no' value='$page_no'></form></li>";
		
		$navibar.=$jump;
		$navibar.="</ul></div>";
		return $navibar;   
	}

	private function array2queryStr($array){
		if(empty($array)){
			return '';
		}
		$str = '';
		foreach($array as $key=>$val){
			$str .= "{$key}=".urlencode($val)."&amp;";
		}
		return substr($str,0,-5);
	}
}
?>
	 