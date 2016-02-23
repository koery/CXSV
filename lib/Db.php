<?php
/*
Auth:Sang
Desc:MYSQL数据库连接类，读写分离
Date:2014-11-01
*/
namespace Lib;
class Db{
	//读实例
	private $read_link = null;
	//写实例
	private $write_link = null;
	//当前选择实例
	private $current_link = null;
	//当前运行sql
	private $current_sql = null;
	//当前运行的stmt
	private $current_stmt = null;
	//当前从数据库取出的单行数据
	private $current_data = [];
	//连接类型 read/write
	private $type = null;
	//是否进行事务处理
    private $transaction = 0;
    //配置
    private $config = [];
    //启动时间
    private $start_time;
    //mysql设置的超时时间 读
    private $read_timeout;
    //mysql设置的超时时间 写
    private $write_timeout;

    // 绑定参数
    private $bind_params = [];

    // 断线重连次数
    private $retry_num = 0;

	public function __construct($config){
		//获取配置
		$this->config = $config;
		//设置运行开始时间
		$this->start_time = time();
		//如果读写分离
		if(isset($config['read']) && isset($config['write'])){
			foreach(array('read','write') as $type){
				list($host,$port,$user,$password,$dbname) = explode(':',$config[$type]);
				$link = $type.'_link';
				$timeout = $type.'_timeout';
				$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s',$host,$port,$dbname);
				try{
					//以pdo方式连接数据库
					$this->$link = new \PDO($dsn,$user,$password,array(\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC,\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_PERSISTENT=>false,\PDO::ATTR_EMULATE_PREPARES=>true));
					//设置字符集
					$this->$link->exec("set names ".($config['charset'] ? $config['charset'] : 'utf8'));
					//设置sql_mode
					$this->$link->exec('set sql_mode = ""');
					//查询mysql服务器的超时时间并记录下来
					$ret = $this->$link->query("show global variables like 'wait_timeout'")->fetch();
					$this->$timeout = $ret['Value'];
				}catch(\PDOException $e){
					throw new \Exception($e->getMessage(),$e->getCode());
				}
			}
		}elseif(isset($config['host'])){
			list($host,$port,$user,$password,$dbname) = explode(':',$config['host']);
			$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s',$host,$port,$dbname);
			try{
				//以pdo方式连接数据库
				$this->read_link = new \PDO($dsn,$user,$password,array(\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC,\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_PERSISTENT=>false,\PDO::ATTR_EMULATE_PREPARES=>true));
				//设置字符集
				$this->read_link->exec("set names ".($config['charset'] ? $config['charset'] : 'utf8'));
				//设置sql_mode
				$this->read_link->exec('set sql_mode = ""');
				//查询mysql服务器的超时时间并记录下来
				$ret = $this->read_link->query("show global variables like 'wait_timeout'")->fetch();
				$this->read_timeout = $this->write_timeout = $ret['Value'];
				m_log('mysql start at '.$this->read_timeout,'db_exception');
			}catch(\PDOException $e){
				throw new \Exception($e->getMessage(),$e->getCode());
			}
			//读写不分离，写实例＝读实例
			$this->write_link = &$this->read_link;
		}		
	}

	/*释放资源*/
	public function reset(){
		if($this->current_stmt){
			$this->current_stmt->closeCursor();
			$this->current_stmt = null;
		}
		$this->current_data = [];
    }

    /*多条插入*/
	public function multiInsert($sql,$data){
		$this->reset();
		if(empty($data) || !is_array($data)){
			throw new \Exception("The Data Must Be An Arrays", 1007);
		}
		if(!isset($data[0])){
			$data = [$data];
		}
		$this->current_sql = $sql;
		$is_exec = 0;
		//超时重连
		$this->checkTimeOut();
		$in_trans = $this->inTrans();
		try{
			//利用事务进行多条插入
			!$in_trans && $this->startTrans();
			$db = &$this->current_link;
			$stmt = $db->prepare($sql);
			$insert_ids = [];
			foreach($data as $bind_data){
				$stmt->execute($bind_data);
				$is_exec++;
				$insert_ids[] = $db->lastInsertId();
			}
			!$in_trans && $this->commit();
		}catch(\Exception $e){
			if($this->transaction){
				$this->rollback();
			}
			$this->error($e);
		}finally{
			unset($bind_data);
			if($is_exec){
				$stmt->closeCursor();
			}
		}
		return count($insert_ids) == 1 ? $insert_ids[0] : $insert_ids;
	}

	public function query($sql,$bind_params=array()){
		$this->reset();
		$is_exec = 0;
		//超时重连
		$this->checkTimeOut();
		try{
			$this->current_link = $this->getDbBySql($sql);
			$db = $this->current_link;

			$this->current_sql = $sql;
			$stmt = $db->prepare($sql);
			$this->bind_params = &$bind_params;
			extract($bind_params);
			foreach($bind_params as $key=>$val){
				$stmt->bindParam(":{$key}",$$key,$this->getDataType($val));
			}
			$stmt->execute();
			$is_exec++;
			$this->current_stmt = &$stmt;
		}catch(\Exception $e){
			if($this->transaction>0){
				$this->rollback();
			}
			$stmt->closeCursor();
			// 超时重连
			m_log(['code'=>$e->getCode(),'message'=>$e->getMessage(),'sql'=>$sql,'params'=>$bind_params,'trace'=>\Lib\Error::trace()],'db_exception');
			if(strpos($e->getMessage(),'2006')!==false || stripos($e->getMessage(), '2013')!==false){
				m_log('mysql reconnect','db_exception');
				self::__construct($this->config);
				return $this->query($sql,$bind_params);
			}
			$this->reset();
			$this->error($e);
		}
		return $this;
	}

