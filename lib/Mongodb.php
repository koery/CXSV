<?php
/*
Auth:Sang
Desc:MongoDb的操作类
Date:2015-01-13
*/
namespace Lib;
class Mongodb{
	//链接
	private $link;
	//配置 
	private $config;
	//当前数据库
	private $db;
	//当前集合
	private $collection;

	// 当前集合名
	private $collection_name;
	//参数
	private $options = [];

	//错误记录
	private $error;

	/**
	* 构建函数
	* @access public
	* @param array $config
	* @return void
	*/
	public function __construct($config){
		if(!isset($config['host']) || !isset($config['db'])){
			error('Mongodb not configured!',900);
		}
		$this->config = $config;
		try{
			$this->link = new \MongoClient($config['host']);
			$this->db = $this->link->$config['db'];
		}catch(\Exception $e){
			$this->error = $e->getMessage();
		}
	}

	public function __call($name,$args){
		try{
			return call_user_func_array([$this->collection,$name], $args);
		}catch(\Exception $e){
			$this->error = $e->getMessage();
			return false;
		}
	}

	/**
	* 选择数据库
	* @access public
	* @param string $db
	* @return void
	*/
	public function selectDB($db){
		$this->link->selectDB($db);
		$this->db = $this->link->$db;
	}

	/**
	* 选择集合
	* @access public
	* @param string $collection 集合名称
	* @return void
	*/
	public function setCollection($collection,$is_gridfs=false){
		$this->collection_name = $collection;
		$this->collection = $is_gridfs ? $this->getGridFS() : $this->getCollection();
	}

	/**
	* 插入记录
	* @access public
	* @param array $array 要插入的数据，如果数据已存在，则插入失败
	* @param array $options fsync:是否异步插入，异步即不等待mongodb操作完成，直接返回数据标识_id
	*              			j:默认false,w,wtimeout,safe,timeout 
	* @return bool
	*/
	public function insert($array,$options=[]){
		if(empty($array) || !is_array($array)){
			throw new \Exception("The argument[0] must to a associative array", 909);
		}
		$this->formatData($array);
		try{
			return $this->collection->insert($array,$options);
		}catch(\MongoCursorException $e){
			$this->error = $e->getMessage();
			return false;
		}finally{
			$this->reset();
		}
	}

	/**
	* 批量插入
	* @access public
	* @param array $data
	* @return bool
	*/
	public function multiInsert($data,$options = []){
		if(empty($data) || !isset($data[0])){
			throw new \Exception("The argument[0] must to a double dimensional associative array", 908);
		}
		$this->formatData($data);
		try{
			return $this->collection->batchInsert($data,$options);
		}catch(\MongoCursorException $e){
			$this->error = $e->getMessage();
			return false;
		}finally{
			$this->reset();
		}
	}

	/**
	* 更新数据
	* @access public
	* @param array $data 要更新的数据
	* @param array $options upsert:不存在时插入，multiple:是否更新符合条件的数据，默认为false
	*						fsync:是否异步插入，j,socketTimeoutMS,w,wTimeoutMS,timeout,wtimeout
	* @return bool
	*/
	public function update($data,$options=[]){
		if(empty($data) || !is_array($data)){
			throw new \Exception("The argument[0] must to a associative array", 909);
		}
		$this->formatData($data);
		extract($this->getOptions());
		!isset($options['multiple']) && $options['multiple'] = true && $data = ['$set'=>$data];
		try{
			if(empty($where)){
				throw new \Exception("\$where can`t no empty", 801);
			}
			return $this->collection->update($where,$data,$options);
		}catch(\MongoCursorException $e){
			$this->error = $e->getMessage();
			return false;
		}finally{
			$this->reset();
		}
	}

	/**
	* 更新数据，如果数据不存在，则创建之，以_id做为判断
	* @access public
	* @param array $data
	* @param array $options 选项有：fsync:是否异步插入，异步即不等待mongodb操作完成，直接返回数据标识_id
	*	 							j:默认false,w,wtimeout,safe,timeout 
	* @return bool
	*/
	public function save($data,$options=[]){
		if(empty($data) || !is_array($data)){
			throw new \Exception("The argument[0] must to a associative array", 909);
		}
		$this->formatData($data);
		try{
			return $this->collection->save($data,$options);
		}catch(\MongoCursorException $e){
			$this->error = $e->getMessage();
			return false;
		}finally{
			$this->reset();
		}
	}

