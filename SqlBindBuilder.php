<?php

/**
 * 支持数据绑定的sqlbuilder实现
 * 可配合PDO使用
 * 说明：
 * 1. :与?两种通配符不能同时使用，
 * 	因为：生成的bindArray是hashtable，而？生成的bindArray是数组(非php数组)
 * 2. build生成内容不含有sql关键词（select，insert，order by等）
 * 3. 如需自行拼接sql，混用build方法，必须使用getBindkey方法返回的字符串做拼接的value
 * 	传入build方法产生的bindArray，或者将getBindKey产生的bindArray传入build方法
 * @author xiaofeng
 */
Class SqlBindBuilder
{
    const SELECT 		= 1;
    const INSERT 		= 2;
    const MULTI_INSERT 	= 3;
    const UPDATE 		= 4;
    const DELETE 		= 5;
    const WHERE  		= 6;
	const ORDER  		= 7;

	/**
	 * 将val压到bindArray中，返回用于拼接sql的key
	 * @param &$bindArray
	 * @param $bindKey
	 * @param $bindValue
	 * @param bool|true $clear 用于递归的参数，使用者无需关心
	 * @return mixed|string
	 * @author xiaofeng
	 */
	public static function getBindkey(&$bindArray, /*string*/ $bindKey, $bindValue, $clear = true)
	{
		if($bindArray === null) {
			$bindArray = [];
		}
		if($clear) {
			// bindKey不支持“.”，用以处理table.field强狂
			$bindKey = str_replace('.', '_', trim($bindKey));
		}
		if($bindKey[0] !== ':') {
			$bindKey = ':' . $bindKey;
		}
		// 递归添加防止重复
		if(isset($bindArray[$bindKey])) {
			$bindKey .= '_r' . mt_rand(0, 10);
			return self::getBindkey($bindArray, $bindKey, $bindValue, false);
		} else {
			$bindArray[$bindKey] = $bindValue;
		}
		return $bindKey;
	}

	/**
	 * @param $bindArray
	 * @param $array
	 * @author xiaofeng
	 */
	public static function getBindkeys(&$bindArray, $array)
	{
		$bindKeys = [];
		foreach($array as $bk => $bv) {
			$bindKeys[] = self::getBindkey($bindArray, $bk, $bv);
		}
		return $bindKeys;
	}

	/**
	 * 多种类型builder的统一接口
	 * @param integer 	$buildType
	 * @param array 	$array 传入构建的数组
	 * @param &array 	$bindArray OUT_PARAM 用于参与绑定的返回值
	 * @param string 	$placeholder ':'占位符表示返回:fieldName方式查询语句，否则直接返回占位符
	 * @param string 	$relation build where时用到的首层关系条件
	 * @return string
	 * @author xiaofeng
	 */
	public static function build(/*int*/ $buildType, array $array, &$bindArray = [],
		/*string*/ $placeholder = ':', /*string*/ $relation = 'AND')
	{
		// build类型检查
        $types = (new ReflectionClass(__CLASS__))->getConstants();
		if(!in_array($buildType, $types, true)) {
			throw new InvalidArgumentException("buildType $type is not defined.");
		}
		if(!in_array($placeholder, ['?', ':'])) {
			throw new InvalidArgumentException("placeholder only support ? or :");
		}


		// bindArray检查
		// select 与 where 类型允许空的array，select * | where 1 = 1
		if(empty($array) && !in_array($buildType, [self::SELECT, self::WHERE])) {
			throw new SqlBuilderBindException("buildType $buildType bindArray can not be empty");
		}

		switch ($buildType) {
			case self::SELECT:
				$ret = empty($array) ? '*' : implode(', ', array_values($array));
				return " $ret ";

			case self::INSERT:
				$fields = array_keys($array);
				$fieldsStr = implode(', ', $fields);
				$_ = array_values($array);
				if($placeholder === ':') {
					$valuesPlaceholdersStr = implode(', ', self::getBindkeys($bindArray, $array));
				} else {
					$valuesPlaceholdersStr = implode(', ', array_fill(0, count($fields), $placeholder));
					if(is_array($bindArray)) {
						// FIXME 不能发生覆盖行为，array_merge只用于?通配符
						$bindArray = array_merge($bindArray, array_values($array));
					} else {
						$bindArray = array_values($array);
					}
				}
				return " ($fieldsStr) VALUES ($valuesPlaceholdersStr) ";

			case self::MULTI_INSERT:
				$fields = array_keys($array[0]);
				$fieldsStr = implode(', ', $fields);

				if($placeholder === ':') {
					$insertArr = [];
					foreach($array as $k => $pairs) {
						$i = 0;
						$tmpArr = [];
						foreach($pairs as $field => $value) {
							$tmpArr[] = self::getBindkey($bindArray, "{$field}_{$k}_{$i}", $value);
							$i++;
						}
						$insertArr[] = '(' . implode(',', $tmpArr)  . ')';
					}
					$valuesPlaceholdersStr = implode(',', $insertArr);
				} else {
					// $values = array_reduce($array, function($carry, $row){ array_push($carry, array_values($row)); }, []);
					foreach($array as $value) {
						foreach(array_values($value) as $item) {
							$bindArray[] = $item;
						}
					}
					$valuesPlaceholdersStr = '(' . implode('), (', array_fill(0, count($array), implode(',', array_fill(0, count($fields), '?')))) . ')';
				}
				return " ($fieldsStr) VALUES $valuesPlaceholdersStr ";

			case self::UPDATE:
				if($placeholder === ':') {
					$tmpArr = [];
					foreach(array_combine(array_keys($array), self::getBindkeys($bindArray, $array))
							as $bfield => $bvalue) {
						$tmpArr[] = "$bfield = $bvalue";
					}
					return ' ' . implode(', ', $tmpArr) . ' ';
				}
				$bindArray = array_values($array);
				return ' ' . implode(', ', array_map(function($f) use($placeholder) { return "$f = $placeholder";}, array_keys($array))) . ' ';

			case self::WHERE:
				return self::buildWhere($array, $bindArray, $relation, $placeholder);

			case self::ORDER:
				$orderByArr = [];
				foreach($array as list($order, $by)) {
					if(!in_array(strtolower($by), ['asc', 'desc'], true)) {
						$by = 'ASC';
					}
					// FIXBUG Column/table names are part of the schema and cannot be bound.
					/*
					$orderByArr[] = _::getBindkey($bindArray, "sort_by_$order", $order) . ' ' .
						_::getBindkey($bindArray, "sort_order_$order", $by);
					*/

					// FIXME 应该检测$order字段是否为column字段,防止sql注入
					/*
					if(!in_array($order, $allowColumns)) {
						throw new Exception
					}
					*/
					$orderByArr[] = "$order $by";
				}
				return " " . implode(' , ', $orderByArr);

			default:
				return '';
		}
	}

	/**
	 * 构建like表达式
	 * @param string	$field
	 * @param string	$value
	 * @param &array	$bindArray
	 * @param boolean	$not
	 * @param string    $placeholder
	 * @return string
	 * @author xiaofeng
	 */
	public static function buildLike(/*string*/ $field, /*string*/ $value, &$bindArray, /*bool*/ $not = false,  /*string*/ $placeholder = '?') /*: string*/
	{
		if(!is_string($field) || !$field || !is_string($value)) {
			throw new InvalidArgumentException("build args error");
		}

		// FIXME : 处理$value
		$not = $not ? 'NOT' : '';
		if($placeholder === ':') {
			return " $field $not LIKE " . self::getBindkey($bindArray, $field, '%' . $value . '%') . ' ';
		} else {
			$bindArray[] = '%' . $value . '%';
			return " $field $not LIKE ? ";
		}
	}

	/**
	 * 构建in查询条件
	 * @param  string       $field
	 * @param  array        $array OUT_PARAM
	 * @param  &array        $bindArray
	 * @param  bool         $not
	 * @param  string       $placeholder
	 * @return string
	 * @throws InvalidArgumentException
	 * @author xiaofeng
	 * 2015-09-06 02:24 添加占位符选项
	 */
	public static function buildIn(/*string*/ $field, /*array*/ $array, &$bindArray, /*bool*/ $not = false, /*string*/ $placeholder = ':') /*: string*/
	{
		if(!is_string($field) || !$field || empty($array)) {
			throw new InvalidArgumentException("build args error");
		}
		if(!is_array($array)) {
			$array = [$array];
		}
		if($placeholder === ':') {
			if (count($array) === 1) {
				return $field . ($not ? ' <> ' : ' = ') . self::getBindkey($bindArray, $field, $array[0]);
			}
			$inArr = [];
			foreach($array as $k => $v) {
				$inArr[] = self::getBindkey($bindArray, "{$field}_{$k}", $v);
			}
			$valuesStr = implode(', ', $inArr);
		} elseif($placeholder) {
			if(is_array($bindArray)) {
				$bindArray = array_merge($bindArray, $array);
			} else {
				$bindArray = $array;
			}
			if (count($array) === 1) {
				return $field . ($not ? ' <> ' : ' = ') . "?";
			}
			$valuesStr = implode(', ', array_fill(0, count($array), '?'));
		}

		$not = $not ? 'NOT' : '';
		return "$field $not IN ($valuesStr)";
	}

	/**
	 * 构建where查询条件
	 * @param array 	$cond 条件数组(like 与 not like 参数不含有%)
	 * @param &array 	$bindArray 返回bind数组
	 * @param string 	$relation 关系 ['AND', 'OR']
	 * @param string 	$placeholder
	 * @return string
	 * @throws SqlBuilderBindException
	 * @author xiaofeng
	 */
	public static function buildWhere(array $cond = [], &$bindArray, $relation = 'AND', $placeholder = ':') /* :string */
	{
		if(empty($cond)) {
			return $relation === 'AND' ? ' 1 = 1 ' : ' 1 = 0 ';
		}

		$condArr = [];
		$relation = strtoupper($relation);
		foreach($cond as $key => $subCond) {

			// 递归构建
			if(in_array(strtoupper($key), ['AND', 'OR'], true)) {
				$condArr[] = '(' . self::buildWhere($subCond, $bindArray, $key, $placeholder) . ')';
				continue;
			}

			if(($_c = count($subCond)) !== 3) {
				throw new SqlBuilderBindException("wrong where condition array: 3 items of subcond is excepted, {$_c} was given", _Code::SQL_BUILD_ERROR);
			}

			list($field, $subRel, $value) = $subCond;
			switch(strtoupper($subRel)) {
				case 'LIKE':
					$condArr[] = self::buildLike($field, $value, $bindArray, false, $placeholder);
					break;
				case 'NOT LIKE':
					$condArr[] = self::buildLike($field, $value, $bindArray, true, $placeholder);
					break;
				case 'IN':
					$condArr[] = self::buildIn($field, $value, $bindArray, false, $placeholder);
					break;
				case 'NOT IN':
					$condArr[] = self::buildIn($field, $value, $bindArray, true, $placeholder);
					break;
				default:
					if($placeholder === '?') {
						$condArr[] = "$field $subRel ?";
						$bindArray[] = $value;
					} else if($placeholder === ':') {
						$condArr[] = "{$field} {$subRel} " . self::getBindkey($bindArray, $field, $value);
					}
			}
		}
		return ' ' . implode(" $relation ", $condArr) . ' ';
	}

	/**
	 * 检测变量在pdobind中的类型
	 * 最终的bind实现类中，请依照此类的对应关系来处理绑定变量类型
	 * @param string $var
	 * @return int PDOtype
	 */
	public static function getPdoType($var)
	{
		static $map = [
			'boolean'   =>PDO::PARAM_BOOL,
			'integer'   =>PDO::PARAM_INT,
			'string'    =>PDO::PARAM_STR,
			'resource'  =>PDO::PARAM_LOB,
			'NULL'      =>PDO::PARAM_NULL,
		];
		$type = gettype($var);
		return isset($map[$type]) ? $map[$type] : PDO::PARAM_STR;
	}
}

class SqlBuilderBindException extends Exception {}
