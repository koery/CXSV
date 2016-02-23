<?php
/*
Auth:Sang
Desc:后台用户模型
Date:2014-10-27
*/
namespace Mod;
use Lib\Model;
use Lib\Common;
use Lib\Encrypt;
class User extends Model{
	private $cookie_domain;
	public function _init(){
		$this->cookie_domain = get_dom();
		if(!$this->tableExists($this->getTableName())){
			$sql = <<<eof
CREATE TABLE `{$this->getTableName()}` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `user_name` char(50) NOT NULL DEFAULT '' COMMENT '用户名',
  `password` char(32) NOT NULL DEFAULT '' COMMENT '密码',
  `salt` char(6) NOT NULL DEFAULT '' COMMENT '密码扰码',
  `real_name` char(40) NOT NULL DEFAULT '' COMMENT '真实姓名',
  `mobile` char(20) NOT NULL DEFAULT '' COMMENT '手机',
  `email` char(60) NOT NULL DEFAULT '' COMMENT '邮箱',
  `user_desc` varchar(50) NOT NULL DEFAULT '' COMMENT '用户描述',
  `login_time` int(11) NOT NULL DEFAULT 0 COMMENT '登录时间',
  `status` tinyint(3) NOT NULL DEFAULT 1 COMMENT '状态',
  `login_ip` char(64) NOT NULL DEFAULT '' COMMENT '登录IP',
  `user_group` int(11) NOT NULL DEFAULT 0 COMMENT '用户组',
  `shortcuts` text COMMENT '快捷菜单',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `user_name` (`user_name`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1001 DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;
eof;
			if($this->db->query($sql)){
				$this->addUser([
					'user_id' => 1,
					'user_name' => 'admin',
					'password' => '123456',
					'user_group' => 1,
				]);
			}

		}
	}
	public function isLogin(){
		if(!session('uid')){
			$user_id=$this->getCookieRemember();
			if($user_id>0){
				$user_info = $this->getUserById($user_id);
				$this->loginDoSomething($user_info);
				return true;
			}
			return false;
		}
		return session('uid');
	}

	public function login($username,$password){
		$user_info = $this->where("user_name='{$username}'")->fetch();
		if(!empty($user_info) && $this->genPassword($password,$user_info['salt'])[0]==$user_info['password']){
			$this->loginDoSomething($user_info);
			return $user_info;
		}
		return false;
	}

	public function loginDoSomething($user_info){
		if(empty($user_info)){
			$this->error = 'user not exists';
			return false;
		}
		if($user_info['status']!=1){
			$this->error = 'the user is frozen';
			return false;
		}

		$user_group = model('#UserGroup')->getGroupById($user_info['user_group']);
		if(!empty($user_group)){
			$user_info = array_merge($user_info,$user_group ? $user_group : []);
			$user_info['menus'] = model('#MenuUrl')->getMenusByRole($user_group['group_role'],$user_info['user_id']);
		}else{
			$user_info['group_id']=0;
			$user_info['menus']= [];
		}
		$user_info['shortcuts']=explode(',',$user_info['shortcuts']);
		
		//更新登录信息
		$login_time = time();
		$login_ip = Common::getIp ();
		$this->where("user_id='{$user_info['user_id']}'")->update(array ('login_ip' => $login_ip, 'login_time' => $login_time ));
		$user_info['login_ip']=$login_ip;
		$user_info['login_time']=Common::getDateTime($user_info['login_time']);
		unset($user_info['password'],$user_info['salt']);
		$this->setSession($user_info);
	}

	public function reloadSession($uid=0){
		$user_info = $this->getUserById($uid ? $uid : session('uid'));
		if($user_info['status']!=1){
			return false;
		}
		
		//读取该用户所属用户组将该组的权限保存在$_SESSION中
		$user_group = model('#UserGroup')->getGroupById($user_info['user_group']);
		if(!empty($user_group)){
			$user_info = array_merge($user_info,$user_group ? $user_group : []);
			$user_info['menus'] = model('#MenuUrl')->getMenusByRole($user_group['group_role']);
		}else{
			$user_info['group_id']=0;
			$user_info['user_role']='';
		}
		$user_info['shortcuts']=explode(',',$user_info['shortcuts']);
		unset($user_info['password'],$user_info['salt']);
		$this->setSession( $user_info);
		return true;
	}

	public function setSession($user){
		session('uid',$user['user_id']);
		session('user_info',$user);
	}

	public function loginOut(){
		session(null);
		$this->setCookie('radm',null);
	}

	/**
   * 产生密码  双重MD5 加扰码
   * @param string $password
   * @return Array
   */
  public function genPassword($password,$salt=''){
    $salt or $salt = substr(uniqid(rand()), -6);
    return array(md5(md5($password).$salt),$salt);
  }

  public function checkPassword($user_name, $password) {
  	$user = $this->where("user_name = '{$user_name}'")->fetch();
  	list($gen_password,$salt) = $this->genPassword($password,$user['salt']);
  	if($gen_password != $user['password']){
  		return false;
  	}
  	return true;
}

  public function setTemplate($t){
  	if($this->where("user_id=".session('uid'))->update(array('template'=>$t))){
	  	$user_info = session('user_info');
	  	$user_info['template'] = $t;
	  	session('user_info',$user_info);
	  }
  }

  public function getTemplate(){
  	return isset(session('user_info')['template']) && !empty(session('user_info')['template']) ? session('user_info')['template'] : 'default';
  }

  public function getSidebar(){
  	$user_info = session('user_info');
  	if(empty($user_info)){
  		return;
  	}
	//用户的权限
	$access = $user_info['menus'];
	$menus = [];
	foreach ($access as &$item) {
		$model_id = $item['module_id'];
		$menus[$model_id]['module_id'] = $item['module_id'];
		$menus[$model_id]['module_name'] = $item['module_name'];
		$menus[$model_id]['module_icon'] = $item['module_icon'];
		$menus[$model_id]['module_url'] = $item['module_url'];
		$menus[$model_id]['menu_list'][] = $item; 
	}
	return $menus;
  }


  public function getUserByName($user_name) {
		$sql= "select * ,g.group_name from ".$this->getTableName() ." u,".model('#UserGroup')->getTableName()." g where u.user_name='$user_name' and u.user_group=g.group_id";
		$list = $this->db->query($sql)->fetch();
		if ($list) {
			$list['login_time']=Common::getDateTime($list['login_time']);
			return $list;
		}
		return array ();
	}
	
	public function getUserById($user_id) {
		if (! $user_id || ! is_numeric ( $user_id )) {
			return false;
		}
		$list = $this->where("user_id={$user_id}")->fetch();
		if ($list) {
			$list['login_time']=Common::getDateTime($list['login_time']);
			return $list;
		}
		return array ();
	}
	
	public function setCookieRemember($uid,$day=7){
		$encrypted = urlencode(Encrypt::encrypt($uid));
		$this->setCookie("radm",$encrypted,86400*$day);
	}
	
	public function getCookieRemember(){
		$encrypted = $this->setCookie("radm");
		$base64=urldecode($encrypted);
		return Encrypt::decrypt($base64);
	}
	
	public function search($user_group ,$user_name, $start ='' ,$page_size='' ) {
		$limit ="";
		$where = "";
		if($page_size){
			$limit =" limit $start,$page_size ";
		}
		if($user_group >0  && $user_name!=""){
			$where .= " and u.user_group=$user_group and u.user_name like '%$user_name%'";
		}else{
			if($user_group>0){
				$where .= " and u.user_group=$user_group ";
			}
			if($user_name!=""){
				$where .= " and u.user_name like '%$user_name%' ";
			}
		}
		$where && $where = substr($where, 5);
		$sql = "select * from ".$this->getTableName()." u left join ".model('#UserGroup')->getTableName()." g on u.user_group = g.group_id $where order by u.user_id asc $limit";
		
		$result=$this->db->query($sql);
		$list = [];
		while($result->next()){
			$item = $result->get();
			unset($item['password'],$item['salt']);
			$item['login_time'] && $item['login_time']=Common::getDateTime($item['login_time']);
			empty($item['login_time']) && $item['login_time'] = '';
			$list[] = $item;
		}
		return $list;
	}
	
	public function getUsersByGroup( $group_id ) {
		if(empty($group_id) || !is_numeric($group_id)){
			return array();
		}
		$result = $this->where("user_group={$group_id}")->select();
		foreach($result as &$item){
				$item['login_time'] && $item['login_time']=Common::getDateTime($item['login_time']);
		}
		return $result;
	}
	
	public function checkActionAccess() {
		$action_url = Common::getActionUrl();
		$user_info = session('user_info');
		$menus = get_array_field($user_info['menus'],'menu_url');

		$action_urls = [$action_url];
		if(substr($action_url,-1)=='/'){
			$action_urls[] = $action_url.'index';
		}elseif(strtolower(substr($action_url,-4))!='/get'){
			$action_urls[] = $action_url .'/get';
		}elseif(strtolower(substr($action_url,-4))=='/get'){
			$action_urls[] = substr($action_url,0,-4);
		}
		foreach($action_urls as $ac_url){
			if(in_array ( $ac_url, $menus )){
				return true;
			}
		}
		return false;
	}
	
	public function updateUser($user_id,$user_data) {
		if (! $user_data || ! is_array ( $user_data )) {
			return false;
		}
		$validator = new \Lib\Validator($user_data);
		$validator->setRules([
			'password' => ['required'=>true],
			'real_name' => ['validate'=>'strip_tags,trim'],
			'mobile' => ['validate'=>'is_mobile'],
			'email' => ['validate'=>'is_mail'],
			'user_desc' => ['validate'=>'strip_tags,trim'],
			'status' => ['validate'=>'intval'],
			'user_group' => ['required'=>true,'validate'=>'absint'],
		])->setMessages([
			'password' => ['required'=>'密码不能为空'],
			'real_name' => ['validate'=>'真实姓名不合法'],
			'mobile' => ['validate'=>'手机号码不合法'],
			'email' => ['validate'=>'邮箱不合法'],
			'user_desc' => ['validate'=>'用户描述不合法'],
			'user_group' => ['required'=>'用户组不能为空','validate'=>'absint'],
		]);
		$data = $validator->validate();
		if(empty($data)){
			$this->error = $validator->getError();
			return false;
		}
		if(isset($user_data['password']) && !empty($user_data['password'])){
			list($password,$salt) = $this->genPassword($user_data['password']);
			$user_data['password'] = $password;
			$user_data['salt'] = $salt;
		}else{
			if(isset($user_data['password']))
				unset($user_data['password']);
			if(isset($user_data['salt']))
				unset($user_data['salt']);
		}
		return $this->where("user_id={$user_id}")->update($user_data);
	}
	
	/**
	* 批量修改用户，如批量修改用户分组
	* user_ids 可以为无key数组，也可以为1,2,3形势的字符串
	*/
	public function batchUpdateUsers($user_ids,$user_data) {
		if (! $user_data || ! is_array ( $user_data )) {
			return false;
		}
		if(!is_array($user_ids)){
			$user_ids=explode(',',$user_ids);
		}
		return $this->where("user_id in(".join(',',$user_ids).")")->update($user_data);
	}
	
	public function addUser($user_data) {
		if (! $user_data || ! is_array ( $user_data )) {
			return false;
		}
		$validator = new \Lib\Validator($user_data);
		$validator->setRules([
			'user_id' => ['validate'=>'absint'],
			'user_name' => ['required'=>true,'validate'=>'strip_tags,trim'],
			'password' => ['required'=>true],
			'real_name' => ['validate'=>'strip_tags,trim'],
			'mobile' => ['validate'=>'is_mobile'],
			'email' => ['validate'=>'is_mail'],
			'user_desc' => ['validate'=>'strip_tags,trim'],
			'status' => ['validate'=>'intval'],
			'user_group' => ['required'=>true,'validate'=>'absint'],
		])->setMessages([
			'user_id' => ['validate'=>'会员ID不合法'],
			'user_name' => ['required'=>'用户名不能为空','validate'=>'用户名不合法'],
			'password' => ['required'=>'密码不能为空'],
			'real_name' => ['validate'=>'真实姓名不合法'],
			'mobile' => ['validate'=>'手机号码不合法'],
			'email' => ['validate'=>'邮箱不合法'],
			'user_desc' => ['validate'=>'用户描述不合法'],
			'user_group' => ['required'=>'用户组不能为空','validate'=>'用户组不合法'],
		]);
		$data = $validator->validate();
		if(empty($data)){
			$this->error = $validator->getError();
			return false;
		}
		if($this->where("user_name='{$data['user_name']}'")->count()){
			$this->error = '该用户名称已存在';
			return false;
		}
		list($password,$salt) = Common::genPassword($data['password']);
		$data['password'] = $password;
		$data['salt'] = $salt;
		return $this->insert($data);
	}
	
	public function delUser($user_id) {
		if(empty($user_id) || (!is_numeric($user_id) && !is_array($user_id))){
            return false;
        }
        // 是否包含ID为1的用户
        $user_id = string2array($user_id);
        foreach ($user_id as $key => $value) {
        	if($value==1){
        		$this->error = '超级管理员不能被删除';
        		return false;
        	}
        }
        $ids = is_array($user_id) ? join(',',$user_id) : $user_id;
        return $this->where("user_id in({$ids})")->delete();
	}
	
	public function delUserByUserName($user_name) {
		if (! $user_name ) {
			return false;
		}
		return $this->where("user_name='{$user_name}'")->delete();
	}
	
	public function searchCount($user_group,$user_name) {
		$where = "";
		if($user_group>0){
			$where .= " and user_group={$user_group}";
		}
		if($user_name!=""){
			$where .= " and user_name like '%{$user_name}%'";
		}
		$where && $where = substr($where, 5);
		return $this->where($where)->count();
	}

	public function getQuickNote(){
		$note = model('#QuickNote')->getRandomNote();
		if($note){
			$note_content=$note['note_content'];
			$note_html="<div class=\"alert alert-info\">
				<button type=\"button\" class=\"close\" data-dismiss=\"alert\">×</button>$note_content</div>";
			return $note_html;
		}
		return '';
	}

	private function setCookie($key,$val='',$expires=0){
		return cookie($key,$val,$expires,'/',$this->getCookieDomain());
	}

	public function getCookieDomain(){
		return $this->cookie_domain;
	}

	public function setCookieDomain($domain){
		$this->cookie_domain = $domain;
	}
}