<?php
/*
	Functions to manage a file system database. Each database is contained
	entirely within a directory; the name of the database is its path. Each
	record is represented by a file containing a collection of key-value pairs,
	each on a separate line.

	Names must contain only letters, digits, underscores, and hyphens. Values
	should be similar to C strings; newlines are encoded '\n' and '\' is encoded
	'\\'. Strange ASCII values may cause strange behavior.
*/

// ================================ //

class FDB {
	private $name;

	const ERR_OK = 0;
	const ERR_GENERAL = 1;
	const ERR_RECORD_NOT_FOUND = 2;
	const ERR_RECORD_EXISTS = 3;
	const ERR_ENCODE = 4;
	const ERR_DECODE = 5;
	const ERR_INVALID_RECORD_NAME = 6;

	// ================================ //

	public function __construct($db_name) {
		if(!self::is_id_valid($db_name)) {
			die('Invalid database name.');
		}

		$this->name = $db_name;
	}

	// ================================ //

	/*
		Adds a record.
		record_name -- Name of the record.
		data -- Table of key-value pairs.
		over -- Whether to force overwrite.
	*/
	public function put($record_name, $data, $over = false) {
		if(!self::is_id_valid($record_name)) {
			return self::ERR_INVALID_RECORD_NAME;
		}

		$ret = file_exists($this->name) || mkdir($this->name, 0777, true);
		if(!$ret) {
			return self::ERR_GENERAL;
		}

		$path = $this->get_path($record_name);
		if(!$over && file_exists($path)) {
			return self::ERR_RECORD_EXISTS;
		}

		$encoded = self::encode($data);
		if($encoded === false) {
			return self::ERR_ENCODE;
		}

		$fp = fopen($path, 'wb');
		if(!$fp) {
			return self::ERR_GENERAL;
		}

		if(strlen($encoded) > 0 && !fwrite($fp, $encoded)) {
			return self::ERR_GENERAL;
		}

		fclose($fp);
		return self::ERR_OK;
	}

	/*
		Gets a record.
		record_name -- Name of the record.
	*/
	public function get($record_name) {
		if(!$this->has($record_name)) {
			return self::ERR_RECORD_NOT_FOUND;
		}

		$path = $this->get_path($record_name);
		$str = file_get_contents($path);
		if($str === false) {
			return self::ERR_GENERAL;
		}

		$decoded = self::decode($str);
		if($decoded === false) {
			return self::ERR_DECODE;
		}

		return $decoded;
	}

	/*
		Checks whether a record exists. Does not ensure that get() works.
		record_name -- Name of the record.
	*/
	public function has($record_name) {
		if(!self::is_id_valid($record_name)) {
			return false;
		}

		$path = $this->get_path($record_name);
		return file_exists($path);
	}

	/*
		Deletes a record.
		record_name -- Name of the record.
	*/
	public function del($record_name) {
		if(!$this->has($record_name)) {
			return self::ERR_RECORD_NOT_FOUND;
		}

		$path = $this->get_path($record_name);
		if(!unlink($path)) {
			return self::ERR_GENERAL;
		}

		return self::ERR_OK;
	}

	/*
		Deletes the entire database.
	*/
	public function destroy() {
		if(file_exists($this->name)) {
			self::del_tree($this->name);
		}

		return self::ERR_OK;
	}

	// ================================ //

	private static function is_id_valid($key) {
		return preg_match('/^[A-Za-z0-9_-]+$/', $key);
	}

	private function get_path($record_name) {
		return $this->name . '/' . $record_name;
	}

	private static function encode($data) {
		if(!is_array($data)) {
			return false;
		}

		$arr = [];
		foreach($data as $k => $v) {
			if(!self::is_id_valid($k)) {
				return false;
			}
			array_push($arr, $k . ':' . self::encode_value($v));
		}

		return implode("\n", $arr);
	}

	private static function encode_value($str) {
		$arr = [];
		for($i = 0, $l = strlen($str); $i < $l; $i++) {
			$c = $str[$i];
			if($c === "\n") {
				array_push($arr, '\\n');
			} else if($c === '\\') {
				array_push($arr, '\\\\');
			} else {
				array_push($arr, $c);
			}
		}
		return implode('', $arr);
	}

	private static function decode($str) {
		$lines = explode("\n", $str);
		$arr = [];
		for($i = 0, $l = count($lines); $i < $l; $i++) {
			$line = $lines[$i];
			if(strlen($line) === 0) {
				continue;
			}
			$pos = strpos($line, ':');
			// false and 0 are both invalid.
			if(!$pos) {
				return false;
			}
			$k = substr($line, 0, $pos);
			if(!self::is_id_valid($k) || isset($arr[$k])) {
				return false;
			}
			$v = self::decode_value(substr($line, $pos + 1));
			if($v === false) {
				return false;
			}
			$arr[$k] = $v;
		}
		return $arr;
	}

	private static function decode_value($str) {
		$arr = [];
		for($i = 0, $l = strlen($str); $i < $l; $i++) {
			$c = $str[$i];
			if($c === '\\') {
				if(++$i >= $l) {
					return false;
				}
				if($str[$i] === 'n') {
					array_push($arr, "\n");
				} else if($str[$i] === '\\') {
					array_push($arr, '\\');
				} else {
					return false;
				}
			} else {
				array_push($arr, $c);
			}
		}
		return implode('', $arr);
	}

	private static function del_tree($dir) {
		$files = scandir($dir);
		foreach($files as $file) {
			if($file === '.' || $file === '..') {
				continue;
			}
			$path = "$dir/$file";
			if(is_dir($path)) {
				self::del_tree($path);
			} else {
				unlink($path);
			}
		}
		return rmdir($dir);
	}
}
?>
