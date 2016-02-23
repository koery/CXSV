<?php
/**
* @Auth Sang
* @Desc Solr搜索引掣的PHP客户端
* @Date 2015-01-31
*/
namespace Lib;
class Solr{
	private $host,$port,$path;
	//查询参数
	private $params = [];
	private $default_op = 'AND';
	public function __construct($host,$port,$path){
		$this->host = $host;
		$this->port = $port;
		$this->path = $path;
	}

	public function setDefaultOp($op){
		$this->default_op = strtoupper($op);
	}

	/**
	* 执行查询
	* @access public
	* @param string $query
	* @return array
	*/
	public function query($query){
		$this->params['q'] = $query;
		$this->params['q.op'] = $this->default_op;
		empty($this->params['wt']) && $this->params['wt'] = 'json';
		$result = curl($this->buildUrl('select').$this->str2Query());
		$result = json_decode($result,true);
		if(!empty($result['error'])){
			return false;
		}
		return $result;
	}

	/**
	* 根据文档ID删除索引
	* @access public
	* @param string $doc_id 文档ID
	* @param string $path solr路径
	* @param string $key 主键名称，默认为 id
	* @return array
	*/
	public function deleteById($doc_id,$key = 'id'){
		$json = '{"delete":{"'.$key.'":"'.$doc_id.'"}}';
		$this->params['stream.body'] = $json;
		$this->params['stream.contentType'] = 'text/json;charset=utf8';
		$this->params['commit'] = 'true';
		$result = curl($this->buildUrl('update').$this->str2Query());
		$result = json_decode($result,true);
		return $result;
	}

	/**
	* 根据查询条件删除索引
	* @access public
	* @param string query
	* @return array
	*/
	public function deleteByQuery($query){
		if(empty($query)){
			return false;
		}
		$json = '{"delete":{"query":"'.$query.'"}}';
		$this->params['stream.body'] = $json;
		$this->params['stream.contentType'] = 'text/json;charset=utf8';
		$this->params['commit'] = 'true';
		$result = curl($this->buildUrl('update').$this->str2Query());
		$result = json_decode($result,true);
		return $result;
	}

	/**
	* 添加索引，主键重复会复盖
	* @access public
	* @param array $doc
	* @return array
	*/
	public function add($doc){
		if(!is_array($doc)){
			return false;
		}
		$arr = ['add' => ['doc'=>$doc,'commitWithin'=>3000,'overwrite'=>true]];
		$json = json_encode($arr,JSON_UNESCAPED_UNICODE);
		$this->params['stream.body'] = $json;
		$this->params['stream.contentType'] = 'text/json;charset=utf8';
		$result = curl($this->buildUrl('update').$this->str2Query());
		$result = json_decode($result,true);
		return $result;
	}

	/**
	* 设置默认查询字段
	* @access public
	* @param string $field
	* @return self
	*/
	public function setDefaultField($field){
		$this->params['df'] = $field;
		return $this;
	}

	/**
	* 指定查询使用的QueryHandler，默认为“standard”
	* @access public
	* @param string $type
	* @return self
	*/
	public function setQueryType($type){
		$this->params['qt'] = $type;
		return $this;
	}

	/**
	* 指定查询输出结构格式，默认为“xml”。在solrconfig.xml中定义了查询输出格式：xml、json、python、ruby、php、phps、custom
	* @access public
	* @param string $type
	* @return self
	*/
	public function setWriterType($type='xml'){
		$this->params['wt'] = $type;
		return $this;
	}

	/**
	* 是否在查询结果中显示使用的QueryHandler名称
	* @access public
	* @param string $bool
	* @return self
	*/
	public function setEchoHandler($bool){
		$this->params['echoHandler'] = $bool;
		return $this;
	}


	/**
	* 是否显示查询参数。none：不显示；explicit：只显示查询参数；all：所有，包括在solrconfig.xml定义的Query Handler参数
	* @access public
	* @param string $val
	* @return self
	*/
	public function setEchoParams($val = 'none'){
		$this->params['echoParams'] = $val;
		return $this;
	}

