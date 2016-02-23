<?php
/*
* @Desc 分销结算处理接口
* @Auth Sang
* @Date 2015-06-18 16:31:28
**/
namespace Lib;
class ShareProc{
	private $error;
	private static $instance;
	private function __construct(){

	}
	public static function getInstance(){
		if(empty(self::$instance)){
			self::$instance = new self;
		}
		return self::$instance;
	}
	public function process($data){
		if(!is_array($data) && is_string($data)){
			$data = json_decode($data,true);
		}
		if(empty($data)){
			return $this->error('invalid params','缺少必要的参数');
		}
		// 待分佣金额
		$money = val($data,'money',0);
		$money && $money = price($money);

		// 基础分佣比率
		$base_rate = val($data,'base_rate',100.00);
		$base_rate && $base_rate = price($base_rate);

		// 分销模式
		$type = val($data,'type','');

		// 分佣规则
		$rule = val($data,'rule','');

		// 分佣模式
		$commission_type = val($data,'commission_type','');

		// 参与分佣的会员
		$members = val($data,'members','');

		// 等级加成
		$extra_rate = val($data,'extra_rate','');

		if(check_not_empty($money,$type,$rule,$commission_type,$members)===false){
			return $this->error('invalid params','缺少必要的参数');
		}else{
			if($base_rate<1){
				return $this->error('invalid base_rate','基础分佣比率不能小于1');
			}

			// 基础分佣比率不能大于100
			if($base_rate>100.00){
				return $this->error('invalid base_rate','基础分佣比率不能大于100');
			}
			!is_array($rule) && $rule = json_decode($rule,true);
			!is_array($members) && $members = json_decode($members,true);
			!is_array($extra_rate) && $extra_rate = json_decode($extra_rate,true);
			// 如果传入extra_rate，检查它
			if(!empty($extra_rate) && !$this->checkKV($extra_rate,null)){
				return $this->error('Invalid extra_rate','等级分佣加成比率传入不正确');
			}
			// 检查基础分佣比例+加成比例是否大于100，大于100不行
			if($commission_type == 'extra' && $this->sumRate($base_rate,$extra_rate)>100){
				return $this->error('Invalid rate','基础分佣比例+等级加成比例不能大于100');
			}elseif($commission_type != 'extra' && !empty($extra_rate)){
				$extra_rate = [];
			}
			if(!in_array($type, ['agent','fans','agent_fans'])){
				return $this->error('Invalid parameter type','传入的分销模式不合法');
			}
			if($this->checkRule($type,$rule,$commission_type)==false){
				return $this->error('Invalid parameter rule','传入的规则不合法 '.$this->getError());
			}
			if($this->checkMembers($type,$members)==false){
				return $this->error('Invalid parameter members','传入的会员ID不合法 '.$this->getError());
			}
			if(!in_array($commission_type, ['integration','affiliation','extra','level_diff','basis'])){
				return $this->error('Invalid parameter commission_type','传入的分佣模式不合法 '.$this->getError());
			}

			$ret = [];
			switch($type){
				case 'agent':
				$ret = $this->agentRule($money,$base_rate,$rule,$members,$extra_rate,$commission_type);
				break;
				case 'fans':
				$ret = $this->fansRule($money,$base_rate,$rule,$members,$extra_rate,$commission_type);
				break;
				case 'agent_fans':
				$ret = $this->agentFansRule($money,$base_rate,$rule,$members,$extra_rate,$commission_type);
			}
			return !$this->getError() ? ['commission_money'=>$ret,'type' => $type,'commission_type' => $commission_type,'money' => $money,'base_rate' => $base_rate] : $this->error('fail',$this->getError());
		}

	}

