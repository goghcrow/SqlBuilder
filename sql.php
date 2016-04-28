<?php
/**
 * User: xiaofeng
 * Date: 2016/4/26
 * Time: 23:31
 */
namespace xiaofeng\sql;

/**
 * @param $var
 * @return int
 * is_* faster than gettype
 */
function pdoType($var) {
    if($var === null) return \PDO::PARAM_NULL;
    if(is_bool($var)) return \PDO::PARAM_BOOL;
    if(is_int($var)) return \PDO::PARAM_INT;
    if(is_resource($var)) return \PDO::PARAM_LOB;
    return \PDO::PARAM_STR; // others type => pdostr
}

function _getBindKey(&$bindArray, $bindKey, $bindValue) {
    _assertString($bindKey, _get_variable_name($bindKey, get_defined_vars()));
    // bindingKey not support table.filed
    // so replace to table_filed
    $bindKey = str_replace(['.', '`'], ['_', ''], trim($bindKey));
    // bindKey needs prefix :
    if($bindKey[0] !== ":") {
        $bindKey = ":$bindKey";
    }
    // init input parameter
    if($bindArray == null) {
        $bindArray = [];
    }
    // prevent repeating key
    if(isset($bindArray[$bindKey])) {
        $bindKey = _getBindKey($bindArray, "{$bindKey}_", $bindValue);
    } else {
        $bindArray[$bindKey] = $bindValue;
    }
    return $bindKey;
}

function _getBindKeys(&$bindArray, array $keyValues) {
    _assertNotEmpty($keyValues, _get_variable_name($keyValues, get_defined_vars()));
    $bindKeys = [];
    foreach($keyValues as $key => $value) {
        $bindKeys[] = _getBindkey($bindArray, $key, $value);
    }
    return $bindKeys;
}

/**
 * getBindKey[s]
 * @param array $bindArray
 * @param string|array $bindKey
 * @param mixed $bindValue
 * @return string|array
 * @throws \InvalidArgumentException
 */
function _(&$bindArray, $bindKey, $bindValue = null) {
    if(is_array($bindKey)) {
        return _getBindKeys($bindArray, $bindKey);
    } else {
        return _getBindKey($bindArray, $bindKey, $bindValue);
    }
}

/**
 * select
 * @param array $fields
 * @return string
 */
function select(array $fields) {
    if(empty($fields)) {
        return " * ";
    }
    return " " . implode(", ", array_values($fields)) . " ";
}

function _insertColon(&$bindArray, array $keyValues) {
    $fieldsStr = implode(", ", array_keys($keyValues));
    $values = implode(", ", _($bindArray, $keyValues));
    return " ($fieldsStr) VALUES ($values) ";
}

function _insertQ(&$bindArray, array $keyValues) {
    $fieldsStr = implode(", ", array_keys($keyValues));
    $values = implode(", ", array_fill(0, count($keyValues), '?'));
    $bindArray = array_merge($bindArray ?: [], array_values($keyValues));
    return " ($fieldsStr) VALUES ($values) ";
}

/**
 * insert
 * @param array $bindArray
 * @param array $row
 * @param string $placeHolder
 * @return string
 * @throws \InvalidArgumentException
 */
function insert(&$bindArray, array $row, $placeHolder = ":") {
    _assertNotEmpty($row, _get_variable_name($row, get_defined_vars()));
    if($placeHolder === ":") return _insertColon($bindArray, $row);
    if($placeHolder === "?") return _insertQ($bindArray, $row);
    throw new \InvalidArgumentException("placeHolder should be : or ?");
}

function _batchInsertColon(&$bindArray, array $rows) {
    $fieldsStr = implode(", ", array_keys($rows[0]));
    $rowsArr = [];
    foreach($rows as $i => $pairs) {
        $j = 0;
        $rowArr = [];
        foreach($pairs as $field => $value) {
            $rowArr[] = _getBindKey($bindArray, "{$field}_{$i}_{$j}", $value);
            $j++;
        }
        $rowsArr[] = '(' . implode(", ", $rowArr)  . ')';
    }
    $values = implode(',', $rowsArr);
    return " ($fieldsStr) VALUES $values ";
}