	/**
	* 返回的结果是否缩进，默认关闭，用indent=true|on 开启，一般调试json,php,phps,ruby输出才有必要用这个参数
	* @access public
	* @param string $val
	* @return self
	*/
	public function setIndent($bool = 'true'){
		$this->params['indent'] = $bool;
		return $this;
	}

	/**
	* 查询语法的版本，建议不使用它，由服务器指定默认值
	* @access public
	* @param string $version
	* @return self
	*/
	public function setVersion($version){
		$this->params['version'] = $version;
		return $this;
	}

	/**
	* 用于分页定义结果起始记录数，默认为0，从第1条记录开始,分页定义结果每页返回记录数，默认为10
	* @access public
	* @param int $start
	* @param int $rows
	* @return self
	*/
	public function limit($start,$rows){
		$this->params['start'] = $start;
		$this->params['rows'] = $rows;
		return $this;
	}

	/**
	* 排序，格式：sort=<field name>+<desc|asc>[,<fieldname>+<desc|asc>]„ 。
	* 示例：（inStock desc, priceasc）表示先 “inStock” 降序, 再 “price” 升序，默认是相关性降序
	* @access public
	* @param string $sort_order
	* @return self
	*/
	public function sort($sort_order){
		$this->params['sort'] = $sort_order;
		return $this;
	}

	/**
	* 对查询结果进行过滤
	* 使用Filter Query可以充分利用FilterQuery Cache，提高检索性能。作用：在q查询符合结果中同时是fq查询符合的，
	* 例如：q=mm&fq=date_time:[20081001TO 20091031]，找关键字mm，并且date_time是20081001到20091031之间的。
	* fq查询字段后面的冒号和关键字必须有
	* @access public
	* @param string $field 要过滤的字段
	* @param array $value 要过滤的范围，数组 如：[1,100]
	* @return self
	*/
	public function filter($field,$value=[]){
		$this->params['fq'] = $field.':'.'['.str_replace('TO','',$value[0]).' TO '.str_replace('TO','',$value[1]).']';
		return $this;
	}

	/**
	* 指定返回结果字段。以空格“ ”或逗号“,”分隔
	* @access public
	* @param string $fields
	* @return self
	*/
	public function setFieldList($fields){
		$this->params['fl'] = $fields;
		return $this;
	}

	/**
	* 设置返回结果是否显示Debug信息
	* @access public
	* @return self
	*/
	public function debug(){
		$this->params['debugQuery'] = 'true';
		return $this;
	}

	/**
	* 设置当debugQuery=true时，显示其他的查询说明
	* @access public
	* @return self
	*/
	public function setExplainOther(){
		$this->params['explainOther'] = 'true';
		return $this;
	}

	/**
	* 设置查询解析器名称
	* @access public
	* @param string $type
	* @return self
	*/
	public function setDefType($type){
		$this->params['defType'] = $type;
		return $this;
	}

	/**
	* 设置查询超时时间
	* @access public
	* @param int $time
	* @return self
	*/
	public function setTimeAllowed($time){
		$this->params['timeAllowed'] = $time;
		return $this;
	}

	/**
	* 设置是否忽略查询结果返回头信息，默认为“false”
	* @access public
	* @param string $bool
	* @return self
	*/
	public function setOmitHeader($bool){
		$this->params['omitHeader'] = $bool;
		return $this;
	}

	/**
	* 将参数转换成URL查询参数
	* @access private
	* @return string
	*/
	private function str2Query(){
		$str = '';
		foreach ($this->params as $key => $value) {
			$str .= "{$key}=".urlencode($value)."&";
		}
		return '?'.trim($str,'&');
	}


	/**
	* 生成业务URL
	* @access public
	* @param string $type 如：select,update
	* @return string
	*/
	private function buildUrl($type){
		$url = 'http://'.$this->host.':'.($this->port ? $this->port : '80').'/'.trim($this->path,'/').'/'.$type;
		return $url;
	}
}