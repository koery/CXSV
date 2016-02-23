<?php
/*
Auth:Sang
Desc:邮件模板模型
Date:2014-10-27
*/
namespace Mod;
use Lib\Model;
class MailTpl extends Model{
    public function addMailTpl($data){
        if(empty($data) || !is_array($data)){
            return false;
        }
        
        $validator = new \Lib\Validator($data);
        $validator->setRules([
            'name' => ['required'=>true,'validate'=>'strip_tags,trim'],
            'content' => ['required'=>true,'validate'=>'htmlspecialchars'],
        ])->setMessages([
            'name' => ['required'=>'模板名称不能为空','validate'=>'模板名称不合法'],
            'content' => ['required'=>'模板内容不能为空','validate'=>'模板内容不合法'],
        ]);
    
        $data = $validator->validate();
        if(!$data){
            $this->error = $validator->getError();
            return false;
        }

        //名称是否已存在
        if($this->getMailTplByName($data['name'])){
            $this->error = '该模板名称已存在';
            return false;
        }
    
        return $this->insert($data);
    }

    public function delMailTpl($id){
        if(empty($id) || (!is_numeric($id) && !is_array())){
            return false;
        }
        $ids = is_array($id) ? join(',',$id) : $id;
        return $this->where("tpl_id in({$ids})")->delete();
    }

    public function updateMailTpl($id,$data){
        if(empty($id) || (!is_numeric($id) && !is_array($id)) || empty($data) || !is_array($data)){
            return false;
        } 
        
        $validator = new \Lib\Validator($data);
        $validator->setRules([
            'name' => ['required'=>true,'validate'=>'strip_tags,trim'],
            'content' => ['required'=>true,'validate'=>'htmlspecialchars'],
        ])->setMessages([
            'name' => ['required'=>'模板名称不能为空','validate'=>'模板名称不合法'],
            'content' => ['required'=>'模板内容不能为空','validate'=>'模板内容不合法'],
        ]);
    
        $data = $validator->validate();
        if(!$data){
            $this->error = $validator->getError();
            return false;
        }
        
        $ids = is_array($id) ? join(',',$id) : $id;
        if($this->where("tpl_id not in({$ids}) and `name`='{$data['name']}'")->count()){
            $this->error = '该模板名称已存在';
            return false;
        }
        return $this->where("tpl_id in({$ids})")->update($data);
    }

    public function getMailTplById($id){
        if(empty($id) || !is_numeric($id)){
            return false;
        }
        return $this->where("tpl_id={$id}")->fetch();
    }

    public function getMailTplByName($name){
        if(empty($name) || !is_string($name)){
            return false;
        }
        return $this->where("`name`='{$name}'")->fetch();
    }

    public function search($condition,$offset=0,$size=20,$order='tpl_id desc'){
        return $this->where($condition)->order($order)->limit($offset,$size)->select();
    }

    public function searchCount($condition){
        return $this->where($condition)->count();
    }
}