	/**
	* 查询
	* @access public
	* @param array $where
	* @param mix $fields
	* @return array
	*/
	public function select(){
		$db = &$this->collection;
		extract($this->getOptions());
		try{
			$result = $db->find($where,$fields);
			if(!empty($limit)){
				$result->limit($limit);
			}
			if(!empty($skip)){
				$result->skip($skip);
			}
			if(!empty($order)){
				$result->sort($order);
			}
			$ret = [];
			foreach($result as $item){
				$ret[] = $item;
			}
			unset($result);
			return $ret;
		}catch(\MongoCursorException $e){
			$this->error = $e->getMessage();
			return [];
		}finally{
			$this->reset();
		}
	}

	/**
	* 查询一条记录
	* @access public
	* @param array $where
	* @param mix $fields
	* @return array
	*/
	public function fetch(){
		$db = &$this->collection;
		extract($this->getOptions());
		try{
			$result = $db->findOne($where,$fields);
			return $result ? $result : [];
		}catch(\MongoCursorException $e){
			$this->error = $e->getMessage();
			return [];
		}finally{
			$this->reset();
		}
	}

	/**
	* 统计条数
	* @access public
	* @return int
	*/
	public function count(){
		extract($this->getOptions());
		try{
			return $this->collection->find($where)->count();
		}catch(\MongoCursorException $e){
			$this->error = $e->getMessage();
			return 0;
		}finally{
			$this->reset();
		}
	}


	/**
	* 删除
	* @access public
	* @param array $options w,justOne:最多只删除一个匹配的记录,fsync:异步写入,j,w,timeout
	* @return bool
	*/
	public function delete($options=[]){
		extract($this->getOptions());
		try{
			return $this->collection->remove($where,$options);
		}catch(\MongoCursorException $e){
			$this->error = $e->getMessage();
			return false;
		}finally{
			$this->reset();
		}
	}

	/**
	* 删除一个集合
	* @access public
	* @param string $collection_name 默认为删除当前选中集合，传入该参数表示删除该名称的集合
	* @return bool
	*/
	public function drop($collection_name=null){
		if(!empty($collection_name)){
			return $db->$collection_name->drop();
		}
		return $this->collection->drop();
	}

	/**
	* 执行mongodb命令
	* @access public
	* @param array $command_data
	* @param array $options
	* @return bool
	*/
	public function runCommand($command_data,$options=[]){
		if(empty($command_data)){
			return false;
		}
		return $this->db->command($command_data,$options=[]);
	}



	/**
	* 限制返回数据条数
	* @access public
	* @return object self
	*/
	public function limit(){
		$args = func_get_args();
		if(count($args)>1){
			$this->options['limit'] = $args[1];
			$this->options['skip'] = $args[0];
		}elseif(count($args)==1){
			$this->options['limit'] = $args[0];
			$this->options['skip'] = 0;
		}
		return $this;
	}

	/**
	* 排序
	* @access public
	* @param mix $order
	* @return object self
	*/
	public function order($order){
		if(empty($order)){
			return $this;
		}
		if(!is_array($order)){
			$order = $this->order2array($order);
		}
		$this->options['order'] = $order;
		return $this;
	}


	/**
	* 条件
	* @access public
	* @param mix $where
	* @return object self
	*/
	public function where($where){
		if(empty($where)){
			return $this;
		}
		if(!is_array($where)){
			$where = $this->where2array($where);
		}
		$this->formatData($where);
		$this->options['where'] = $where;
		return $this;
	}

	/**
	* 显示字段
	* @access public
	* @param string $fields
	* @return object self
	*/
	public function fields($fields){
		if(empty($fields)){
			return $this;
		}
		if(!is_array($fields)){
			$fields = $this->fields2array($fields);
		}
		$this->options['fields'] = $fields;
		return $this;
	}

	/**
	* 字段转换成数组
	* @access private
	* @param string $fields
	* @return array
	*/
	private function fields2array($fields){
		$fields = explode(',',$fields);
		$ret = [];
		foreach ($fields as $key => $field) {
			$ret[$field] = true;
		}
		return $ret;
	}


	/**
	* 排序转换成数组
	* @access private
	* @param string $order
	* @return array
	*/
	private function order2array($order){
		$sort = [];
		$orders = explode(',',$order);
		foreach ($orders as $key => $order) {
			list($s,$o) = preg_split('/\s+/',$order,2);
			$o = $o == 'asc' ? 1 : -1;
			$sort[] = [$s=>$o];
		}
		return $sort;
	}



