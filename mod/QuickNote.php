<?php
/*
Auth:Sang
Desc:快捷标签模型
Date:2014-10-27
*/
namespace Mod;
use \Lib\Model;
class QuickNote extends Model{
	// 查询字段
	private $columns = 'note_id, note_content, owner_id';

	//列表 
	public function getNotes($start ='' ,$page_size=25) {
		$limit ="";
		if($page_size){
			$limit =" limit $start,$page_size ";
		}
		$sql="select ".$this->columns." ,coalesce(u.user_name,'已删除') as owner_name from ".$this->getTableName()." q left join ".model('#User','adm_')->getTableName()." u on q.owner_id =  u.user_id order by q.note_id desc $limit";
		$list = $this->db->query($sql)->fetchAll();
		if ($list) {
			return $list;
		}
		return array ();		
	}
	
	public function addNote($note_data) {
		if (! $note_data || ! is_array ( $note_data )) {
			return false;
		}
		return $this->insert($note_data);
	}

	public function getNoteById($note_id) {
		if (! $note_id || ! is_numeric ( $note_id )) {
			return false;
		}
		return $this->where("note_id={$note_id}")->fetch();
	}
	
	public function getRandomNote() {
		//获取最新一条
		return $this->order('note_id desc')->limit(1)->fetch();
		$sql="select min(note_id), max(note_id) from ".$this->getTableName();
		$list = $this->db->query($sql)->fetch();
		if ($list) {
			$note_id=rand($list['min(note_id)'],$list['max(note_id)']);
			return $this->where("note_id=$note_id")->fetch();
		}
		return array();
	}
	
	public function searchCount($condition = '') {
		return $this->where($condition)->count ();
	}
	
	public function updateNote($note_id,$note_data) {
		if (! $note_data || ! is_array ( $note_data )) {
			return false;
		}
		return $this->where("note_id={$note_id}")->update($note_data);
	}
	
	public function delNote($note_id) {
		if (! $note_id || ! is_numeric ( $note_id )) {
			return false;
		}
		return $this->where("note_id={$note_id}")->delete();
	} 
}