<?php
/*
Auth:Sang
Desc:地区数据-区县模型
Date:2014-10-29
*/
namespace Mod;
use Lib\Model;
class District extends Model{

	public function getDistrictById($id){
		if(empty($id) || !is_numeric($id)){
			return false;
		}
		return $this->where("id={$id}")->fetch();
	}
	
	public function searchCount($name,$fid){
		$where = '1';
		if(!empty($name)){
			$where .= " and name like '%{$name}%'";
		}
		if(!empty($fid)){
			$where .= " and father = {$fid}";
		}
		return $this->where($where)->count();
	}

	public function search($name,$fid,$offset=0,$size=25){
		$where = '1';
		if(!empty($name)){
			$where .= " and name like '%{$name}%'";
		}
		if(!empty($fid)){
			$where .= " and father = {$fid}";
		}
		return $this->where($where)->limit($offset,$size)->select();
	}

	public function delById($id){
		if(empty($id) || !is_numeric($id)){
			return false;
		}
		return $this->where("id={$id}")->delete();
	}

	public function updateDistrict($id,$data){
		if(empty($id) || !is_numeric($id) || empty($data) || !is_array($data)){
			return false;
		}
		return $this->where("id={$id}")->update($data);
	}

	public function addDistrict($data){
		if(empty($data) || !is_array($data)){
			return false;
		}
		return $this->insert($data);
	}

	public function getDistrictBySn($sn){
		if(empty($sn) || !is_numeric($sn)){
			return false;
		}
		return $this->where("sn='{$sn}'")->fetch();
	}

	public function getDistrictByName($name){
		if(empty($name) || !is_string($name)){
			return false;
		}
		return $this->where("name='{$name}'")->fetch();
	}

	public function getDistrictByFather($fid){
		if(empty($fid) || !is_numeric($fid)){
			return false;
		}
		return $this->where("father='{$fid}'")->select();
	}

	public function getOption($selected='',$fid=0){
		$where = " father='{$fid}'";
		$result = $this->where($where)->order('id asc')->select();
		
		$str = '<select name="district" id="district" style="width:150px;margin-right:5px">';
		$str .= '<option value="0">请选择</option>';
		foreach($result as $item){
			$str .= "<option value=\"{$item['sn']}\"".($selected == $item['sn'] ? ' selected' : '').">{$item['name']}</option>";
		}
		$str .= '</select>';

		$mod = model('#City');
		$city = $mod->getCityBySn($fid);
		$str = $mod->getOption($city['sn'],$city['father']).$str;

		return $str;
	}
}