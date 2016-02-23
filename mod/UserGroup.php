<?php
/*
Auth:Sang
Desc:后台用户权限组模型
Date:2014-10-27
*/
namespace Mod;
use Lib\Model;
class UserGroup extends Model{
	public function _init(){
		if(!$this->tableExists($this->getTableName())){
			$sql = <<<eof
CREATE TABLE `{$this->getTableName()}` (
  `group_id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '分组ID',
  `group_name` char(32) DEFAULT '' COMMENT '分组名称',
  `group_role` text COMMENT '权限',
  `owner_id` int(11) unsigned DEFAULT 0 COMMENT '创建人ID',
  `group_desc` char(64) DEFAULT '' COMMENT '分组描述',
  PRIMARY KEY (`group_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;
eof;
			if($this->db->query($sql)){
				$this->addGroup([
					'group_id' => 1,
					'owner_id' => 1,
					'group_name' => '超级管理员',
					'group_desc' => '万能的管理组,能操纵一切',
				]);
			}
		}
	}
	public function getGroupById($group_id) {
		if (! $group_id || ! is_numeric ( $group_id )) {
			return false;
		}
		return $this->where("group_id={$group_id}")->fetch();
	}

	//列表 
	public function getAllGroup() {
		$sql="select group_id, group_name, group_role, owner_id , group_desc ,u.user_name as owner_name from ".$this->getTableName()." g left join ".model('#User')->getTableName()." u on g.owner_id =  u.user_id order by g.group_id";
		return $this->db->query($sql)->fetchAll();
	}
	
	public function addGroup($data) {
		if(empty($data) || !is_array($data)){
            return false;
        }
        
        $validator = new \Lib\Validator($data);
        $validator->setRules([
            'group_name' => ['required'=>true,'validate'=>'strip_tags,trim'],
            'group_role' => ['validate'=>'strip_tags,trim'],
            'owner_id' => ['validate'=>'absint'],
            'group_desc' => ['validate'=>'strip_tags,trim'],
        ])->setMessages([
            'group_name' => ['required'=>'分组名称不能为空','validate'=>'分组名称填写不正确'],
            'group_role' => ['validate'=>'权限填写不正确'],
            'owner_id' => ['validate'=>'创建人ID填写不正确'],
            'group_desc' => ['validate'=>'分组描述填写不正确'],
        ]);
    
        $data = $validator->validate();
        if(!$data){
            $this->error = $validator->getError();
            return false;
        }
        if($this->getGroupByName($data['group_name'])){
        	$this->error = '要添加的分组名已存在';
        	return false;
        }
        return $this->insert($data);
	}
	
	public function getGroupByName($group_name,$id=0) {
		if ( $group_name == "" ) {
			return false;
		}
		$condition = "group_name='{$group_name}'";
		$id && $condition .= " and group_id!={$id}";
		return $this->where($condition)->fetch();
	}
	
	public function updateGroupInfo($id,$data) {
		if(empty($id) || (!is_numeric($id) && !is_array($id)) || empty($data) || !is_array($data)){
            return false;
        }
        
        $validator = new \Lib\Validator($data);
        $validator->setRules([
            'group_name' => ['validate'=>'strip_tags,trim'],
            'group_role' => ['validate'=>'strip_tags,trim'],
            'group_desc' => ['validate'=>'strip_tags,trim'],
        ])->setMessages([
            'group_name' => ['validate'=>'分组名称填写不正确'],
            'group_role' => ['validate'=>'权限填写不正确'],
            'group_desc' => ['validate'=>'分组描述填写不正确'],
        ]);
    
        $data = $validator->validate();
        if(!$data){
            $this->error = $validator->getError();
            return false;
        }
    	if(isset($data['group_name']) && $this->getGroupByName($data['group_name'],$id)){
        	$this->error = '要修改的分组名已存在';
        	return false;
        }
        $ids = is_array($id) ? join(',',$id) : $id;
        return $this->where("group_id in({$ids})")->update($data);
	}
	
	public function delGroup($id) {
		if(empty($id) || (!is_numeric($id) && !is_array($id))){
            return false;
        }
        $id = string2array($id);
        foreach($id as $item){
        	if($item==1){
        		$this->error = '不能删除内置的超级管理组';
        		return false;
        	}
        }
        $ids = is_array($id) ? join(',',$id) : $id;
        return $this->where("group_id in({$ids})")->delete();
	}
	
	public function getGroupForOptions() {
		$group_list = $this->getAllGroup ();
		foreach ( $group_list as $group ) {
			$group_options_array [$group ['group_id']] = $group ['group_name'];
		}
		return $group_options_array;
	}
	
	public function getGroupUsers($group_id) {
		$sql="select  group_id, group_name, group_role, owner_id , group_desc ,u.user_id as user_id,u.user_name as user_name,u.real_name as real_name from ".$this->getTableName()." g,".model('#User')->getTableName()." u where g.group_id = $group_id and g.group_id = u.user_group order by g.group_id,u.user_id";
		return $this->db->query ($sql)->fetchAll();
	}

	public function hasUser($group_id){
		return model('#User')->where("user_group={$group_id}")->count()>0;
	}

}