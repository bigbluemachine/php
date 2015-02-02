<?php
require 'fdb.php';

// ================================ //

function run($test_fn, $name) {
	global $total, $passed;

	$ret = $test_fn();
	if($ret === true) {
		echo "[PASS] $name\n";
		$passed++;
	} else {
		echo "[FAIL] $name\n>> $ret\n";
	}
	$total++;
}

function print_results() {
	global $total, $passed;

	echo "================================\n";
	if($passed == $total) {
		echo 'All tests pass!';
	} else {
		echo "$passed of $total tests pass.";
	}
}

function eq($data0, $data1) {
	if(count($data0) !== count($data1)) {
		return false;
	}
	foreach($data0 as $k => $v) {
		if(!isset($data1[$k]) || strcmp($v, $data1[$k])) {
			return false;
		}
	}
	return true;
}

// ================================ //

$test_put_get = function () {
	global $db;

	$record_name = 'test_put_get';
	$data = [
		'four' => '4',
		'newline' => "\n",
		'empty' => '',
		'tabs' => "\t \t",
		'backslash' => '\\',
		'with_colons' => 'std::string'
	];
	if($db->put($record_name, $data) !== FDB::ERR_OK) {
		return 'Put failed.';
	}
	$get_data = $db->get($record_name);
	if(!is_array($get_data)) {
		return 'Get failed.';
	}
	if(!eq($data, $get_data)) {
		return 'Data not equal.';
	}
	return true;
};

$test_put_invalid_name = function () {
	global $db;

	$bad_names = ['nope!', 'hack/slash', '+', 'oh no', ''];
	foreach($bad_names as $record_name) {
		if($db->put($record_name, []) !== FDB::ERR_INVALID_RECORD_NAME) {
			return 'Expected \'' . $record_name . '\' to be an invalid name.';
		}
	}
	return true;
};

$test_put_exists = function () {
	global $db;

	$record_name = 'test_put_exists';
	if($db->put($record_name, []) !== FDB::ERR_OK) {
		return 'Put failed.';
	}
	if($db->put($record_name, []) !== FDB::ERR_RECORD_EXISTS) {
		return 'Expected that record exists.';
	}
	return true;
};

$test_put_exists_over = function () {
	global $db;

	$record_name = 'test_put_exists_over';
	if($db->put($record_name, []) !== FDB::ERR_OK) {
		return 'Put failed.';
	}
	if($db->put($record_name, [], true) !== FDB::ERR_OK) {
		return 'Expected to overwrite.';
	}
	return true;
};

$test_put_encode_fail = function () {
	global $db;

	$record_name = 'test_put_encode_fail';
	$bad_names = ['nope!', 'hack/slash', '+', 'oh no', ''];
	foreach($bad_names as $k) {
		$data = [$k => 'something'];
		if($db->put($record_name, $data) !== FDB::ERR_ENCODE) {
			return 'Expected \'' . $k . '\' to be an invalid key.';
		}
	}
	return true;
};

$test_get_nonexistent = function () {
	global $db;

	$record_name = 'test_get_nonexistent';
	if($db->get($record_name) !== FDB::ERR_RECORD_NOT_FOUND) {
		return 'Expected that record does not exist.';
	}
	return true;
};

$test_del_pass = function () {
	global $db;

	$record_name = 'test_del_pass';
	if($db->put($record_name, []) !== FDB::ERR_OK) {
		return 'Put failed.';
	}
	if($db->del($record_name) !== FDB::ERR_OK) {
		return 'Delete failed.';
	}
	return true;
};

$test_del_nonexistent = function () {
	global $db;

	$record_name = 'test_del_nonexistent';
	if($db->del($record_name) !== FDB::ERR_RECORD_NOT_FOUND) {
		return 'Expected that record does not exist.';
	}
	return true;
};

// ================================ //

header('Content-type: text/plain; charset=utf-8');

$total = 0;
$passed = 0;
$db_name = 'test';
$db = new FDB($db_name);
@$db->destroy();

run($test_put_get, 'Put and get record');
run($test_put_invalid_name, 'Put record with invalid name');
run($test_put_exists, 'Put existing record without overwriting');
run($test_put_exists_over, 'Put existing record and overwrite');
run($test_put_encode_fail, 'Fail to encode data');
run($test_get_nonexistent, 'Get nonexistent record');
run($test_del_pass, 'Delete record');
run($test_del_nonexistent, 'Delete nonexistent record');

@$db->destroy();
print_results();
?>