	/**
	* 处理代理模式分佣
	* @access public
	* @param float $money
	* @param array $rule
	* @param array $members
	* @param string $commission_type
	* @return array
	*/
	private function agentRule($money,$base_rate,$rule,$members,$extra_rate,$commission_type){
		$ret = [];
		if(empty($members)){
			return $ret;
		}
		// 参与分佣的代理数量不能大于级别数
		if($commission_type!='level_diff' && count(array_keys($members)) > count(array_keys($rule))){
			$this->error .= '参与分佣的代理数量不能大于级别数';
			return false;
		}
		// 如果分佣模式是级别差模式，则重新计算代理的分佣比率
		if($commission_type == 'level_diff'){
			$buyer_level = array_pop($members);
			if(empty($members)){
				return $ret;
			}
			$member_levels = array_values($members);
			// sort($member_levels);
			// 计算每级别应获得的佣金比例
			$level_diff_rules = [];
			// 找出现实是一级代理，也就是最高级
			$max_level = min($member_levels);
			// 找出最高级别的索引位置
			$max_level_pos = array_search($max_level, $member_levels);
			// 截断数组，截至最高级别，后面的丢弃
			$member_levels = array_slice($member_levels,$max_level_pos);
			// 翻转数组，从接近消费者的那一级开始分
			$member_levels = array_reverse($member_levels,true);
			$member_levels = $this->filterLevelDiffMemberLevels($member_levels,$buyer_level);
			$member_levels = array_values($member_levels);
			foreach ($member_levels as $key=>$level) {
				$cur_level_rate = val($rule,$level,0);
				if($buyer_level>0 && $key==0 && $level<$buyer_level){
					$near_level_rate = val($rule,$buyer_level,0);
					$level_diff_rules[$level] = intval($cur_level_rate - $near_level_rate);
				}else{
					$near_level = val($member_levels,$key-1,-1);
					$near_level_rate = val($rule,$near_level,0);
					$level_diff_rules[$level] = intval($cur_level_rate - $near_level_rate);
					$level_diff_rules[$level]<0 && $level_diff_rules[$level] = 0;
				}
			}
			$rule = $level_diff_rules;
		}
		foreach($members as $id=>$level){
			if(isset($rule[$level]) && $rule[$level]>0){
				$ret[$id] = ['money'=>$money*$rule[$level]/100,'rate'=>$rule[$level],'base_money'=>$money,'base_rate'=>100,'extra_rate'=>0];
				unset($rule[$level]);
			}
		}
		// 未分完的情况
		if(!empty($rule)){
			// 归属模式
			if($commission_type == 'affiliation'){
				$diff_rule = [];
				$recuver_member = array_flip($members);
				// 将剩下的未分完的比率按级别从高到低排序
				krsort($rule);
				// 从高到低遍历
				foreach($rule as $level=>$rate){
					if($level>1){
						$new_level = $level;
						if(isset($recuver_member[$new_level]) && ($uid = $recuver_member[$new_level]) && isset($ret[$uid])){
							$ret[$uid]['money'] += $money*$rate/100;
							$ret[$uid]['rate'] += $rate;
						}else{
							// 追溯
							while($new_level-->0 && !isset($recuver_member[$new_level])){

							}
							$uid = $recuver_member[$new_level];
							if(!isset($ret[$uid])){
								continue;
							}
							$ret[$uid]['money'] += $money*$rate/100;
							$ret[$uid]['rate'] += $rate;
						}
					}
				}
			}elseif($commission_type == 'integration'){
				// 整合模式 未分完不再分，归厂家
			}
		}
		array_walk($ret,function(&$v,$k){
			$v['money'] = price($v['money']);
		});
		return $ret;
	}

