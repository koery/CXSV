<?php
/*
Auth:Sang
Desc:后台功能菜单模型
Date:2014-10-27
*/
namespace Mod;
use Lib\Model;

class MenuUrl extends Model{
	public function _init(){
		if(!$this->tableExists($this->getTableName())){
			$sql = <<<eof
CREATE TABLE `{$this->getTableName()}` (
  `menu_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '菜单ID',
  `menu_name` char(50) NOT NULL DEFAULT '' COMMENT '菜单名称',
  `menu_url` varchar(128) NOT NULL DEFAULT '' COMMENT '菜单链接',
  `module_id` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属模块ID',
  `is_show` tinyint(4) NOT NULL DEFAULT 1 COMMENT '是否在sidebar里出现',
  `online` int(11) NOT NULL DEFAULT 1 COMMENT '在线状态，0下线 1在线',
  `sort_order` int(3) NOT NULL DEFAULT 999 COMMENT '排序',
  `shortcut_allowed` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '是否允许快捷访问',
  `menu_desc` varchar(120) NOT NULL DEFAULT '' COMMENT '菜单描述',
  `menu_icon` char(30) NOT NULL DEFAULT '' COMMENT '菜单图标',
  `father_menu` int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '上一级菜单',
  `is_sys` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '是否系统菜单',
  PRIMARY KEY (`menu_id`),
  UNIQUE KEY `menu_url` (`menu_url`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;
eof;
			$this->db->query($sql);
		}
	}
	public function getMenusByRole($user_role,$uid=0) {
		$condition = 'me.online=1 and mo.online=1';
		if($uid!=1 && empty($user_role)){
			return [];
		}elseif($uid!=1){
			is_array($user_role) && $user_role = join(',',$user_role);
			$condition .= " and me.menu_id in({$user_role})";
		}
		$url_array = array ();
		$sql ="select me.*,mo.module_id,mo.module_name,mo.module_icon,mo.module_url from ".$this->getTableName()." me left join ".model('#Module')->getTableName()." mo on(me.module_id=mo.module_id) where {$condition}";
		 
		$list = $this->db->query($sql)->fetchAll();
		return $list;
	}

	public function getMenuByUrl($url) {
		$condition = "menu_url = '{$url}'";
		if(substr($url,-1)=='/'){
			$condition .= " or menu_url = '{$url}index'";
		}elseif(substr($url,-6)=='/index'){
			$condition .= " or menu_url = '".substr($url,0, -5)."'";
		}
		$menu = $this->where($condition)->fetch();
		if (!empty($menu)) {
			 $module = model('#Module')->getModuleById($menu['module_id']);
			 if($module){
				$menu['module_id']=$module['module_id'];
				$menu['module_name']=$module['module_name'];
				$menu['module_url']=$module['module_url'];
				$menu['module_icon']=$module['module_icon'];
				if($menu['father_menu']>0){
					$father_menu=$this->getMenuById($menu['father_menu']);
					$menu['father_menu_url'] = $father_menu['menu_url'];
					$menu['father_menu_name'] = $father_menu['menu_name'];
				}
			}
			return $menu;
		}
		return array ();
	}

	public function getUserMenuByUrl($url){
		$urls = [$url];
		if(substr($url, -1)=='/'){
			$urls[] = $url.'index';
		}elseif(substr($url, -6)=='/index'){
			$urls[] = substr($url, 0,-5);
		}
		$user_info = session('user_info');
		$menus = val($user_info,'menus',[]);
		foreach ($menus as $key => $menu) {
			if(in_array($menu['menu_url'], $urls)){
				return $menu;
			}
		}
		return [];
	}

	public function getListByModuleId($module_id,$type="all" ) {
		if (! $module_id || ! is_numeric ( $module_id )) {
			return array ();
		}
		$where = "1";
		switch ($type){
			case "sidebar":
				$where.=" and is_show=1 and online=1";
				break;
			case "role":
				$where.=" and online=1";
				break;
			case "navibar":
				$where.=" and is_show=1 and online=1";
				break;
			default:
		}

		$where .= " and module_id={$module_id}";
		$list = $this->where($where)->select();
		if ($list) {
			return $list;
		}
		return array ();
	}

	public function getMenuById($menu_id) {
		if (! $menu_id || ! is_numeric ( $menu_id )) {
			return false;
		}
		$list = $this->where("menu_id='{$menu_id}'")->fetch();
		if ($list) {
			return $list;
		}
		return array ();
	}

	public function getMenuByIds($menu_ids,$online=null,$shortcut_allowed=null) {
		$where="menu_id in({$menu_ids})";
		if(isset($online)){
			$where .= " and online='{$online}'";
		}
		if(isset($shortcut_allowed)){
			$where .= " and shortcut_allowed='{$shortcut_allowed}'";
		}
		
		$list = $this->where($where)->select();
		if ($list) {
			return $list;
		}
		return array ();
 
	}


	/**
	* 批量修改菜单，如批量修改所属模块
	* menu_ids 可以为无key数组，也可以为1,2,3形势的字符串
	*/
	public function batchUpdateMenus($menu_ids,$function_data) {

		if (! $function_data || ! is_array ( $function_data )) {
			return false;
		}
		return $this->where("menu_id in({$menu_ids})")->update($function_data);
	}

	public function updateMenuInfo($id,$data) {
		if(empty($id) || (!is_numeric($id) && !is_array($id)) || empty($data) || !is_array($data)){
            return false;
        }
        
        $validator = new \Lib\Validator($data);
        $validator->setRules([
            'menu_name' => ['validate'=>'strip_tags,trim'],
            'menu_url' => ['validate'=>'strip_tags,trim'],
            'module_id' => ['validate'=>'absint'],
            'is_show' => ['validate'=>'absint'],
            'online' => ['validate'=>'absint'],
            'sort_order' => ['validate'=>'absint'],
            'shortcut_allowed' => ['validate'=>'absint'],
            'menu_desc' => ['validate'=>'strip_tags,trim'],
            'menu_icon' => ['validate'=>'strip_tags,trim'],
            'father_menu' => ['validate'=>'absint'],
            'is_sys' => ['validate'=>'absint'],
        ])->setMessages([
            'menu_name' => ['validate'=>'菜单名称填写不正确'],
            'menu_url' => ['validate'=>'菜单链接填写不正确'],
            'module_id' => ['validate'=>'所属模块ID填写不正确'],
            'is_show' => ['validate'=>'是否在sidebar里出现填写不正确'],
            'online' => ['validate'=>'在线状态填写不正确'],
            'sort_order' => ['validate'=>'排序填写不正确'],
            'shortcut_allowed' => ['validate'=>'是否允许快捷访问填写不正确'],
            'menu_desc' => ['validate'=>'菜单描述填写不正确'],
            'menu_icon' => ['validate'=>'菜单图标填写不正确'],
            'father_menu' => ['validate'=>'上一级菜单填写不正确'],
            'is_sys' => ['validate'=>'是否系统菜单填写不正确'],
        ]);
    
        $data = $validator->validate();
        if(!$data){
            $this->error = $validator->getError();
            return false;
        }
    	if($this->getMenuByName($data['menu_name'],$id)){
    		$this->error = '要修改的菜单名称已存在';
    		return false;
    	}
        $ids = is_array($id) ? join(',',$id) : $id;
        return $this->where("menu_id in({$ids})")->update($data);
	}


	public function getFatherMenuForOptions() {
		$menu_options_array=array("0"=>"无");
		$modules = model('#Module')->getAllModules();
		foreach($modules as $module){
			$list = $this->getListByModuleId($module['module_id'],'navibar');
			foreach ($list as $menu){
				$menu_options_array[$module['module_name']][$menu['menu_id']]=$menu['menu_name'];
			}
		}
		return $menu_options_array;
	}
	
	public function addMenu($data) {
		if(empty($data) || !is_array($data)){
            return false;
        }
        
        $validator = new \Lib\Validator($data);
        $validator->setRules([
            'menu_name' => ['required'=>true,'validate'=>'strip_tags,trim'],
            'menu_url' => ['required'=>true,'validate'=>'strip_tags,trim'],
            'module_id' => ['required'=>true,'validate'=>'absint'],
            'is_show' => ['default'=>1,'validate'=>'absint'],
            'online' => ['default'=>1,'validate'=>'absint'],
            'sort_order' => ['default'=>999,'validate'=>'absint'],
            'shortcut_allowed' => ['validate'=>'absint'],
            'menu_desc' => ['validate'=>'strip_tags,trim'],
            'menu_icon' => ['validate'=>'strip_tags,trim'],
            'father_menu' => ['validate'=>'absint'],
            'is_sys' => ['validate'=>'absint'],
        ])->setMessages([
            'menu_name' => ['required'=>'菜单名称不能为空','validate'=>'菜单名称填写不正确'],
            'menu_url' => ['required'=>'菜单链接不能为空','validate'=>'菜单链接填写不正确'],
            'module_id' => ['required'=>'所属模块ID不能为空','validate'=>'所属模块ID填写不正确'],
            'is_show' => ['validate'=>'是否在sidebar里出现填写不正确'],
            'online' => ['validate'=>'在线状态，0下线 1在线填写不正确'],
            'sort_order' => ['validate'=>'排序填写不正确'],
            'shortcut_allowed' => ['validate'=>'是否允许快捷访问填写不正确'],
            'menu_desc' => ['validate'=>'菜单描述填写不正确'],
            'menu_icon' => ['validate'=>'菜单图标填写不正确'],
            'father_menu' => ['validate'=>'上一级菜单填写不正确'],
            'is_sys' => ['validate'=>'是否系统菜单填写不正确'],
        ]);
    
        $data = $validator->validate();
        if(!$data){
            $this->error = $validator->getError();
            return false;
        }
        if($this->getMenuByName($data['menu_name'])){
        	$this->errlr = '要添加的菜单名称已存在';
        	return false;
        }
		return $this->insert($data);
	}
	
	public function getAllMenus($start =0 ,$page_size=20) {
		$result = $this->limit($start,$page_size)->select();
		$rebuild_list = rebuild_array_by('menu_id',$result);
		foreach($result as &$menu){
			if($menu['father_menu']>0){
				$father_menu = val($rebuild_list,$menu['father_menu']);
				$menu['father_menu_name'] = val($father_menu,'menu_name');
			}
		}
		return $result;
	}
	
	
	public function search($module_id,$menu_name,$start=0,$page_size=20,$sort='') {
		$limit ="";
		$where = " where 1";
		if($page_size){
			$limit ="limit $start,$page_size";
		}

		if($module_id>0){
			$where .= " and me.module_id=$module_id ";
		}
		if($menu_name!=""){
			$where .= " and me.menu_name like '%$menu_name%' ";
		}
		$order_by = empty($sort) ? 'order by me.module_id,me.menu_id' : 'order by me.'.$sort;
		$sql = "select * from ".$this->getTableName()." me left join ".model('#Module')->getTableName()." mo on me.module_id = mo.module_id $where {$order_by} $limit";
		$list=$this->db->query($sql)->fetchAll();
		if ($list) {
			$rebuild_list = rebuild_array_by('menu_id',$list);
			foreach($list as &$menu){
				if($menu['father_menu']>0 && ($father_menu = val($rebuild_list,$menu['father_menu']))){
					$menu['father_menu_name'] = $father_menu['menu_name'];
				}
			}
			return $list;
		}
		return array ();
	}

	public function searchCount($module_id,$menu_name) {
		$where = '1';
		if($module_id>0){
			$where .= " and module_id='{$module_id}'";
		}
		if($menu_name!=""){
			$where .= " and menu_name like '%$menu_name%'";
		}
		$num = $this->where($where)->count();
		return $num ? $num : 0;
	}
	
	public function delMenu($menu_id) {
		if (!$menu_id || (!is_numeric ($menu_id) && !is_array($menu_id))) {
			return false;
		}
		$ids = is_array($menu_id) ? join(',',$menu_id) : $menu_id;
		// 查找待删除的菜单里面，有没有父菜单
		$menus = $this->where("menu_id in({$ids})")->select();
		foreach ($menus as $key => $value) {
			if($value['father_menu']==0){
				foreach($menus as $menu){
					if($menu['father_menu']==$value['menu_id']){
						$this->error = '要删除的菜单含有父级菜单，并且其下有子菜单，不能被删除';
						return false;
					}
				}
			}
		}
		return $this->where("menu_id in({$ids})")->delete();
	}
	
	public function getMenuByName($name,$id=0){
		if(empty($name)){
			return [];
		}
		$condition = "menu_name='{$name}'";
		$id && $condition .= " and menu_id!={$id}";
		return $this->where($condition)->fetch();
	}
}