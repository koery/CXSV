<?php
/*
Auth:Sang
Desc:数据模型类，所有数据库MOD类都继承于它
Date:2014-11-01
*/
namespace Lib;
class Model implements IModel{
	private $options = [];
	private $bind_params = [];
	public $db;
	private $table_name;
	private $fields = [];
	private $prikey = [];
	private $prefix;
	protected $error;
	// 是否免解析，针对一些特殊情况
	private $no_parse = false;
	private $allow_methods = array('table','order','alias','having','group','lock','distinct','auto','filter','validate','result','bind','token');
	public function __construct($table_name='',$prefix=''){
		global $php;
		$prefix = $prefix ? $prefix : ($php->c('db.prefix') ? $php->c('db.prefix') : '');
		$this->prefix = $prefix;
		if(!empty($table_name)){
			$table_name = lcfirst($table_name);
			$table_name = preg_replace_callback('/([A-Z])/',function($matchs){return '_'.strtolower($matchs[1]);},$table_name);
			$this->table_name = $table_name;
		}
		$this->db = $php->db;
		if(method_exists($this, '_init')){
			call_user_func(array($this,'_init'));
		}
	}

	public function getPriKey(){
		if(empty($this->prikey[$this->table_name])){
			$fields = $this->getFields();
			foreach($fields as $name => $field){
				if($field['primary']){
					$this->prikey[$this->table_name] = $name;
					break;
				}
			}
		}
		return $this->prikey[$this->table_name];
	}

	public function getError(){
		return $this->error;
	}

	public function clearError(){
		$this->error = '';
	}

	//释放查询参数，条件等
	public function reset(){
		$this->bind_params = [];
		$this->options = [];
		$this->no_parse = false;
	}

	public function setTableName($table_name){
		return $this->table_name = $table_name;
	}

	public function setPrefix($prefix){
		return $this->prefix = $prefix;
	}

	public function getTableName(){
		return $this->prefix.$this->table_name;
	}

	public function getPrefix(){
		return $this->prefix;
	}

	public function getFields(){
		if(empty($this->fields[$this->table_name])){
			$this->fields[$this->table_name] = $this->db->getFields($this->getTableName());
		}
		return $this->fields[$this->table_name];
	}

	public function getTables($dbname=''){
		return $this->db->getTables($dbname);
	}

	public function getInsertId(){
		return $this->db->getInsertId();
	}

	public function join($join,$type='left') {
        $this->options['join'][$type][] = $join;
        $this->no_parse = true;
        return $this;
    }

    public function leftJoin($join){
    	return $this->join($join,'left');
    }

    public function rightJoin($join){
    	return $this->join($join,'right');
    }

    public function unjoin($unjoin){
    	$this->options['unjoin'][] = $unjoin;
    	return $this;
    }

    public function fields($fields){
    	$this->options['fields'] = $fields;
    	return $this;
    }

    public function limit($offset,$rows=null){
    	if($this->no_parse){
    		if(empty($rows)){
	    		$this->options['limit'] = $offset;
		    }
	    	else{
	    		$this->options['limit'] = $offset.','.$rows;
		    }
		    return $this;
    	}
    	if(empty($rows)){
    		$this->options['limit'] = ':limit___rows';
	    	$this->bind_params['limit___rows'] = $offset; 
	    }
    	else{
    		$this->options['limit'] = ':limit___offset,:limit___rows';
	    	$this->bind_params['limit___offset'] = $offset;
	    	$this->bind_params['limit___rows'] = $rows;
	    }
    	return $this;
    }

	public function where($condition){
		if(!empty($condition) && !is_string($condition)){
			throw new \Exception("The sql condition must be an string", 1006);
		}
		$this->options['where'] = $condition;
		return $this;
	}

	public function query($sql){
		try{
			if($this->no_parse==false){
				$sql = $this->parseSql($sql);
				return $this->db->query($sql,$this->bind_params);
			}else{
				return $this->db->query($sql);
			}
		}finally{
			$this->reset();
		}
	}

	public function select(){
		$sql = $this->buildSql('select');
		return $this->query($sql)->fetchAll();
	}

	public function fetch(){
		$sql = $this->buildSql('select');
		return $this->query($sql)->fetch();
	}

	public function multiInsert($data,$replace=false){
		try{
			if(empty($data)){
				return false;
			}
			if(!isset($data[0])){
				$data = [$data];
			}
			$type = $replace == true ? 'replace' : 'insert';
			$sql = $type.' into `'.$this->getTableName().'`(%s) values(%s)';
			$keys = array_keys($data[0]);
			$fields = array_map(function($v){return '`'.$v.'`';},$keys);
			$fields = join(',',$fields);
			$values = array_map(function($v){return ":{$v}";},$keys);
			$values = join(',',$values);
			$sql = sprintf($sql,$fields,$values);
			return $this->db->multiInsert($sql,$data);
		}finally{
			unset($data);
		}
	}