	/**
	* 条件转成数组，此处只实现简单 and 表达式的sql语句转换，如语句中包含复杂表达式，请直接使用mongodb的查询方式
	* @access private
	* @param string $where
	* @return array
	*/
	private function where2array($where){
		//解析表达式
		$patt = '/(`?[a-zA-Z0-9_]+`?)\s*(>=|<=|=|<>|>|<|!=|\bin\b|\blike\b|\bnot\s+in\b|\bnot\b|\bhaving\b)\s*(\(.*?\)|\d+(\.\d{1,})?|[\'\"].*?[\'\"]|null)?/iS';
		preg_match_all($patt,$where,$matches);
		$mts = [];
		$tags = [];
		if(!empty($matches)){
			$mts = array_combine($matches[1], $matches[3]);
			$tags = array_combine($matches[1], $matches[2]);
		}

		$fu = [
			'>'=>'$gt',
			'<' => '$lt',
			'>=' => '$gte',
			'<=' => '$lte',
			'in' => '$in',
			'not_in' => '$nin',
			'<>' => '$ne',
			'!=' => '$ne',
			'exists' => '$exists',
		];
		$ret = [];
		foreach ($mts as $key => $value) {
			$value = trim($value,'"');
			$value = trim($value,"'");
			if(is_numeric($value)){
				$value = $value+0;
			}
			$f = $tags[$key];
			switch($f){
				case '=':
					$ret[$key] = $value;
				break;
				case 'like':
					$ret[$key] = $this->buildLike($value);
				break;
				default:
					$ret[$key] = [$fu[$f]=>$value];
				break;
			}
		}
		return $ret;
	}


	/**
	* 复位
	* @access public
	* @return void
	*/
	private function reset(){
		$this->options = [];
	}


	/**
	* 取得参数
	* @access private
	* @return array
	*/
	private function getOptions(){
		$default = [
			'where' => [],
			'fields' => [],
			'limit' => '',
			'skip' => 0,
			'order' => [],
		];

		return array_merge($default,$this->options);
	}


	/**
	* 获取错误信息
	* @access public
	* @return string
	*/
	public function getError(){
		return $this->error;
	}

	/**
	* 处理LIKE查询
	* @access private
	* @param string $value
	* @return string
	*/
	private function buildLike($value){
		$value = trim($value);
		if(trim($value,'%')==$value){
			return "/\^{$value}\$/";
		}elseif($value{0}=='%' && substr($value,-1)=='^'){
			return "/".trim($value,'%')."/";
		}elseif($value{0}=='%'){
			return '/'.trim($value,'%').'$/';
		}elseif(substr($value,-1)=='%'){
			return '/^'.trim($value,'%').'/';
		}
		return $value;
	}

	/**
	* 格式化数据
	* @access private
	* @param array $data
	* @return array
	*/
	private function formatData(&$data){
		if(empty($data)){
			return;
		}
		array_walk_recursive($data, function(&$v,$k){
			if(is_numeric($v)){
				if((int)$v<=PHP_INT_MAX){
					$v = $v+0;
				}else{
					$v = strval($v);
				}
			}
		});
	}

	/**
	* 获得collection句柄
	* @access private
	* @return object
	*/
	private function getCollection(){
		$collection = $this->collection_name;
		return $this->db->$collection;
	}

	/**
	* 获得gridfs句柄
	* @access private
	* @return object
	*/
	private function getGridFS(){
		return $this->db->getGridFS($this->collection_name);
	}

	/**
	* 上传文件到gridfs
	* @access public
	* @param string $file
	* @return bool
	*/
	public function storeFile($file,$meta,$options=[]){
		try{
			$this->formatData($meta);
			return $this->getGridFS()->storeFile($file,$meta,$options);
		}catch(\MongoGridFSException $e){
			$this->error = $e->getMessage();
			return false;
		}finally{
			$this->reset();
		}
	}

	/**
	* 上传字节流到gridfs
	* @access public
	* @param byte $bytes
	* @return bool
	*/
	public function storeBytes($bytes,$meta,$options=[]){
		try{
			$this->formatData($meta);
			return $this->getGridFS()->storeBytes($bytes,$meta,$options);
		}catch(\MongoGridFSException $e){
			$this->error = $e->getMessage();
			return false;
		}finally{
			$this->reset();
		}
	}

	/**
	* 从gridfs中删除一个文件
	* @access public
	* @param string $_id
	* @return bool
	*/
	public function deleteFile($_id){
		try{
			return $this->getGridFS()->delete($_id);
		}catch(\MongoCursorException $e){
			$this->error = $e->getMessage();
			return false;
		}finally{
			$this->reset();
		}
	}

	/**
	* 获取文件内容
	* @access public
	* @param string $_id
	* @return resource
	*/
	public function getFile(){
		$result = $this->fetch();
		return $result ? $result->getBytes() : '';
	}

	/**
	* 获取所有数据库
	* @access public
	* @return array
	*/
	public function listDBs(){
		return $this->link->listDBs();
	}

	/**
	* 获取数据库下所有集合
	* @access public
	* @param string $db
	* @return array
	*/
	public function listCollections($db=null,$options=[]){
		if(!empty($db)){
			return $this->link->$db->listCollections($options);
		}else{
			return $this->db->listCollections($options);
		}
	}
}