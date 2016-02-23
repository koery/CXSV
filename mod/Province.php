<?php
/*
Auth:Sang
Desc:地区数据-省份模型
Date:2014-10-27
*/
namespace Mod;
use Lib\Model;
class Province extends Model{

	public function getProvinceById($id){
		if(empty($id) || !is_numeric($id)){
			return false;
		}
		return $this->where("id={$id}")->fetch();
	}
	
	public function searchCount($name){
		$where = '1';
		if(!empty($name)){
			$where .= " and name like '%{$name}%'";
		}
		return $this->where($where)->count();
	}

	public function search($name,$fid,$offset=0,$size=25){
		$where = '1';
		if(!empty($name)){
			$where .= " and name like '%{$name}%'";
		}
		return $this->where($where)->limit($offset,$size)->select();
	}

	public function delById($id){
		if(empty($id) || !is_numeric($id)){
			return false;
		}
		return $this->where("id={$id}")->delete();
	}

	public function updateProvince($id,$data){
		if(empty($id) || !is_numeric($id) || empty($data) || !is_array($data)){
			return false;
		}
		return $this->where("id={$id}")->update($data);
	}

	public function addProvince($data){
		if(empty($data) || !is_array($data)){
			return false;
		}
		return $this->insert($data);
	}

	public function getProvinceBySn($sn){
		if(empty($sn) || !is_numeric($sn)){
			return false;
		}
		return $this->where("sn='{$sn}'")->fetch();
	}

	public function getProvinceByName($name){
		if(empty($name) || !is_string($name)){
			return false;
		}
		return $this->where("name='{$name}'")->fetch();
	}

	public function getOption($selected=''){
		$result = $this->order('id asc')->select();
		$str = '<select name="province" style="width:150px;margin-right:5px" id="province">';
		$str .= '<option value="0">请选择</option>';
		foreach($result as $item){
			$str .= "<option value=\"{$item['sn']}\"".($selected == $item['sn'] ? ' selected' : '').">{$item['name']}</option>";
		}
		$str .= '</select>';
		return $str;
	}
}