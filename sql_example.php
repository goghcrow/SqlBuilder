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

/**
 * bindValues
 * @param \PDOStatement $stmt
 * @param array $keyValues
 * @return \PDOStatement
 */
function bindValues(\PDOStatement $stmt, array $keyValues) {
    foreach ($keyValues as $key => $value) {
        $stmt->bindValue($key, $value, pdoType($value));
    }
    return $stmt;
}

$dsn = "mysql:host=192.168.2.18;dbname=distribute;charset=utf8;";

$pdo = new \PDO($dsn, "root", "meicai@!#");
//$pdo = new \PDO($dsn, "distribute_dev_rw", "123456");


/** @noinspection PhpUndefinedVariableInspection */
$sql = 'SELECT company_id,`name` FROM `t_company` WHERE `name` LIKE ' . _($bindArray, "name", "沙县%");
$stmt = $pdo->prepare($sql);
// $stmt = bindValues($stmt, $bindArray);
$stmt->execute($bindArray);
$ret = $stmt->fetchAll(\PDO::FETCH_ASSOC);
print_r($ret);


// TODO ...