	/**
	* 处理粉丝模式分佣
	* @access public
	* @param float $money 分佣基数
	* @param float $base_rate 基础分佣比率
	* @param array $rule 分佣规则
	* @param array $members 参与分佣的会员
	* @param array $extra_rate 会员等级分佣加成
	* @param string $commission_type 分佣模式
	* @param string $type 分销模式
	* @return array
	*/
	private function fansRule($money,$base_rate,&$rule,$members,$extra_rate,$commission_type,$type='fans'){
		$ret = [];
		// 参与分佣的粉丝数量不能大于级别数
		if(count($members) > count($rule)){
			$this->error .= '参与分佣的粉丝数量不能大于级别数';
			return false;
		}
		// 会员等级
		$levels = array_values($members);
		// 会员ID
		$members = array_keys($members);
		// 先对消费者那一级进行处理
		$consumer  = array_pop($members);
		$consumer_rule = array_pop($rule);
		$consumer_level = $levels[count($levels)-1];
		$consumer_extra_rate = val($extra_rate,$consumer_level,0);
		if($consumer_rule > 0){
			$consumer_base_rate = $base_rate+$consumer_extra_rate;
			$base_money = $money*$consumer_base_rate/100;
			$ret[$consumer] = ['money'=>$base_money*($consumer_rule/100),'rate'=>$consumer_rule,'base_money'=>$base_money,'base_rate'=>$consumer_base_rate,'extra_rate'=>$consumer_extra_rate];
		}
		// 如果只有一个粉丝，此时已经分完了，不用再执行下面的
		if(empty($members)){
			return $ret;
		}

		// 接下来进行其它粉丝分佣
		// if(($commission_type == 'integration' && $type == 'agent_fans') || $commission_type == 'extra'){
			// 整合分销模式，粉丝+代理分佣模式，需要从接近购买者那一级开始计算
			$members = array_reverse($members);
			$rule = array_reverse($rule);
		// }
		while(list($key,$uid) = each($members)){
			$r = array_shift($rule);
			$_level = val($levels,$key,0);
			$_extra_rate = val($extra_rate,$_level,0);
			if($r>0){
				$_base_rate = $base_rate + $_extra_rate;
				$_base_money = $money*$_base_rate/100;
				$ret[$uid] = ['money'=>$_base_money*($r/100),'rate'=>$r,'base_money'=>$_base_money,'base_rate'=>$base_rate,'extra_rate'=>$_extra_rate];
			}
		}

		// 未分完
		if(!empty($rule)){
			// 归属模式
			if($commission_type == 'affiliation'){
				$diff_rate = $diff_money = 0;
				foreach($rule as $key=>$rate){
					$diff_rate += $rate;
					$diff_money += $money*$rate/100;
					unset($rule[$key]);
				}
				$max_key = min(array_keys($members));
				$last_uid = $members[$max_key];
				$ret[$last_uid]['money'] += $diff_money;
				$ret[$last_uid]['rate'] += $diff_rate;
			}
		}
		// 整合模式，将之前翻转的数组，转回来
		// if($commission_type == 'integration' && $type == 'agent_fans'){
			// $ret = array_reverse($ret,true);
		// }
		array_walk($ret,function(&$v,$k){
			$v['money'] = price($v['money']);
		});
		return $ret;
	}

	/**
	* 处理代理+粉丝模式分佣
	* @access public
	* @param float $money
	* @param array $rule
	* @param array $members
	* @param string $commission_type
	* @return array
	*/
	private function agentFansRule($money,$base_rate,$rule,$members,$extra_rate,$commission_type){
		$ret = ['agent'=>[],'fans'=>[]];
		// 粉丝分佣规则
		$fans_rule = $rule['fans'];
		// 代理分佣规则
		$agent_rule = $rule['agent'];
		// 粉丝会员
		$fans_members = $members['fans'];
		// 代理会员
		$agent_members = $members['agent'];
		// 计算粉丝的分佣，如果未分完，则fans_rule不会变成空，否则通过fansRule的处理后，fans_rule会变为空
		$ret['fans'] = $this->fansRule($money,$base_rate,$fans_rule,$fans_members,$extra_rate,$commission_type,'agent_fans');
		// 粉丝未分完
		if(!empty($fans_rule)){
			// 归属模式
			if($commission_type == 'affiliation'){
				$diff_rate = 0;
				foreach($fans_rule as $rate){
					$diff_rate += $rate;
				}
				// 取得最后一级代理商的级别
				$last_agent_level = max(array_keys($agent_rule));
				// 将剩下的分佣比率加到最后一级代理商的佣金比率上
				$agent_rule[$last_agent_level] += $diff_rate;
			}elseif($commission_type == 'integration'){
				// 整合模式
				// 将代理分佣规则倒序排列
				$_agent_members = $agent_members;
				rsort($_agent_members);
				// 将未分完的粉丝规则倒序排列
				arsort($fans_rule);
				// 重新组合到代理规则，未分完的则归商家
				foreach($fans_rule as $rate){
					list($_,$level) = each($_agent_members);
					if($level && isset($agent_rule[$level])){
						$agent_rule[$level] += $rate;
					}
				}
			}
		}

		// 计算代理商分佣，如果分佣模式不是等级差模式，则给代理分佣始终是归属模式，否则为等级差模式
		$ret['agent'] = $this->agentRule($money,$base_rate,$agent_rule,$agent_members,$extra_rate,$commission_type!='level_diff' ? 'affiliation' : 'level_diff');
		return $ret;
	}

