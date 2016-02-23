<?php
/*
Auth:Sang
Desc:挂件数据模型
Date:2014-10-27
*/

/*
DROP TABLE IF EXISTS `widget_data`;
CREATE TABLE `widget_data` (
  `data_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id` char(60) NOT NULL DEFAULT '' COMMENT '挂件唯一标识',
  `city_id` int(5) unsigned NOT NULL DEFAULT '0' COMMENT '分站ID',
  `data` mediumtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL COMMENT '挂件JSON数据',
  `update_time` int(10) unsigned DEFAULT NULL COMMENT '最后更新时间',
  PRIMARY KEY (`data_id`),
  KEY `id_city_id` (`id`,`city_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='挂件数据表';
*/
namespace Mod;
use Lib\Model;
class WidgetData extends Model{
    public function addWidgetData($data){
        if(empty($data) || !is_array($data)){
            return false;
        }
        
        $validator = new \Lib\Validator($data);
        $validator->setRules([
            'id' => ['required'=>true,'validate'=>'trim'],
            'city_id' => ['required'=>true,'validate'=>'absint'],
            'data' => ['required'=>true],
            'data_type' => ['required'=>true],
            'update_time' => ['required'=>true,'validate'=>'longint'],
        ])->setMessages([
            'id' => ['required'=>'挂件唯一标识不能为空','validate'=>''],
            'city_id' => ['required'=>'分站ID不能为空','validate'=>''],
            'data' => ['required'=>'挂件JSON数据不能为空','validate'=>''],
            'data_type' => ['required'=>'数据类型不能为空'],
            'update_time' => ['required'=>'更新时间不能为空','validate'=>''],
        ]);
    
        $data = $validator->validate();
        if(!$data){
            $this->error = $validator->getError();
            return false;
        }
    
        return $this->insert($data);
    }

    public function delWidgetData($widget_id){
        if(empty($widget_id)){
            return false;
        }
        return $this->where("widget_id='{$widget_id}'")->delete();
    }

    public function updateWidgetData($widget_id,$city_id,$data){
        if(empty($widget_id) || empty($city_id) || !is_numeric($city_id) || empty($data)){
            return false;
        } 
        
        $validator = new \Lib\Validator($data);
        $validator->setRules([
            'data' => ['required'=>true],
            'update_time' => ['required'=>true,'validate'=>'longint'],
        ])->setMessages([
            'data' => ['required'=>'挂件JSON数据不能为空','validate'=>''],
            'update_time' => ['required'=>'更新时间不能为空','validate'=>''],
        ]);
    
        $data = $validator->validate();
        if(!$data){
            $this->error = $validator->getError();
            return false;
        }
    
        return $this->where("id='{$widget_id}' and city_id={$city_id}")->update($data);
    }

    public function getWidgetDataById($widget_id,$city_id){
        if(empty($widget_id) || empty($city_id) || !is_numeric($city_id)){
            return ['data'=>[]];
        }
        $data = $this->where("id='{$widget_id}' and city_id={$city_id}")->fetch();
        if(empty($data)){
            return ['data'=>[]];
        }
        $data['data'] = $data['data_type'] == 'array' ? json_decode($data['data'],true) : $data['data'];
        $data['data'] || $data['data'] = [];
        return $data;
    }

    public function saveData($widget_id,$city_id,$data){
        if(empty($widget_id) || empty($city_id) || !is_numeric($city_id) || empty($data)){
            return false;
        }
        $data = [
            'id' => $widget_id,
            'city_id' => $city_id,
            'data' => is_array($data) ? json_encode($data,JSON_UNESCAPED_UNICODE) : $data,
            'data_type' => is_array($data) ? 'array' : 'string',
            'update_time' => time(),
        ];
        if($this->searchCount("id='{$widget_id}' and city_id={$city_id}")>0){
            return $this->updateWidgetData($widget_id,$city_id,$data);
        }else{
            return $this->addWidgetData($data);
        }
    }

    public function search($condition,$offset=0,$size=20,$order='data_id desc'){
        return $this->where($condition)->order($order)->limit($offset,$size)->select();
    }

    public function searchCount($condition){
        return $this->where($condition)->count();
    }
}