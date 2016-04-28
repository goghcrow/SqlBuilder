<?php
/**
 * User: xiaofeng
 * Date: 2016/4/26
 * Time: 23:32
 * @require PHP7 assert
 */
namespace xiaofeng\sql;
require __DIR__ . "/sql.php";
error_reporting(E_ALL);

assert(pdoType(null) === \PDO::PARAM_NULL);
assert(pdoType(true) === \PDO::PARAM_BOOL);
assert(pdoType(1) === \PDO::PARAM_INT);
assert(pdoType(1.1) === \PDO::PARAM_STR);
assert(pdoType("str") === \PDO::PARAM_STR);
assert(pdoType(fopen(__FILE__, 'r')) === \PDO::PARAM_LOB);


/** @noinspection PhpUndefinedVariableInspection */
_($bindArray, "name", "xiaofeng");
_($bindArray, "age", 26);
assert($bindArray === array (
        ':name' => 'xiaofeng',
        ':age' => 26,
        ));
unset($bindArray);

/** @noinspection PhpUndefinedVariableInspection */
_($bindArray, ":name", "xiaofeng");
_($bindArray, ":age", 26);
assert($bindArray === array (
        ':name' => 'xiaofeng',
        ':age' => 26,
    ));
unset($bindArray);

/** @noinspection PhpUndefinedVariableInspection */
_($bindArray, "info.field1", 1);
_($bindArray, "info.field2", 2);
assert($bindArray === array (
        ':info_field1' => 1,
        ':info_field2' => 2,
    ));
unset($bindArray);

/** @noinspection PhpUndefinedVariableInspection */
_($bindArray, "info.field3", 3);
_($bindArray, "info.field3", 3);
_($bindArray, "info.field3", 3);
assert($bindArray === array (
        ':info_field3' => 3,
        ':info_field3_' => 3,
        ':info_field3__' => 3,
    ));
unset($bindArray);

/** @noinspection PhpUndefinedVariableInspection */
_($bindArray, [
    "field1" => "f1",
    "field2" =>  2,
    ":field3"=> true,
]);
assert($bindArray === array (
        ':field1' => 'f1',
        ':field2' => 2,
        ':field3' => true,
    ));
unset($bindArray);

assert(select([]) === " * ");
assert(select(["field1", "field2", "field3"]) === " field1, field2, field3 ");

assert(insert($bindArray, [
        'name' => 'xiaofeng',
        'age' => 26,
    ]) === " (name, age) VALUES (:name, :age) ");
assert($bindArray === array (
        ':name' => 'xiaofeng',
        ':age' => 26,
    ));
unset($bindArray);

assert(insert($bindArray, [
        'name' => 'xiaofeng',
        'age' => 26,
    ], '?') === " (name, age) VALUES (?, ?) ");
assert($bindArray === ['xiaofeng', 26,]);
unset($bindArray);

assert(batchInsert($bindArray, [[
        "id" => 1,
        "name" => "n1"
    ], [
        "id" => 2,
        "name" => "n2"
    ]]) === " (id, name) VALUES (:id_0_0, :name_0_1),(:id_1_0, :name_1_1) ");
assert($bindArray === array (
        ':id_0_0' => 1,
        ':name_0_1' => 'n1',
        ':id_1_0' => 2,
        ':name_1_1' => 'n2',
    ));
unset($bindArray);

assert(batchInsert($bindArray, [[
        "id" => 1,
        "name" => "n1"
    ], [
        "id" => 2,
        "name" => "n2"
    ]], '?') === " (id, name) VALUES (?, ?), (?, ?) ");
assert($bindArray === array (1, 'n1', 2, 'n2',));
unset($bindArray);

assert(update($bindArray, [
        "id" => 1,
        "name" => "n1"
    ], ":") === " id = :id, name = :name ");
assert($bindArray === array (
        ':id' => 1,
        ':name' => 'n1',
    ));
unset($bindArray);

assert(update($bindArray, [
        "id" => 1,
        "name" => "n1"
    ], "?") === " id = ?, name = ? ");
assert($bindArray === array (1, 'n1'));
unset($bindArray);

assert(like($bindArray, "`name`", "xiaofeng%") === " `name`  LIKE :name ");
assert($bindArray === array (':name' => 'xiaofeng%',));
unset($bindArray);

assert(like($bindArray, "`name`", "xiaofeng%", "?") === " `name`  LIKE ? ");
assert($bindArray === array ('xiaofeng%',));
unset($bindArray);

assert(notLike($bindArray, "`name`", "xiaofeng%") === " `name` NOT LIKE :name ");
assert($bindArray === array (':name' => 'xiaofeng%',));
unset($bindArray);

assert(notLike($bindArray, "`name`", "xiaofeng%", "?") === " `name` NOT LIKE ? ");
assert($bindArray === array ('xiaofeng%',));
unset($bindArray);


assert(in($bindArray, "id", range(1, 3)) === " id  IN (:id_0, :id_1, :id_2) ");
assert($bindArray === array (
        ':id_0' => 1,
        ':id_1' => 2,
        ':id_2' => 3,
    ));
unset($bindArray);


assert(notIn($bindArray, "id", range(1, 3)) === " id NOT IN (:id_0, :id_1, :id_2) ");
assert($bindArray === array (
        ':id_0' => 1,
        ':id_1' => 2,
        ':id_2' => 3,
    ));
unset($bindArray);

assert(in($bindArray, "id", range(1, 3), "?") === " id  IN (?, ?, ?) ");
assert($bindArray === array (1, 2, 3,));
unset($bindArray);

assert(notIn($bindArray, "id", range(1, 3), "?") === " id NOT IN (?, ?, ?) ");
assert($bindArray === array (1, 2, 3,));
unset($bindArray);