	private function checkTimeOut(){
		$time = time();
		if($time-$this->start_time>=$this->read_timeout-100 || $time-$this->start_time>=$this->write_timeout-100){
			self::__construct($this->config);
		}
	}

	private function getDataType($v){
		switch(gettype($v)){
			case 'boolear':
				return \PDO::PARAM_BOOL;
				break;
			case 'integer':
				return \PDO::PARAM_INT;
				break;
			case 'double':
			case 'string':
				return \PDO::PARAM_STR;
				break;
			default:
				return \PDO::PARAM_STR;	
				break;
		}
	}

	public function next(){
		$this->current_data = $this->current_stmt->fetch();
		if(empty($this->current_data)){
			$this->current_stmt->closeCursor();
			return false;
		}
		return $this->current_data;
	}

	public function fetch(){
		$data = $this->current_stmt->fetch();
		$this->current_stmt->closeCursor();
		return $data;
	}

	public function get($key=''){
		return empty($key) ? $this->current_data : (isset($this->current_data[$key]) ? $this->current_data[$key] : null);
	}

	public function fetchAll(){
		try{
			return $this->current_stmt ? $this->current_stmt->fetchAll() : [];
		}finally{
			$this->current_stmt && $this->current_stmt->closeCursor();
		}
	}

	public function getNumRows(){
		try{
			return $this->current_stmt ? $this->current_stmt->rowCount() : 0;
		}finally{
			$this->current_stmt && $this->current_stmt->closeCursor();
		}
	}

	public function getLastSql(){
		return $this->current_sql;
	}

	public function getInsertId(){
		return $this->current_link->lastInsertId();
	}

	public function getColumn($index=0){
		try{
			return $this->current_stmt ? $this->current_stmt->fetchColumn($index) : [];
		}finally{
			$this->current_stmt && $this->current_stmt->closeCursor();
		}
	}

	/*启动事务*/
	public function startTrans(){
		if($this->transaction>0){
			throw new \Exception("You Have A Transaction No Commit!", 1002);
		}
		$this->current_link = $this->write_link;
		$this->current_link->beginTransaction();
		$this->transaction++;
		return $this;
	}

	public function inTrans(){
		return $this->transaction>0;
	}

	public function commit(){
		if($this->transaction > 0){
			$result = $this->current_link->commit();
			$this->transaction --;
		}else{
			throw new \Exception("Please startTrans First!", 1003);
		}
		return $this;
	}

	/**
     * 事务回滚
     * @access public
     * @return boolen
     */
    public function rollback() {
        if ($this->transaction > 0) {
            $result = $this->current_link->rollback();
            $this->transaction --;
        }else{
            throw new \Exception("Please startTrans First!", 1004);
        }
        return true;
    }

    /**
     * 取得数据表的字段信息
     * @access public
     * @return array
     */
    public function getFields($tableName) {
        $result =   $this->query("SHOW FULL FIELDS FROM `{$tableName}`")->fetchAll();
        $info   =   array();
        if($result) {
            foreach ($result as $key => $val) {
                $info[$val['Field']] = array(
                    'name'    => $val['Field'],
                    'type'    => $val['Type'],
                    'notnull' => (bool) ($val['Null'] === 'NO'), // not null is empty, null is yes
                    'default' => $val['Default'],
                    'primary' => (bool)($val['Key'] == 'PRI'),
                    'autoinc' => (bool)($val['Extra'] == 'auto_increment'),
                    'comment' => $val['Comment'],
                );
            }
        }
        return $info;
    }

    /**
     * 取得数据库的所有表
     * @access public
     * @return array
     */
    public function getTables($dbName='') {
        $sql    = !empty($dbName) ? "SHOW TABLES FROM `{$dbName}`" : 'SHOW TABLES ';
        $result =   $this->query($sql)->fetchAll();
        $info   =   array();
        if($result) {
            foreach ($result as $key => $val) {
                $info[$key] = current($val);
            }
        }
        return $info;
    }


	private function getDbBySql($sql){
		if($this->transaction){
			$this->type = 'write';
			return $this->current_link;
		}

		list($query_type,$_) = preg_split('/\s+/',$sql,2);
		$db = false;
		switch(strtolower($query_type)){
			case 'select':
			case 'show':
				$this->type = 'read';
				$db = &$this->read_link;
			break;
			default:
            	$this->type = 'write';
                $db = &$this->write_link;
            break;
		}
		return $db;
	}

	private function error($e){
		throw new DBException($e->getCode().':'.$e->getMessage().' [SQL] '.$this->current_sql.' . [PARAMS] '.json_encode($this->bind_params,JSON_UNESCAPED_UNICODE), is_numeric($e->getCode()) ? $e->getCode() : 2 , $e->getFile(),$e->getLine());
	}
}

class DBException extends \Exception{
	public function __construct($msg,$code,$file,$line){
		$this->message = $msg;
		$this->code = $code;
		$this->file = $file;
		$this->line = $line;
	}
}