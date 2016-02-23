<?php
/*
Auth:Sang
Desc:挂件基类
Date:2014-11-01
*/
namespace Lib;
class Widget{
	protected $widget_path;
	protected $widget_name;
	public $page_id;
	private $count = [];
	protected $widget_id;
	// 控制器
	public $action;
	//是否可编辑
	protected $enable_config = false;
	//是否异步加载
	protected $async = false;

	public function __construct($page_id,&$action){
		$widget_names = explode('\\',get_called_class());
		$this->widget_name = array_pop($widget_names);
		$this->widget_path = WIDGET_PATH.$this->widget_name.'/';
		$this->page_id = $page_id;
		$this->action = $action;
	}

	public function init(){
		$this->widget_id = $this->getWidgetId();
		return $this;
	}

	private function getWidgetId(){
		$key = md5($this->widget_name.$this->page_id);
		if(isset($this->count[$key])){
			$this->count[$key]++;
		}else{
			$this->count[$key] = 1;
		}
		return $this->page_id.'-'.abs(crc32($this->widget_name)).'-'.$this->count[$key];
	}

	protected function display($tpl_file,$data=[]){
		!is_array($data) && $data = (array)$data;
		extract($data);
		echo $this->enable_config || $this->async ? '<div class="W_mod'.($this->async ? ' loading W_mod_async' : '').'" id="'.$this->widget_id.'" name="'.$this->widget_name.'">'."\n" : '';
		if($this->async===false) include $this->tpl($tpl_file);
		echo $this->enable_config || $this->async ? '</div>' : '';
		unset($data);
	}

	protected function msg($msg){
		echo '<div style="color:red;text-align:center;height:25px;line-height:25px;">'.$msg.'</div>';
	}

	protected function tpl($tpl_file){
		$shm_file = SHM_PATH.'widget/tpl/'.$this->widget_name.'/'.$tpl_file;
		$real_tpl_file = $this->widget_path.$tpl_file;
		if(is_file($shm_file)){
			if(!defined('DEBUG')){
				return $shm_file;
			}
		}
		if(!is_file($shm_file) || filemtime($real_tpl_file)>filemtime($shm_file)){
			if(!is_file($real_tpl_file)){
				if(is_file($shm_file)){
					unlink($shm_file);
				}
				throw new \Exception("Widget template file <font color='red'>".str_replace(APP_PATH,'',$real_tpl_file)."</font> not exist!", 401);
			}
			$shm_dir = dirname($shm_file);
			if(!is_dir($shm_dir)){
				mkdir($shm_dir,0755,true);
			}
			copy($real_tpl_file, $shm_file);
		}
		return $shm_file;
	}

	public function saveData($data){
		if(!is_array($data)){
			return $this->action->jsonError('invalid data.the data must to be an arrays');
		}
		$validator = new \Lib\Validator($data);
		$validator->setRules([
			'_id'=>['required'=>true],
			'name'=>['required'=>true,'validate'=>'strip_tags,trim'],
			'tag'=>['required'=>true,'validate'=>'strip_tags,trim'],
			'page_id'=>['required'=>true,'validate'=>'longint'],
			'widget_id'=>['required'=>true,'validate'=>'absint'],
			'widget_path'=>['required'=>true,'validate'=>'trim'],
			'config'=>['required'=>true],
			'data'=>[],
		])->setMessages([
			'_id'=>['required'=>'唯一ID不能为空'],
			'name'=>['required'=>'挂件名称不能为空','validate'=>'模块名称不合法'],
			'tag'=>['required'=>'挂件标识不能为空','validate'=>'挂件标识不合法'],
			'page_id'=>['required'=>'页面ID不能为空','validate'=>'页面ID不合法'],
			'widget_id'=>['required'=>'挂件ID不能为空','validate'=>'挂件ID不合法'],
			'widget_path'=>['required'=>'挂件路径不能为空','validate'=>'挂件路径不合法'],
			'config'=>['required'=>'挂件配置不能为空'],
			'data'=>[],
		]);
		$data = $validator->validate();
		if(empty($data)){
			return $this->action->jsonError($validator->getError());
		}
		if(!is_array($data['config'])){
			$data['config'] = json_decode($data['config'],true);
		}
		if(isset($data['data']) && !empty($data['data']) && !is_array($data['data'])){
			$data['data'] = json_decode($data['data'],true);
		}
		$mdb = mongodb('widget_data');
		return $this->action->jsonSuccess($mdb->where("_id='{$data['_id']}'")->update($data));
	}

	public function getData($uniqid,$fields=''){
		if(empty($uniqid)){
			return $this->action->jsonError('invalid param uniqid');
		}
		$mdb = mongodb('widget_data');
		return $this->action->jsonSuccess($mdb->where("_id='{$uniqid}'")->fields($fields)->fetch());
	}

	public function delData($condition){
		if(empty($condition) || !is_array($condition)){
			return $this->action->jsonError('invalid condition');
		}
		$mdb = mongodb('widget_data');
		return $mdb->where($condition)->delete();
	}
}