	public function insert($data,$replace=false){
		try{
			if(empty($data)){
				throw new \Exception("The insert params \$data can't be empty", 111);
			}
			if(isset($data[0])){
				throw new \Exception("Please usage multiInsert!", 111);
			}
			$type = $replace == true ? 'replace' : 'insert';
			$sql = $type.' into `'.$this->getTableName().'`(%s) values(%s)';
			$keys = array_keys($data);
			$fields = array_map(function($v){return '`'.$v.'`';},$keys);
			$fields = join(',',$fields);
			$values = array_map(function($v){return ":{$v}";},$keys);
			$values = join(',',$values);
			$sql = sprintf($sql,$fields,$values);
			$row = $this->db->query($sql,$data)->getInsertId();
			empty($row) && $row = $this->getNumRows();
			return $row;
		}finally{
			unset($data);
		}
	}

	public function delete(){
			if(empty($this->options['where'])){
				throw new \Exception("The sql run in delete,so the condition can't be empty", 109);
			}
			$sql = $this->buildSql('delete');
			return $this->query($sql)->getNumRows();
	}

	public function update($data){
			if(empty($this->options['where'])){
				throw new \Exception("The sql run in update,so the condition can't be empty", 108);
			}
			try{
				$sql = "update `".$this->getTableName().'` set ';
				$keys = array_keys($data);
				foreach($keys as $key){
					$this->bind_params[$key] = $data[$key];
					$sql .= "`{$key}` = :{$key},";
				}
				$sql = substr($sql, 0,-1);
				$sql .= ' where '.$this->parseSql($this->options['where']);
				return $this->db->query($sql,$this->bind_params)->getNumRows();
			}finally{
				$this->reset();
			}
	}

	private function buildSql($sql_head){
		$sql_head = strtoupper($sql_head);
		$sql = $sql_head;
		$tag = in_array($sql_head,array('SELECT','DELETE'));
		$sql_head=='SELECT' && $sql .= isset($this->options['fields']) && !empty($this->options['fields']) ? ' '.$this->options['fields'] : ' *';
		$sql .= $tag ? ' FROM ' : '';
		$sql .= "`{$this->getTableName()}`".(isset($this->options['alias']) && !empty($this->options['alias']) ? ' as '.$this->options['alias'] : '');
		$sql .= isset($this->options['join'])   && !empty($this->options['join'])   ? $this->getJoin($this->options['join']) : '';
		$sql .= isset($this->options['where'])  && !empty($this->options['where'])  ? ' where '.$this->options['where'] : '';
		$sql .= isset($this->options['group'])  && !empty($this->options['group'])  ? ' group by '.$this->options['group'] : '';
		$sql .= isset($this->options['having']) && !empty($this->options['having']) ? ' having '.$this->options['having'] : '';
		$sql .= isset($this->options['order'])  && !empty($this->options['order'])  ? ' order by '.$this->options['order'] : '';
		$sql .= isset($this->options['limit'])  && !empty($this->options['limit'])  ? ' limit '.$this->options['limit'] : '';
		return $sql;
	}

	private function getJoin($join){
		if(empty($join)){
			return '';
		}
		$str = '';
		foreach($join as $type=>$items){
			$str .= " {$type} join ".join(" {$type} join ",$items);
		}
		return $str;
	}

	private function parseSql($sql){
		//解析表达式
		$patt = '/(`?[a-zA-Z0-9_]+`?)\s*(>=|<=|=|<>|>|<|!=|\bin\b|\blike\b|\bnot\s+in\b|\bnot\b|\bhaving\b|\bis\b)\s*(\(.*?\)|\d+(\.\d{1,})?|[\'\"].*?[\'\"]|null)?/iS';
		// preg_match_all($patt, $sql, $matches);
		$sql = preg_replace_callback($patt, array($this,'parseSqlReplaceCallback'), $sql);
		return $sql;
	}

	private function parseSqlReplaceCallback($matches){
		array_shift($matches);
		$field = array_shift($matches);
		$tag = array_shift($matches);
		$value = array_shift($matches);
		$ret = $field.' '.$tag;
		$key = trim($field,'`');
		$tag = preg_replace('/\s{1,}/','_',$tag);
		switch(strtolower($tag)){
			case 'in':
			case 'not_in':
				$m = substr($value,1,-1);
				$m = explode(',',$m);
				$a = [];
				for($i=0;$i<count($m);$i++){
					$k = $key.'_'.$tag.'_'.$i;
					isset($this->bind_params[$k]) && $k = $k.'_'.rand(1,999999);
					$this->bind_params[$k] = $m[$i];
					$a[] = ':'.$k;
				}
				$ret .= '('.join(',',$a).')';
			break;
			default:
				$k = trim(($field),'`');
				isset($this->bind_params[$k]) && $k = $k.'_'.rand(1,999999);
				$this->bind_params[$k] = trim(trim($value,'"'),"'");
				$ret .= ' :'.$k;
			break;
		}
		return $ret;
	}

	public function startTrans(){
		return $this->db->startTrans();
	}

	public function inTrans(){
		return $this->db->inTrans();
	}

	public function commit(){
		return $this->db->commit();
	}

	public function rollback(){
		return $this->db->rollback();
	}