assert(orderBy(["id" => "asc", "rank" => "desc"], ["id", "rank"]) === " id ASC , rank DESC ");

$where = [
    ['name', 'like', 'xiao%'],
    'or' => [
        ['age', '<', 10],
        ['age', '>', 20],
    ],
    ['sex', 'not in', [0, 2]],
];
assert(where($bindArray, $where) === "  name  LIKE :name  AND ( age < :age OR age > :age_ ) AND  sex NOT IN (:sex_0, :sex_1)  ");
assert($bindArray === array (
        ':name' => 'xiao%',
        ':age' => 10,
        ':age_' => 20,
        ':sex_0' => 0,
        ':sex_1' => 2,
    ));
unset($bindArray);

assert(where($bindArray, $where, "or") === "  name  LIKE :name  OR ( age < :age OR age > :age_ ) OR  sex NOT IN (:sex_0, :sex_1)  ");
unset($bindArray);

assert(where($bindArray, $where, "or", "?") === "  name  LIKE ?  OR ( age < ? OR age > ? ) OR  sex NOT IN (?, ?)  ");
assert($bindArray === array ('xiao%', 10, 20, 0, 2,));
unset($bindArray);

$where = [
    'or' => [
        ['age', '<', 10],
        ['age', '>', 20],
    ],
];

assert(where($bindArray, $where) === " ( age < :age OR age > :age_ ) ");
unset($bindArray);

$from = "t_company";
$select = ["id", "company_id"];
$where = [
    ["company_id", "in", [167,180,200,201,203,214,219]],
    "or" => [
        ['`name`', "not like", "沙县%"],
        ["type", "<>", 0],
    ]
];
$orderBy = ["id" => "ASC", "company_id" => "DESC"];
$offset = 0;
$limit = 100;
/** @noinspection PhpUndefinedVariableInspection */
assert(SQL::Select($bindArray, $from, $select, $where, $orderBy, $offset, $limit)
    ===
    "SELECT  id, company_id  FROM t_company WHERE   company_id  IN (:company_id_0, :company_id_1, :company_id_2, :company_id_3, :company_id_4, :company_id_5, :company_id_6)  AND (  `name` NOT LIKE :name  OR type <> :type )  ORDER BY  id ASC , company_id DESC  LIMIT 0, 100");
assert($bindArray === array (
        ':company_id_0' => 167,
        ':company_id_1' => 180,
        ':company_id_2' => 200,
        ':company_id_3' => 201,
        ':company_id_4' => 203,
        ':company_id_5' => 214,
        ':company_id_6' => 219,
        ':name' => '沙县%',
        ':type' => 0,
    ));
unset($bindArray);

/** @noinspection PhpUndefinedVariableInspection */
assert(SQL::Select($bindArray, $from, $select, $where, $orderBy, $offset, $limit, "?")
    ===
    "SELECT  id, company_id  FROM t_company WHERE   company_id  IN (?, ?, ?, ?, ?, ?, ?)  AND (  `name` NOT LIKE ?  OR type <> ? )  ORDER BY  id ASC , company_id DESC  LIMIT 0, 100");
assert($bindArray === array (
        0 => 167,
        1 => 180,
        2 => 200,
        3 => 201,
        4 => 203,
        5 => 214,
        6 => 219,
        7 => '沙县%',
        8 => 0,
    ));
unset($bindArray);

assert(SQL::insert($bindArray, $from, ["id"=>1, "company_id"=>2]) === "INSERT INTO t_company  (id, company_id) VALUES (:id, :company_id) ");
assert($bindArray ===  array (
        ':id' => 1,
        ':company_id' => 2,
    ));
unset($bindArray);

assert(SQL::insert($bindArray, $from, ["id"=>1, "company_id"=>2], "?") === "INSERT INTO t_company  (id, company_id) VALUES (?, ?) ");
assert($bindArray ===  array (1, 2,));
unset($bindArray);

assert(SQL::batchInsert($bindArray, $from, [["id"=>1, "company_id"=>2], ["id"=>2, "company_id"=>4]]) === "INSERT INTO t_company  (id, company_id) VALUES (:id_0_0, :company_id_0_1),(:id_1_0, :company_id_1_1) ");
assert($bindArray === array (
        ':id_0_0' => 1,
        ':company_id_0_1' => 2,
        ':id_1_0' => 2,
        ':company_id_1_1' => 4,
    ));
unset($bindArray);

assert(SQL::batchInsert($bindArray, $from, [["id"=>1, "company_id"=>2], ["id"=>2, "company_id"=>4]], "?") === "INSERT INTO t_company  (id, company_id) VALUES (?, ?), (?, ?) ");
assert($bindArray === array (
        0 => 1,
        1 => 2,
        2 => 2,
        3 => 4,
    ));
unset($bindArray);


assert(SQL::update($bindArray, $from, ["`name`" => "newName", "type" => 2], $where)
===
"UPDATE t_company SET  `name` = :name, type = :type  WHERE   company_id  IN (:company_id_0, :company_id_1, :company_id_2, :company_id_3, :company_id_4, :company_id_5, :company_id_6)  AND (  `name` NOT LIKE :name_  OR type <> :type_ ) ");
assert($bindArray === array (
        ':name' => 'newName',
        ':type' => 2,
        ':company_id_0' => 167,
        ':company_id_1' => 180,
        ':company_id_2' => 200,
        ':company_id_3' => 201,
        ':company_id_4' => 203,
        ':company_id_5' => 214,
        ':company_id_6' => 219,
        ':name_' => '沙县%',
        ':type_' => 0,
    ));
unset($bindArray);