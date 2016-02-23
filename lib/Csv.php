<?php
/**
* @Auth Sang
* @Desc 生成csv文件
* @Date 2013-03-26
*/
namespace Lib;
class Csv {
	// 文件名
	private $filename;
	private $csv_string;
	// 临时存放数据的变量
	private $tmp;
	public function __construct() {
		$this->tmp = array ();
	}
	public function setFileName($filename) {
		$this->filename = $filename;
		return $this;
	}
	public function put($data) {
		if ($data) {
			$this->tmp [] = $data;
		}
	}
	public function create() {
		/**
		 * **************************************************************************************************************************
		 * 新建csv数据
		 * /***************************************************************************************************************************
		 */
		if(empty($this->tmp)){
			return false;
		}
		$csv_string = null;
		$csv_row = array ();
		foreach ( $this->tmp as $key => $csv_item ) {
			if ($key === 0) {
				$csv_row [] = implode ( "\t", $csv_item );
				continue;
			}
			$current = array ();
			foreach ( $csv_item as $item ) {
				$current [] = is_numeric ( $item ) ? $item : '"' . str_replace ( '\"', '\"\"', $item ) . '"';
			}
			$csv_row [] = implode ( "\t", $current );
		}
		$csv_string = implode ( "\r\n", $csv_row );
		$this->csv_string = "\xFF\xFE" . mb_convert_encoding ( $csv_string, 'UCS-2LE', 'UTF-8' );
		unset($this->tmp);
		return $this;
	}
	function save() {
		mkdir(dirname($this->filename),0755,true);
		file_put_contents($this->filename, $this->csv_string);
		return $this;
	}
	public function download() {
		if(!$this->csv_string){
			$this->create();
		}
		$file_name = basename ( $this->filename );
		/**
		 * **************************************************************************************************************************
		 * 输出
		 * /***************************************************************************************************************************
		 */
		set_header("Content-type","text/csv" );
		set_header("Content-Type","application/force-download" );
		set_header("Accept-Length",strlen ( $this->csv_string ) );
		set_header("Content-Disposition","attachment; filename=" . ($file_name ? $file_name : date ( 'Y-m-d' )) );
		set_header('Expires',0 );
		set_header('Pragma','public' );
		echo $this->csv_string;
	}

	/**
	* zip压缩下载
	*/
	public function downloadzip(){
		##
	}

	public function downloadFromFile($file) {
		if (! is_file ( $file )) {
			return false;
		}
		
		$csv_string = file_get_contents($file);
		$file_name = basename ( $file );
		/**
		 * **************************************************************************************************************************
		 * 输出
		 * /***************************************************************************************************************************
		 */
		set_header("Content-type","text/csv" );
		set_header("Content-Type","application/force-download" );
		set_header("Accept-Length",strlen ( $csv_string ) );
		set_header("Content-Disposition","attachment; filename=" . $file_name );
		set_header('Expires','0' );
		set_header('Pragma','public' );
		echo $csv_string;
	}
}