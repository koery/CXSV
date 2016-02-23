<?php
/*
Auth:Sang
Desc:支付方式模型
Date:2014-10-27
*/
namespace Mod;
use Lib\Model;
class Payment extends Model{
    public function addPayment($data){
        if(empty($data) || !is_array($data)){
            return false;
        }
        
        $validator = new \Lib\Validator($data);
        $validator->setRules([
            'payment_code' => ['required'=>true,'validate'=>'trim'],
            'biz_id' => ['required'=>true,'validate'=>'trim'],
            'payment_name' => ['required'=>true,'validate'=>'trim'],
            'config' => ['required'=>true,'validate'=>'trim'],
            'is_online' => ['validate'=>'intval,abs'],
            'enabled' => ['required'=>true,'validate'=>'intval,abs'],
            'sort_order' => ['validate'=>'intval,abs'],
        ])->setMessages([
            'payment_code' => ['required'=>'接口代码不能为空','validate'=>''],
            'payment_name' => ['required'=>'接口名称不能为空','validate'=>''],
            'config' => ['required'=>'接口配置不能为空','validate'=>''],
            'enabled' => ['required'=>'是否启用不能为空','validate'=>''],
        ]);
    
        $data = $validator->validate();
        if(!$data){
            $this->error = $validator->getError();
            return false;
        }
        return $this->insert($data);
    }

    public function delPayment($biz_id,$code){
        if($biz_id==='' || empty($code)){
            return false;
        }
        return $this->where("biz_id='{$biz_id}' and payment_code={$code}")->delete();
    }

    public function updatePayment($biz_id,$code,$data){
        if($biz_id==='' || empty($code) || empty($data)){
            return false;
        }
        $validator = new \Lib\Validator($data);
        $validator->setRules([
            'payment_name' => ['required'=>true,'validate'=>'trim'],
            'config' => ['required'=>true,'validate'=>'trim'],
            'is_online' => ['validate'=>'intval,abs'],
            'enabled' => ['required'=>true,'validate'=>'intval,abs'],
            'sort_order' => ['validate'=>'intval,abs'],
        ])->setMessages([
            'payment_name' => ['required'=>'接口名称不能为空','validate'=>''],
            'config' => ['required'=>'接口配置不能为空','validate'=>''],
            'enabled' => ['required'=>'是否启用不能为空','validate'=>''],
        ]);
    
        $data = $validator->validate();
        if(!$data){
            $this->error = $validator->getError();
            return false;
        }
        return $this->where("biz_id = '{$biz_id}' and payment_code='{$code}'")->update($data);
    }

    public function disablePayment($biz_id,$code){
        if($biz_id==='' || empty($code)){
            return false;
        }
        return $this->where("biz_id='{$biz_id}' and payment_code='{$code}'")->update(['enabled'=>0]);
    }

    public function getPaymentById($id){
        if(empty($id) || !is_numeric($id)){
            return false;
        }
        return $this->where("payment_id={$id}")->fetch();
    }

    public function getPaymentByCode($biz_id,$code){
        if($biz_id==='' || empty($code)){
            return false;
        }
        return $this->where("biz_id='{$biz_id}' and payment_code='{$code}'")->fetch();
    }

    public function search($condition,$offset=0,$size=20,$order='payment_id desc'){
        return $this->where($condition)->order($order)->limit($offset,$size)->select();
    }

    public function searchCount($condition){
        return $this->where($condition)->count();
    }

    public function getConfig($biz_id,$code){
        if($biz_id==='' || empty($code)){
            return [];
        }
        $payment = $this->fields('config')->where("biz_id='{$biz_id}' and payment_code='{$code}'")->fetch();
        if($payment && !empty($payment['config'])){
            return json_decode($payment['config'],true);
        }
        return [];
    }
}