function _batchInsertQ(&$bindArray, array $rows) {
    $fieldsStr = implode(", ", array_keys($rows[0]));
    foreach($rows as $row) {
        foreach(array_values($row) as $value) {
            $bindArray[] = $value;
        }
    }
    $single = implode(", ", array_fill(0, count($rows[0]), '?'));
    $values = implode(", ", array_fill(0, count($rows), "($single)"));
    return " ($fieldsStr) VALUES $values ";
}

/**
 * batchInsert
 * @param array $bindArray
 * @param array $rows
 * @param string $placeHolder
 * @return string
 * @throws \InvalidArgumentException
 */
function batchInsert(&$bindArray, array $rows, $placeHolder = ":") {
    _assertNotEmpty($rows, _get_variable_name($rows, get_defined_vars()));
    if($placeHolder === ":") return _batchInsertColon($bindArray, $rows);
    if($placeHolder === "?") return _batchInsertQ($bindArray, $rows);
    throw new \InvalidArgumentException("placeHolder should be : or ?");
}

function _updateColon(&$bindArray, array $keyValues) {
    $sets = array_map(function($key, $bindKey) {
        return "$key = $bindKey";
    }, array_keys($keyValues), _($bindArray, $keyValues));
    return " " . implode(", ", $sets) . " ";
}

function _updateQ(&$bindArray, array $keyValues) {
    $bindArray = array_merge($bindArray ?: [], array_values($keyValues));
    $sets = array_map(function($f) { return "$f = ?";}, array_keys($keyValues));
    return " " . implode(", ", $sets) . " ";
}

/**
 * @param array $bindArray
 * @param array $keyValues
 * @param string $placeHolder
 * @return string
 * @throws \InvalidArgumentException
 */
function update(&$bindArray, array $keyValues, $placeHolder = ":") {
    _assertNotEmpty($keyValues, _get_variable_name($keyValues, get_defined_vars()));
    if($placeHolder === ":") return _updateColon($bindArray, $keyValues);
    if($placeHolder === "?") return _updateQ($bindArray, $keyValues);
    throw new \InvalidArgumentException("placeHolder should be : or ?");
}

function _likeColon(&$bindArray, $field, $value, $not = false) {
    $not = $not ? "NOT" : "";
    return " $field $not LIKE " . _getBindkey($bindArray, $field, $value) . " ";
}

function _likeQ(&$bindArray, $field, $value, $not = false) {
    $not = $not ? "NOT" : "";
    $bindArray[] = $value;
    return " $field $not LIKE ? ";
}

/**
 * like
 * @param array $bindArray
 * @param string $field
 * @param string $value
 * @param string $placeHolder
 * @return string
 */
function like(&$bindArray, $field, $value, $placeHolder = ':') {
    _assertString($field, _get_variable_name($field, get_defined_vars()));
    _assertString($value, _get_variable_name($value, get_defined_vars()));

    if($placeHolder === ":") return _likeColon($bindArray, $field, $value, false);
    if($placeHolder === "?") return _likeQ($bindArray, $field, $value, false);
    throw new \InvalidArgumentException("placeHolder should be : or ?");
}

/**
 * not like
 * @param array $bindArray
 * @param string $field
 * @param string $value
 * @param string $placeHolder
 * @return string
 */
function notLike(&$bindArray, $field, $value, $placeHolder = ':') {
    $_ = get_defined_vars();
    _assertString($field, _get_variable_name($field, $_));
    _assertString($value, _get_variable_name($value, $_));

    if($placeHolder === ":") return _likeColon($bindArray, $field, $value, true);
    if($placeHolder === "?") return _likeQ($bindArray, $field, $value, true);
    throw new \InvalidArgumentException("placeHolder should be : or ?");
}

function _inColon(&$bindArray, $field, array $array, $not = false) {
    if (count($array) === 1) {
        return $field . ($not ? " <> " : " = ") . _getBindkey($bindArray, $field, $array[0]);
    }
    $inArr = [];
    foreach($array as $k => $v) {
        $inArr[] = _getBindkey($bindArray, "{$field}_{$k}", $v);
    }
    $valuesStr = implode(', ', $inArr);
    $not = $not ? "NOT" : '';
    return " $field $not IN ($valuesStr) ";
}

