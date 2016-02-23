<?php
/*
Auth:Sang
Desc:后台用户权限模型
Date:2014-10-29
*/
namespace Mod;
use Lib\Model;
class GroupRole extends Model{
	public function getGroupRoles() {
		$data = model('#Module')->getAllModules (1);
		//用户组的权限
		foreach ( $data as $k => $module ) {
			$list = model('#MenuUrl')->getListByModuleId ($module ['module_id'] ,"role");
			foreach ( $list as $menu ) {
				$data [$k] ['menu_info'][$menu ['menu_id']] = $menu;
			}
		}
		
		return $data;
	}

	public function getGroupRolesByGroupRole($group_role_array){
		if (! $group_role_array || ! is_array ( $group_role_array)) {
			return false;
		}
		$roles = join(',',$group_role_array);
		$menus = model('#MenuUrl')->alias('mu')->leftJoin(model('#Module')->getTableName().' m on(m.module_id=mu.module_id)')->fields('mu.*,m.module_name')->where("menu_id in({$roles})")->select();
		$result = [];
		foreach ($menus as $key => $menu) {
			$result[$menu['module_id']]['menu_info'][$menu['menu_id']] = $menu;
			$result[$menu['module_id']]['module_id'] = $menu['module_id'];
			$result[$menu['module_id']]['module_name'] = $menu['module_name'];
		}
		return $result;
	}
	
	public function getGroupForOptions() {
		$group_list = model('#UserGroup')->getAllGroup ();
		
		foreach ( $group_list as $group ) {
			$group_options_array [$group ['group_id']] = $group ['group_name'];
		}
		
		return $group_options_array;
	}
}