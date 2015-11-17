# SqlBuilder
支持数据绑定的SQL构建类

~~~
<?php
error_reporting(E_ALL);
require __DIR__ . '/SqlBindBuilder.php';

// 两种通配符不能混合使用

// SELECT 不支持绑定
unset($bindArray);
$selectArray = ['name', 'sex', 'b.age'];
echo "SELECT " . SqlBindBuilder::build(SqlBindBuilder::SELECT, []) . PHP_EOL;
echo "SELECT " . SqlBindBuilder::build(SqlBindBuilder::SELECT, $selectArray) . PHP_EOL;

// ORDER 不支持绑定
unset($bindArray);
$orderArray = [
	['age', 'desc'],
	['sex', 'asc'],
];
echo "ORDER BY" . SqlBindBuilder::build(SqlBindBuilder::ORDER, $orderArray) . PHP_EOL;


// INSERT
unset($bindArray);
$insertArray = [
	'name' => 'xiaofeng',
	'sex'	=> 1,
	'age'	=> 25,
];
// echo "INSERT INTO t" . SqlBindBuilder::build(SqlBindBuilder::INSERT, $insertArray, $bindArray, '?') . PHP_EOL;
// print_r($bindArray);
echo "INSERT INTO t" . SqlBindBuilder::build(SqlBindBuilder::INSERT, $insertArray, $bindArray, ':') . PHP_EOL;
print_r($bindArray);

// MULTI_INSERT
unset($bindArray);
$multiInsertArray = [
[
	'name' => 'xiaofeng',
	'sex'	=> 1,
	'age'	=> 25,
],
[
	'name' => 'xiaofeng1',
	'sex'	=> 2,
	'age'	=> 24,
],
];
// echo "INSERT INTO t" . SqlBindBuilder::build(SqlBindBuilder::MULTI_INSERT, $multiInsertArray, $bindArray, '?') . PHP_EOL;
// print_r($bindArray);
echo "INSERT INTO t" . SqlBindBuilder::build(SqlBindBuilder::MULTI_INSERT, $multiInsertArray, $bindArray, ':') . PHP_EOL;
print_r($bindArray);

// UPDATE
unset($bindArray);
$updateArray = [
	'name' => 'xiaofeng',
	'sex'	=> 1,
	'age'	=> 25,
];
// echo "UPDATE t SET" . SqlBindBuilder::build(SqlBindBuilder::UPDATE, $updateArray, $bindArray, '?') . PHP_EOL;
// print_r($bindArray);
echo "UPDATE t SET" . SqlBindBuilder::build(SqlBindBuilder::UPDATE, $updateArray, $bindArray, ':') . PHP_EOL;
print_r($bindArray);

// WHERE
unset($bindArray);
$where = [
	['name', 'like', 'xiao'],
	'OR' => [
		['age', '<', 10],
		['age', '>', 20],
	],
	['sex', 'not in', [0, 2]],
];
// echo "WHERE " . SqlBindBuilder::build(SqlBindBuilder::WHERE, $where, $bindArray, '?') . PHP_EOL;
// print_r($bindArray);
echo "WHERE " . SqlBindBuilder::build(SqlBindBuilder::WHERE, $where, $bindArray, ':') . PHP_EOL;
print_r($bindArray);

// 综合
unset($bindArray);
$selectArray = ['name', 'sex', 'b.age'];
echo "SELECT" . SqlBindBuilder::build(SqlBindBuilder::SELECT, $selectArray) .
"FROM t WHERE" . SqlBindBuilder::build(SqlBindBuilder::WHERE, $where, $bindArray) . PHP_EOL;;
print_r($bindArray);

unset($bindArray);
$selectArray = ['name', 'sex', 'b.age'];
echo "UPDATE t SET" . SqlBindBuilder::build(SqlBindBuilder::UPDATE, $updateArray, $bindArray) .
"FROM t WHERE" . SqlBindBuilder::build(SqlBindBuilder::WHERE, $where, $bindArray) . PHP_EOL;;
print_r($bindArray);

// 自定义拼接必须使用getBindkey方法
unset($bindArray);
$selectArray = ['name', 'sex', 'b.age'];
echo "UPDATE t SET" . SqlBindBuilder::build(SqlBindBuilder::UPDATE, $updateArray, $bindArray) .
"FROM t WHERE name like " . SqlBindBuilder::getBindkey($bindArray, 'name', 'xiao') . "%" . PHP_EOL;
print_r($bindArray);

//

~~~