function _inQ(&$bindArray, $field, array $array, $not = false) {
    $bindArray = array_merge($bindArray ?: [], $array);
    if (count($array) === 1) {
        return $field . ($not ? " <> " : " = ") . "?";
    }
    $valuesStr = implode(", ", array_fill(0, count($array), '?'));
    $not = $not ? "NOT" : '';
    return " $field $not IN ($valuesStr) ";
}

/**
 * in
 * @param array $bindArray
 * @param string $field
 * @param array $array
 * @param string $placeHolder
 * @return string
 */
function in(&$bindArray, $field, array $array, $placeHolder = ':') {
    $_ = get_defined_vars();
    _assertString($field, _get_variable_name($field, $_));
    _assertNotEmpty($field, _get_variable_name($field, $_));
    _assertNotEmpty($array, _get_variable_name($array, $_));

    if($placeHolder === ":") return _inColon($bindArray, $field, $array, false);
    if($placeHolder === "?") return _inQ($bindArray, $field, $array, false);
    throw new \InvalidArgumentException("placeHolder should be : or ?");
}

/**
 * not in
 * @param array $bindArray
 * @param string $field
 * @param array $array
 * @param string $placeHolder
 * @return string
 */
function notIn(&$bindArray, $field, array $array, $placeHolder = ':') {
    $_ = get_defined_vars();
    _assertString($field, _get_variable_name($field, $_));
    _assertNotEmpty($field, _get_variable_name($field, $_));
    _assertNotEmpty($array, _get_variable_name($array, $_));

    if($placeHolder === ":") return _inColon($bindArray, $field, $array, true);
    if($placeHolder === "?") return _inQ($bindArray, $field, $array, true);
    throw new \InvalidArgumentException("placeHolder should be : or ?");
}

/**
 * where
 * @param $bindArray
 * @param array $cond
 * @param string $relation
 * @param string $placeHolder
 * @return string
 */
function where(&$bindArray, array $cond = [], $relation = "AND", $placeHolder = ':')
{
    $relation = strtoupper(trim($relation));
    if(empty($cond)) {
        return $relation === "AND" ? " 1 = 1 " : " 1 = 0 ";
    }

    $condArr = [];
    foreach($cond as $key => $subCond) {
        $key = strtoupper(trim($key));
        if($key === "AND" || $key === "OR") {
            $condArr[] = '(' . where($bindArray, $subCond, $key, $placeHolder) . ')';
            continue;
        }
        if(count($subCond) !== 3) {
            throw new \InvalidArgumentException("subCond error(count != 3): " . print_r($subCond, true));
        }
        list($field, $subRel, $value) = $subCond;
        switch(strtoupper($subRel)) {
            case "LIKE":
                $condArr[] = like($bindArray, $field, $value, $placeHolder);
                break;
            case "NOT LIKE":
                $condArr[] = notLike($bindArray, $field, $value, $placeHolder);
                break;
            case "IN":
                $condArr[] = in($bindArray, $field, $value, $placeHolder);
                break;
            case "NOT IN":
                $condArr[] = notIn($bindArray, $field, $value, $placeHolder);
                break;
            default:
                if($placeHolder === '?') {
                    $condArr[] = "$field $subRel ?";
                    $bindArray[] = $value;
                } else if($placeHolder === ':') {
                    $condArr[] = "$field $subRel " . _getBindkey($bindArray, $field, $value);
                } else {
                    throw new \InvalidArgumentException("placeHolder should be : or ?");
                }
        }
    }
    return " " . implode(" $relation ", $condArr) . " ";
}

/**
 * order by
 * @param array $orderByPairs
 * @param array|null $permittedBys
 * @return string
 */
function orderBy(array $orderByPairs, array $permittedBys = null) {
    $orderByArr = [];
    foreach($orderByPairs as  $by => $order) {
        $order = strtoupper(trim($order));
        if($order !== "ASC" && $order !== "DESC") {
            throw new \InvalidArgumentException("BY only support ASC and DESC, but {$order} given");
        }
        if($permittedBys !== null) { // for security
            $permittedBys = array_map("strtolower", $permittedBys);
            if(!in_array(strtolower($by), $permittedBys, true)) {
                throw new \InvalidArgumentException("$order is not allowed order by");
            }
        }
        $orderByArr[] = "$by $order";
    }
    return " " . implode(" , ", $orderByArr) . " ";
}

