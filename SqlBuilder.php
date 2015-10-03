<?php
/**
 * 不支持数据绑定的sqlbuilder实现
 * 不推荐使用，escape方法不能完全保证安全性，且未完全实现
 * 推荐使用SqlBindBuilder
 * @deprecated
 * @author xiaofeng
 */
Class SqlBuilder
{
    const SELECT 		= 1;
    const INSERT 		= 2;
    const MULTI_INSERT 	= 3;
    const UPDATE 		= 4;
    const DELETE 		= 5;
    const WHERE  		= 6;
	const ORDER  		= 7;

    /**
     * 转义
     * @param string $str
     * @return string
     * @author xiaofeng
     */
    public static function dbQuote(/*string*/ $str) /*: string*/
    {
        /*
        $quote_search = ['\\', "\0", "\n", "\r", "'", '"', "\x1a"];
        $quote_replace = ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'];
        return str_replace($quote_search, $quote_replace, $str);
        */
        return addcslashes(str_replace("'", "''", $str), "\000\n\r\\\032");
    }

    /**
     * 根据类型转义
     * @param $var
     * @return mixed
     * @author xiaofeng
     */
    public static function quote($var) /*: string*/
    {
        if(is_string($var)) {
            return "'" . self::dbQuote($var) . "'";
        } else if (is_bool($var)) {
            // 数据库bool字段使用01表示
            return $var ? 1 : 0;
        } else if (is_null($var)) {
            return 'NULL'; // 数据库字段尽量设置成 not null
        } else {
            return $var;
        }
    }

	/**
	 * 多种类型builder的统一接口
	 * @param integer 	$buildType
	 * @param array 	$array 传入构建的数组
	 * @return string
	 * @author xiaofeng
	 */
    public static function build(/*int*/ $buildType, array $array) /*: string*/
    {
		// build类型检查
        $types = (new ReflectionClass(__CLASS__))->getConstants();
		if(!in_array($buildType, $types, true)) {
			throw new InvalidArgumentException("buildType $type is not defined.");
		}

        switch ($buildType) {
            case self::SELECT:
                return empty($array) ? '*' : implode(', ', $array);

            case self::INSERT:
                $fields = implode(', ', array_keys($array));
                $values = implode(', ', array_map([__CLASS__, 'quote'], array_values($array)));
                return " ($fields) VALUES ($values) ";

            case self::MULTI_INSERT:
                trigger_error('not impl');

            case self::UPDATE:
                $expArr = [];
                foreach ($array as $field => $value) {
                    $expArr[] = "$field = " . self::quote($value);
                }
                return implode(', ', $expArr);

            case self::WHERE:
                trigger_error('not impl');

            case self::ORDER:
                trigger_error('not impl');
            default:
                return '';
        }
    }

    /**
     * 构建like表达式
     * @param  string       $field
     * @param  string       $expression
     * @param  bool         $not
     * @return string
     * @author xiaofeng
     */
    public static function buildLike(/*string*/ $field, /*string*/ $expression, /*bool*/ $not = false) /*: string*/
    {
		if(!is_string($field) || !$field || !is_string($expression)) {
			throw new InvalidArgumentException("build args error");
		}

        $expression = str_replace(['_', '%'], ["\_", "\%"], $expression);
        $expression = str_replace([chr(0) . "\_", chr(0) . "\%"], ['_', '%'], $expression);
        $expression = self::dbQuote($expression);
        $not = $not ? 'NOT' : '';
        return " $field $not LIKE '$expression' ";
    }

    /**
     * 构建in表达式
     * @param  string       $field
     * @param  array        $array
     * @param  bool         $not
     * @param  string       $placeholder
     * @return string
     * @author xiaofeng
     */
    public static function buildIn(/*string*/ $field, /*array*/ $array, /*bool*/ $not = false,  /*string*/ $placeholder = '?') /*: string*/
    {
		if(!is_string($field) || !$field || empty($array)) {
			throw new InvalidArgumentException("build args error");
		}
        if(!is_array($array)) {
            $array = [$array];
        }
        if (count($array) === 1) {
            return $field . ($not ? ' <> ' : ' = ') . self::quote($array);
        }
        $not = $not ? 'NOT' : '';
        $values = implode(', ', array_map([__CLASS__, 'quote'], $array));
        return " $field $not IN ($values) ";
    }

    public static function buildWhere(array $cond = [], $relation = 'AND')
    {
        // FIXME:
        trigger_error('not impl');
        // 可参数绑定类中where实现
    }

}
