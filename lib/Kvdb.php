<?php
/*
Auth:Sang
Desc:KV持久化缓存类接口
Date:2014-11-01
*/
namespace Lib;
class Kvdb{
    private $table_name = 'kvdata';
    public function __construct($db_table){
        !empty($db_table) && $this->table_name = $db_table;
        global $php;
        $php->db->query("show tables like '{$this->table_name}'");
        if(!$php->db->getNumRows()){
        	$sql = "CREATE TABLE `{$this->table_name}` (".
  					"`name` char(50) COLLATE utf8_unicode_ci NOT NULL,".
 					"`value` mediumblob,".
  					"`type` enum('object','string') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'string',".
 					"PRIMARY KEY (`name`)".
					") ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
        	$php->db->query($sql);
        }
    }

    public function get($name){
        global $php;
        $value = cache($this->table_name.$name);
        if($value===false){
            $result = $php->db->query("select value,type from {$this->table_name} where `name` = :name",array('name'=>$name));
            $result ? $data = $result->fetch() : $data = [];
            if(isset($data['value'])){
                $value = $data['type'] == 'object' ? unserialize($data['value']) : $data['value'];
                cache($this->table_name.$name,$value);
            }
        }
        return $value;
    }

    public function set($name,$value){
        global $php;
        if($value===null){
           return $this->delete($name);
        }
        cache($this->table_name.$name,$value);
        $type = !is_string($value) && !is_numeric($value) ? 'object' : 'string';
        $value = $type == 'object' ? serialize($value) : $value;
        return $php->db->query("replace into {$this->table_name}(`name`,`value`,`type`) values(:name,:value,:type)",array('name'=>$name,'value'=>$value,'type'=>$type))->getNumRows();
    }

    public function delete($name){
        global $php;
        cache($this->table_name.$name,null);
        return $php->db->query("delete from {$this->table_name} where `name`=:name",array('name'=>$name))->getNumRows();
    }
}