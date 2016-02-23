<?php
/*
Auth:Sang
Desc:表单数据验证类
Date:2014-11-01
*/
namespace Lib;
class Validator{
  protected $data=array(),$error,$rules=array(),$messages=array();
  
  public function __construct($data){
    $this->setData($data);
  }
  public function setData($data){
    if($data){
      $this->data=$data;
    }
    return $this;
  }
  
  public function setRules($rules){
    if(!empty($rules)){
      $this->rules=$rules;
    }
    return $this;
  }
  
  public function setMessages($messages){
    if(!empty($messages)){
      $this->messages=$messages;
    }
    return $this;
  }
  
  public function getError(){
    return $this->error;
  }
  
  public function validate(){
    if(!$this->rules){
      return $this->data;
    }
    $data=array();
    foreach($this->rules as $key=>$rules){
      if(isset($rules['required']) && ($rule=$rules['required']) && ((function_exists($rule) && $rule()===true) || $rule===true)){
        if(!isset($this->data[$key])){
          $this->setError($key, 'required');
          return false;
        }
        unset($rules['required']);
      }elseif(!isset($this->data[$key])){
        if(isset($rules['default'])){
          $data[$key] = $rules['default'];
          unset($rules['default']);
        }
        continue;
      }
      if(isset($rules['default'])){
        unset($rules['default']);
      }
      if(empty($rules)){
        $data[$key]=$this->data[$key];
        continue;
      }
      foreach($rules as $rule_name=>$rule){
        $rule_name_lower = strtolower($rule_name);
        if(in_array($rule_name_lower, ['min','max','minlen','maxlen'])){
          continue;
        }
        if(!empty($rule)){
          $rule=explode(',',$rule);
          foreach($rule as $r){
            if($this->data[$key] !== '' && $this->data[$key] !== null){
              if(($value=$r($this->data[$key]))===false){
                $this->setError($key, $rule_name);
                return false;
              }else{
                $this->data[$key]=$value;
                $data[$key]=$value;
              }
            }else{
              $data[$key]='';
            }
          }
        }else{
          $data[$key]=val($this->data,$key,false);
        }
      }
      if(isset($rules['min']) && $data[$key]<$rules['min']){
        $this->setError($key, 'min');
        return false;
      }
      if(isset($rules['max']) && $data[$key]>$rules['max']){
        $this->setError($key, 'max');
        return false;
      }
      if(isset($rules['minlen']) && mb_strlen($data[$key])<$rules['minlen']){
        $this->setError($key, 'minlen');
        return false;
      }
      if(isset($rules['maxlen']) && mb_strlen($data[$key])>$rules['maxlen']){
        $this->setError($key, 'maxlen');
        return false;
      }
    }
    return $data;
  }
  
  protected function setError($key,$rule){
    if(!isset($this->messages[$key][$rule])){
      $this->error='no such field:'.$key;
    }else{
      $this->error=$this->messages[$key][$rule];
    }
  }
}