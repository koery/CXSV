<?php
/*
Auth:Sang
Desc:后台菜单模块模型
Date:2014-10-27
*/
namespace Mod;
use Lib\Model;
class Module extends Model{
	public function _init(){
		if(!$this->tableExists($this->getTableName())){
			$sql = <<<eof
CREATE TABLE `{$this->getTableName()}` (
  `module_id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '模块ID',
  `module_name` char(50) NOT NULL DEFAULT '' COMMENT '模块名称',
  `module_url` varchar(128) NOT NULL DEFAULT '' COMMENT '模块入口',
  `module_sort` int(11) unsigned NOT NULL DEFAULT 1 COMMENT '排序',
  `module_desc` varchar(120) NOT NULL DEFAULT '' COMMENT '模块描述',
  `module_icon` char(32) NOT NULL DEFAULT 'icon-th' COMMENT '菜单模块图标',
  `online` int(11) UNSIGNED NOT NULL DEFAULT 1 COMMENT '模块是否在线',
  PRIMARY KEY (`module_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;
eof;
			$this->db->query($sql);
		}
	}
	public function getAllModules($is_online=null){
	  	$where = "";
	  	if(isset($is_online)){
	  		$where.="online={$is_online}";
		}
		$list = $this->where($where)->order("module_sort asc,module_id asc")->select();
		if ($list) {
			return $list;
		}
		return array ();
    }

    public function getModuleById($module_id) {
		if (! $module_id || ! is_numeric ( $module_id )) {
			return false;
		}
		$list = $this->where("module_id={$module_id}")->fetch();
		if ($list) {
			return $list;
		}
		return array ();
	}

	public function getModuleForOptions() {
		$module_options_array = array ();
		$module_list = $this->getAllModules (1);
		
		foreach ( $module_list as $module ) {
			$module_options_array [$module ['module_id']] = $module ['module_name'];
		}
		
		return $module_options_array;
	}

	public function updateModuleInfo($id,$data) {
        if(empty($id) || (!is_numeric($id) && !is_array($id)) || empty($data) || !is_array($data)){
            return false;
        }
        
	  	$validator = new \Lib\Validator($data);
	  	$validator->setRules([
	        'module_name' => ['validate'=>'strip_tags,trim'],
	        'module_url' => ['validate'=>'strip_tags,trim'],
	        'module_sort' => ['validate'=>'absint'],
	        'module_desc' => ['validate'=>'strip_tags,trim'],
	        'module_icon' => ['validate'=>'strip_tags,trim'],
	        'online' => ['validate'=>'absint'],
	    ])->setMessages([
	        'module_name' => ['validate'=>'模块名称填写不正确'],
	        'module_url' => ['validate'=>'模块入口填写不正确'],
	        'module_sort' => ['validate'=>'排序填写不正确'],
	        'module_desc' => ['validate'=>'模块描述填写不正确'],
	        'module_icon' => ['validate'=>'菜单模块图标填写不正确'],
	        'online' => ['validate'=>'模块是否在线填写不正确'],
	    ]);
    	
        $data = $validator->validate();
        if(!$data){
            $this->error = $validator->getError();
            return false;
        }
     	$module = $this->getModuleByName($data['module_name'],$id);
	    if($module){
	    	$this->error = '该模块名称已存在';
	    	return false;
	    }

        $ids = is_array($id) ? join(',',$id) : $id;
        return $this->where("module_id in({$ids})")->update($data);
	}

	public function addModule($data) {
		if(empty($data) || !is_array($data)){
            return false;
        }
        
	    $validator = new \Lib\Validator($data);
	    $validator->setRules([
	        'module_name' => ['required'=>true,'validate'=>'strip_tags,trim'],
	        'module_url' => ['required'=>true,'validate'=>'strip_tags,trim'],
	        'module_sort' => ['required'=>true,'validate'=>'absint'],
	        'module_desc' => ['required'=>true,'validate'=>'strip_tags,trim'],
	        'module_icon' => ['required'=>true,'validate'=>'strip_tags,trim'],
	        'online' => ['required'=>true,'validate'=>'absint'],
	    ])->setMessages([
	        'module_name' => ['required'=>'模块名称不能为空','validate'=>'模块名称填写不正确'],
	        'module_url' => ['required'=>'模块入口不能为空','validate'=>'模块入口填写不正确'],
	        'module_sort' => ['required'=>'排序不能为空','validate'=>'排序填写不正确'],
	        'module_desc' => ['required'=>'模块描述不能为空','validate'=>'模块描述填写不正确'],
	        'module_icon' => ['required'=>'菜单模块图标不能为空','validate'=>'菜单模块图标填写不正确'],
	        'online' => ['required'=>'模块是否在线不能为空','validate'=>'模块是否在线填写不正确'],
	    ]);
	    
	    $data = $validator->validate();
	    if(!$data){
	        $this->error = $validator->getError();
	        return false;
	    }
	    $module = $this->getModuleByName($data['module_name']);
	    if($module){
	    	$this->error = '该模块名称已存在';
	    	return false;
	    }
        return $this->insert($data);
	}

	public function getModuleByName($module_name,$id=0) {
		if (!$module_name) {
			return false;
		}
		$condition = "module_name='{$module_name}'";
		$id && $condition .= " and module_id!={$id}";
		return $this->where($condition)->fetch();
	}

	public function getModuleMenu($module_id) {
		if (! $module_id || ! is_numeric ( $module_id )) {
			return false;
		}
		$sql="select * from ".$this->getTableName() ." m,".model('#MenuUrl')->getTableName()." u where m.module_id = $module_id and m.module_id = u.module_id order by m.module_id,u.menu_id";
		return $this->db->query($sql)->fetch();
	}

	public function delModule($module_ids) {
		if (empty($module_ids)) {
			return false;
		}
		is_array($module_ids) && $module_ids = join(',',$module_ids);
		return $this->where("module_id in({$module_ids})")->delete();
	}
}