/**
 * Class SQL
 * @package xiaofeng\sql
 * TODO:
 * SELECT [DISTINCT] ... [FOR UPDATE]
 * REPLACE INTO
 * INSERT INTO ... [ON DUPLICATE KEY UPDATE ...]
 */
class SQL
{
    /**
     * @param $bindArray
     * @param $from
     * @param array $select
     * @param array $where
     * @param array $order
     * @param int $offset
     * @param int $limit
     * @param string $placeHolder
     * @return string
     */
    public static function select(&$bindArray, $from, array $select = [],
                                  array $where = [], array $order = [],
                                  $offset = 0, $limit = PHP_INT_MAX, $placeHolder = ':') {
        $select = select($select);
        $sql = "SELECT $select FROM $from ";
        if($where) {
            $where = where($bindArray, $where, "AND", $placeHolder);
            $sql .= "WHERE $where ";
        }
        if($order) {
            $order = orderBy($order);
            $sql .= "ORDER BY $order ";
        }
        if($offset === 0 && $limit === PHP_INT_MAX) {
            return $sql;
        }
        return $sql .= "LIMIT $offset, $limit";
    }

    /**
     * @param $bindArray
     * @param $table
     * @param array $row
     * @param string $placeHolder
     * @return string
     */
    public static function insert(&$bindArray, $table, array $row, $placeHolder = ':') {
        return "INSERT INTO $table " . insert($bindArray, $row, $placeHolder);
    }

    /**
     * @param $bindArray
     * @param $table
     * @param array $row
     * @param string $placeHolder
     * @return string
     */
    public static function batchInsert(&$bindArray, $table, array $row, $placeHolder = ':') {
        return "INSERT INTO $table " . batchInsert($bindArray, $row, $placeHolder);
    }

    /**
     * @param $bindArray
     * @param $table
     * @param array $row
     * @param array $where
     * @param string $placeHolder
     * @return string
     */
    public static function update(&$bindArray, $table, array $row, array $where = [], $placeHolder = ":") {
        $sets = update($bindArray, $row, $placeHolder);
        $sql = "UPDATE $table SET $sets ";
        if($where) {
            $sql .= "WHERE " . where($bindArray, $where, "AND", $placeHolder);
        }
        return $sql;
    }

    /**
     * @param $bindArray
     * @param $table
     * @param array $where
     * @param string $placeHolder
     * @return string
     */
    public static function delete(&$bindArray, $table, array $where/*=[]*/, $placeHolder = ":") {
        $sql = "DELETE FROM $table ";
        if($where) {
            $sql .= "WHERE " . where($bindArray, $where, "AND", $placeHolder);
        }
        return $sql;
    }

    /**
     * @param $bindArray
     * @param $table
     */
    public static function upsert(&$bindArray, $table) {

    }
}


// restore sql
function _bind($sql, $bindArray) {

}

/**
 * @access private
 * @param $var
 * @param string $what
 * @throws \InvalidArgumentException
 */
function _assertString($var, $what) {
    if(!is_string($var)) {
        throw new \InvalidArgumentException("$what should be string");
    }
}

/**
 * @access private
 * @param $var
 * @param $what
 * @throws \InvalidArgumentException
 */
function _assertNotEmpty($var, $what) {
    if(empty($var)) {
        throw new \InvalidArgumentException("$what should not be empty");
    }
}

/**
 * get_variable_name
 * @access private
 * @param $var
 * @param array|NULL $scope
 * @return mixed
 * http://www.laruence.com/2010/12/08/1716.html
 */
function _get_variable_name(&$var, array $scope = NULL) {
    $scope = $scope ?: $GLOBALS;
    $tmp = $var;
    $var = uniqid(time()); // 给变量唯一值
    $name = array_search($var, $scope, true);
    $var = $tmp;
    return $name;
}