	/**
	* 检查规则是否合法
	* @access private
	* @param string $type
	* @param array $rule
	* @return bool
	*/
	private function checkRule($type,$rule,$commission_type){
		if(empty($rule)){
			return false;
		}
		switch($type){
			case 'fans':
				if(!isset($rule[0])){
					return false;
				}
			case 'agent':
				return $this->checkKV($rule) && $this->checkTotalRate($rule,[],$commission_type);
				break;
			case 'agent_fans':
				if(!isset($rule['agent']) || !isset($rule['fans'])){
					return false;
				}
				$agent_rule = $rule['agent'];
				$fans_rule = $rule['fans'];
				if(!isset($fans_rule[0])){
					return false;
				}
				return $this->checkKV($agent_rule) && $this->checkKV($fans_rule) && $this->checkTotalRate($agent_rule,$fans_rule,$commission_type);
				break;
		}
		return false;
	}

	/**
	* 检查百分比相加是否大于100
	* @access private
	* @param array $agent_rule
	* @param array $fans_rule
	* @param string $commission_type
	* @return bool
	*/
	private function checkTotalRate($agent_rule=[],$fans_rule=[],$commission_type){
		if(empty($agent_rule) && empty($fans_rule)){
			return true;
		}
		$msg = '分佣百分比总数相加不能大于100';

		$agent_total_rate = $fans_total_rate = 0;
		if(!empty($agent_rule)){
			$agent_rule = array_values($agent_rule);
			// 如果分佣模式为级差模式，则取最大的那一级代理比率
			$agent_total_rate = $commission_type=='level_diff' ? max($agent_rule) : array_sum($agent_rule);
		}
		if(!empty($fans_rule)){
			$fans_rule = array_values($fans_rule);
			$fans_total_rate = array_sum($fans_rule);
		}
		if($agent_total_rate + $fans_total_rate > 100){
			$this->error .= $msg;
			return false;
		}
		return true;
	}

	/**
	* 检查规则键值对是否正确
	* @access private
	* @param array $rule
	* @param string $sort 检查排序的方式，key表示检查数组的键是否有序的，value表示检查值是否有序的,null表示不检查是否有序
	* @return bool
	*/
	private function checkKV($array,$sort='key'){
		if($sort!=null){
			$values = $sort == 'key' ? array_keys($array) : array_values($array);
			foreach($values as $key=>$item){
				if(isset($values[$key+1]) && $values[$key+1] - $item!=1){
					return false;
				}
			}
			return true;
		}
		foreach($array as $key=>$value){
			if(!is_int($key) || !is_numeric($value)){
				return false;
			}
		}
		return true;
	}

	/**
	* 检查会员参数是否正确
	* @access private
	* @param string $type
	* @param array $members
	* @return bool
	*/
	private function checkMembers($type,$members){
		if(empty($members)){
			return false;
		}
		switch($type){
			case 'fans':
			case 'agent':
				return $this->checkKV($members,null);
			break;
			case 'agent_fans':
				if(!isset($members['agent']) || !isset($members['fans'])){
					return false;
				}
				$agent_members = $members['agent'];
				$fans_members = $members['fans'];
				return $this->checkKV($agent_members,null) && $this->checkKV($fans_members,null);
			break;
		}
	}

	private function filterLevelDiffMemberLevels($member_levels,$buyer_level){
		$new_array = [];
		while($current = current($member_levels)){
			if($buyer_level==0 || $current<$buyer_level){
				$new_array[] = $current;
			}
			while(next($member_levels)>$current);
		}
		return $new_array;
	}

	private function getError(){
		return $this->error;
	}

	private function sumRate($base_rate,$extra_rate){
		if(empty($extra_rate)) return $base_rate;
		return $base_rate+max(array_values($extra_rate));
	}

	private function error($code,$msg){
		return ['error'=>1,'msg'=>$code,'sub_msg'=>$msg];
	}
}