	public function getNumRows(){
		return $this->db->getNumRows();
	}

	public function getLastSql(){
		return $this->db->getLastSql();
	}

	public function __call($method,$args){
		if(in_array(strtolower($method),$this->allow_methods,true)) {
            // 连贯操作的实现
            $this->options[strtolower($method)] =   $args[0];
            return $this;
        }elseif(in_array(strtolower($method),array('count','sum','min','max','avg'),true)){
            // 统计查询的实现
            $this->options['fields'] =  $method.'('.(isset($args[0])?$args[0]:'*').')';
            return $this->getColumn(0);
        }else{
        	throw new \Exception("The method {$method} don't be exist", 129);
        }
	}

	/**
	* 字段加法
	* @access public
	* @param string $field
	* @param numeric $val
	* @return bool
	*/
	public function setInc($field,$val){
		if(empty($this->options['where'])){
			throw new \Exception("The sql run in update,so the condition can't be empty", 108);
		}
		// 检查参数
		if((is_array($field) && !is_array($val)) || (!is_array($field) && is_array($val))){
			throw new \Exception("Fields and numeric types must be equal", 130);
		}
		$set = [];
		if(is_array($val)){
			if(count($val) != count($field)){
				throw new \Exception("field and numerical number must be equal", 132);
			}
			foreach($val as $k=>$v){
				if(!is_numeric($v)){
					throw new \Exception("Value must be a number", 131);
				}else{
					$set[] = "`{$field[$k]}`=`{$field[$k]}`+{$v}";
				}
			}
		}elseif(!is_numeric($val)){
			throw new \Exception("Value must be a number", 131);
		}else{
			$set[] = "`{$field}`=`{$field}`+{$val}";
		}
		$set = join(',',$set);
		$sql = 'update `'.$this->getTableName().'` set '.$set.' where '.$this->options['where'];
		try{
			return $this->db->query($sql)->getNumRows();
		}catch(\Exception $e){
			$this->error = $e->getMessage();
			return false;
		}finally{
			$this->reset();
		}
	}

	/**
	* 字段减法
	* @access public
	* @param mix $field 可以是单个字段，也可以是多个
	* @param mix $val 与field对应个数
	* @return bool
	*/
	public function setDec($field,$val){
		if(empty($this->options['where'])){
			throw new \Exception("The sql run in update,so the condition can't be empty", 108);
		}
		// 检查参数
		if((is_array($field) && !is_array($val)) || (!is_array($field) && is_array($val))){
			throw new \Exception("Fields and numeric types must be equal", 130);
		}
		$set = [];
		if(is_array($val)){
			if(count($val) != count($field)){
				throw new \Exception("field and numerical number must be equal", 132);
			}
			foreach($val as $k=>$v){
				if(!is_numeric($v)){
					throw new \Exception("Value must be a number", 131);
				}else{
					$set[] = "`{$field[$k]}`=`{$field[$k]}`-{$v}";
				}
			}
		}elseif(!is_numeric($val)){
			throw new \Exception("Value must be a number", 131);
		}else{
			$set[] = "`{$field}`=`{$field}`-{$val}";
		}
		$set = join(',',$set);
		$sql = 'update `'.$this->getTableName().'` set '.$set.' where '.$this->options['where'];
		try{
			return $this->db->query($sql)->getNumRows();
		}catch(\Exception $e){
			$this->error = $e->getMessage();
			return false;
		}finally{
			$this->reset();
		}
	}
	

	public function getColumn($index_key){
		$sql = $this->buildSql('select');
		$this->query($sql);
		return $this->db->getColumn($index_key);
	}

	public function validate($data){
		if(empty($data)){
			return false;
		}
		$fields = $this->getFields();
		foreach($data as $key=>$val){
			if(!isset($fields[$key])){
				unset($data[$key]);
			}
			$field = $fields[$key];
			$data_type = explode('(',$field['type'],2)[1];
			switch($data_type){
				case 'int':
				case 'tinyint':
				case 'smallint':
				case 'mediumint':
				case 'integer':
				case 'bigint':
				$data[$key] = intval($data[$key]);
				break;
				case 'double':
				case 'float':
				case 'decimal':
				case 'numeric':
				$data[$key] = floatval($data[$key]);
				break;
				case 'char':
				case 'varchar':
				case 'tinytext':
				case 'text':
				case 'mediumtext':
				case 'longtext':
				$data[$key] = strval($data[$key]);
				break;
				case 'date':
				case 'time':
				case 'datetime':
				$data[$key] = is_int($data[$key]) ? date('Y-m-d H:i:s',$data[$key]) : $data[$key];
				break;
				default:
				$data[$key] = trim($data[$key]);
				break;
			}
		}
		return $data;
	}

	/**
	* 检查一个表是否存在
	* @access public
	* @param string $table_name
	* @return bool
	*/
	public function tableExists($table_name){
		return !!$this->db->query("show tables like '{$table_name}'")->fetch();
	}
}

interface IModel{
	public function select();
	public function insert($data,$replace=false);
	public function delete();
	public function update($data);
}