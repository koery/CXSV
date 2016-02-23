<?php
/*
Auth:Sang
Desc:分站数据模型
Date:2014-10-27
*/
namespace Mod;
use Lib\Model;
use Lib\Ip\Ip;
class Subdomain extends Model{
    const CACHE_KEY = 'all_sub_domain';
    public function addSubdomain($data){
        if(empty($data) || !is_array($data)){
            return false;
        }
        
        $validator = new \Lib\Validator($data);
        $validator->setRules([
            'name' => ['required'=>true,'validate'=>'strip_tags,trim'],
            'ename' => ['required'=>true,'validate'=>'strip_tags,trim'],
            'display' => ['required'=>true,'validate'=>'absint'],
            'sort_order' => ['required'=>true,'validate'=>'absint'],
            'template' => ['required'=>true,'validate'=>'strip_tags,trim'],
            'is_default' => ['required'=>true,'validate'=>'absint'],
        ])->setMessages([
            'name' => ['required'=>'城市名不能为空','validate'=>''],
            'ename' => ['required'=>'二级域名不能为空','validate'=>''],
            'display' => ['required'=>'是否显示不能为空','validate'=>''],
            'sort_order' => ['required'=>'排序不能为空','validate'=>''],
            'template' => ['required'=>'分站模板不能为空','validate'=>''],
            'is_default' => ['required'=>'是否默认不能为空','validate'=>''],
        ]);
    
        $data = $validator->validate();
        if(!$data){
            $this->error = $validator->getError();
            return false;
        }
        cache(self::CACHE_KEY,null);
        return $this->insert($data);
    }

    public function delSubdomain($id){
        if(empty($id) || (!is_numeric($id) && !is_array($id))){
            return false;
        }
        $ids = is_array($id) ? join(',',$id) : $id;
        cache(self::CACHE_KEY,null);
        return $this->where("id in({$ids})")->delete();
    }

    public function updateSubdomain($id,$data){
        if(empty($id) || (!is_numeric($id) && !is_array($id)) || empty($data) || !is_array($data)){
            return false;
        } 
        
        $validator = new \Lib\Validator($data);
        $validator->setRules([
            'name' => ['required'=>true,'validate'=>'strip_tags,trim'],
            'ename' => ['required'=>true,'validate'=>'strip_tags,trim'],
            'display' => ['required'=>true,'validate'=>'absint'],
            'sort_order' => ['required'=>true,'validate'=>'absint'],
            'template' => ['required'=>true,'validate'=>'strip_tags,trim'],
            'is_default' => ['required'=>true,'validate'=>'absint'],
        ])->setMessages([
            'name' => ['required'=>'城市名不能为空','validate'=>''],
            'ename' => ['required'=>'二级域名不能为空','validate'=>''],
            'display' => ['required'=>'是否显示不能为空','validate'=>''],
            'sort_order' => ['required'=>'排序不能为空','validate'=>''],
            'template' => ['required'=>'分站模板不能为空','validate'=>''],
            'is_default' => ['required'=>'是否默认不能为空','validate'=>''],
        ]);
    
        $data = $validator->validate();
        if(!$data){
            $this->error = $validator->getError();
            return false;
        }
    
        $ids = is_array($id) ? join(',',$id) : $id;
        cache(self::CACHE_KEY,null);
        return $this->where("id in({$ids})")->update($data);
    }

    public function getSubdomainById($id){
        if(empty($id) || !is_numeric($id)){
            return false;
        }
        return $this->get($id);
    }

    public function getSubdomainByName($name){
        if(empty($name)){
            return false;
        }
        $data = $this->getSubDomainByCache();
        foreach ($data as $key => $item) {
            if($item['name'] == $name){
                return $item;
            }
        }
        return [];
    }

    public function getSubdomainByEname($ename){
        if(empty($ename)){
            return false;
        }
        $data = $this->getSubDomainByCache();
        foreach ($data as $key => $item) {
            if($item['ename'] == $ename){
                return $item;
            }
        }
        return [];
    }

    public function getSubdomainByDom(){
        $host = $_SERVER['HTTP_HOST'];
        if(is_ip($host)){
            return $this->getDefaultSubdomain();
        }

        list($tag,$_) = explode('.',$host,2);
        if($tag == 'www'){
            if($dom_id = $this->getSubdomainCookie()){
                $domain = $this->getSubDomainById($dom_id);
                if(empty($domain)){
                    $domain = $this->getDefaultSubdomain();
                }
            }else{
                $ipquery = Ip::getInstance();
                $location = $ipquery->getLocation(get_client_ip());
                if($domain = $this->getSubdomainByName(substr($location['country'],-1))){

                }else{
                    $domain = $this->getDefaultSubdomain();
                }
            }
        }else{
            $domain = $this->getSubdomainByEname($tag);
            empty($domain) && $domain = $this->getDefaultSubdomain();
        }
        $dom_id = $this->getSubdomainCookie();
        if(empty($dom_id) || $dom_id != $domain['id']){
            $this->setSubdomainCookie($domain['id']);
        }
        return $domain;
    }

    public function getDefaultSubdomain(){
        $data = $this->getSubDomainByCache();
        $domain = current($data);
        foreach ($data as $key => $item) {
            if($item['is_default'] == 1){
                $domain = $item;
            }
        }
        return $domain;
    }

    public function getSubdomainIds($condition){
        $domains = $this->search($condition,0,10000,'sort_order asc');
        $ids = [];
        foreach ($domains as $key => $domain) {
            $ids[] = $domain['id'];
        }
        return $ids;
    }

    public function getSubdomainByCookie(){
        $dom_id = cookie('dom_id');
        $domain = $this->getSubDomainById($dom_id);
        if(empty($domain)){
            $domain = $this->getDefaultSubdomain();
        }
        return $domain;
    }

    public function setSubdomainCookie($subdomain_id){
        return cookie('dom_id',$subdomain_id,86400*730);
    }

    public function getSubdomainCookie(){
        return cookie('dom_id');
    }

    public function search($condition,$offset=0,$size=20,$order='id desc'){
        return $this->where($condition)->order($order)->limit($offset,$size)->select();
    }

    public function searchCount($condition){
        return $this->where($condition)->count();
    }

    public function getSubDomainByCache(){
        $cache_data = cache(self::CACHE_KEY);
        if(empty($cache_data)){
            $_cache_data = $this->search('',0,200,'id asc');
            $cache_data = [];
            foreach ($_cache_data as $key => $data) {
                $cache_data[$data['id']] = $data;
            }
            cache(self::CACHE_KEY,$cache_data);
            unset($_cache_data);
        }
        return $cache_data;
    }

    private function get($id){
        $data = $this->getSubDomainByCache();
        return $data[$id];
    }
}