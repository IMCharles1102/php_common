<?php

class Mysql
{
    public static $name;

    private static $multi = 0;  // 开启主从
    private static $master;
    private static $slave;

    const SUCC_CODE = '00000';  // mysql操作正确码

    public function __construct()
    {
        if (IS_PRODUCT) {
            $config = \Yaf\Registry::get('config')->get('proc')->get('medoo')->toArray();
        } else {
            $config = \Yaf\Registry::get('config')->get('debug')->get('medoo')->toArray();
        }

        self::$multi = $config['multi'];

        if (!self::$master) {
            try {
                self::$master = new Medoo\Medoo($config['master']);
            } catch (\PDOException $ex) {
                exit('Connect master db fail. ' . $ex->getMessage());
            }
        }

        if (self::$multi && !self::$slave) {
            try {
                self::$slave = new Medoo\Medoo($config['slave']);
            } catch (\PDOException $ex) {
                exit('Connect slave db fail. ' . $ex->getMessage());
            }
        }
    }

    /**
     * 判断某记录是否存在
     * @return true/false
     */
    public function exist(array $where, array $join = [])
    {
        $dbObj = self::$multi ? self::$slave : self::$master;   // 主从读从库

        if (empty($join)) {
            $result = $dbObj->has(static::$name, $where);
        } else {
            $result = $dbObj->has(static::$name, $join, $where);
        }

        return $result;
    }

    /**
     * 获取条目数
     */
    public function count(array $where, array $join = [], $column = '*')
    {
        $dbObj = self::$multi ? self::$slave : self::$master;   // 主从读从库
        if (empty($join)) {
            $result = $dbObj->count(static::$name, $where);
        } else {
            $result = $dbObj->count(static::$name, $join, $column, $where);
        }

        return $result;
    }

    /**
     * 获取最大数
     */
    protected function max($column, array $where, array $join = [])
    {
        $dbObj = self::$multi ? self::$slave : self::$master;   // 主从读从库
        if (empty($join)) {
            $result = $dbObj->max(static::$name, $column, $where);
        } else {
            $result = $dbObj->max(static::$name, $join, $column, $where);
        }

        return $result;
    }

    /**
     * 单条数据检索
     * @param $columns:检索数据的列
     * @param $where:检索条件
     * @param $join:联表
     * @return false/空/单个值(单列)/单数组(多列)
     */
    protected function queryOnly($columns, array $where = [], array $join = [])
    {
        $dbObj = self::$multi ? self::$slave : self::$master;   // 主从读从库

        if (empty($join)) {
            $result = $dbObj->get(static::$name, $columns, (empty($where) ? null : $where));
        } else {
            $result = $dbObj->get(static::$name, $join, $columns, (empty($where) ? null : $where));
        }

        if ($result === false) {
            file_put_contents(DB_LOG_FILE, $dbObj->last() . "\n", FILE_APPEND);
        }

        return $result;
    }

    /**
     * 多条数据检索
     * @param $columns:检索数据的列
     * @param $where:检索条件
     * @param $join:联表
     * @return false/空/多数组(多列)
     */
    protected function queryBatch($columns, array $where = [], array $join = [])
    {
        $dbObj = self::$multi ? self::$slave : self::$master;   // 主从读从库

        if (empty($join)) {
            $result = $dbObj->select(static::$name, $columns, (empty($where) ? null : $where));
        } else {
            $result = $dbObj->select(static::$name, $join, $columns, (empty($where) ? null : $where));
        }

        if ($result === false) {
            file_put_contents(DB_LOG_FILE, $dbObj->last() . "\n", FILE_APPEND);
        }

        return $result;
    }

    /**
     * 插入单条数据
     * @return false/数据自增ID(单个PRIMARY KEY时)/true(没有PRIMARY KEY时)
     */
    protected function instOnly(array $data)
    {
        $dbObj = self::$master;
        $stmt = $dbObj->insert(static::$name, $data);

        if ($stmt === false) {
            file_put_contents(DB_LOG_FILE, $dbObj->last() . "\n", FILE_APPEND);
            return false;
        }

        if ($stmt->errorCode() === self::SUCC_CODE) {
            return empty($dbObj->id()) ? true : $dbObj->id();
        } else {
            file_put_contents(DB_LOG_FILE, $dbObj->last(). ' ' . $stmt->errorInfo()[2] . "\n", FILE_APPEND);
        }

        return false;
    }

    /**
     * 插入多条数据
     * @return false | int 影响行数
     */
    protected function instBatch(array $datas)
    {
        $dbObj = self::$master;
        $stmt = $dbObj->insert(static::$name, $datas);

        if ($stmt === false) {
            file_put_contents(DB_LOG_FILE, $dbObj->last() . "\n", FILE_APPEND);
            return false;
        }

        if ($stmt->errorCode() === self::SUCC_CODE) {
            return $stmt->rowCount();
        } else {
            file_put_contents(DB_LOG_FILE, $dbObj->last(). ' ' . $stmt->errorInfo()[2] . "\n", FILE_APPEND);
        }

        return false;
    }

    /**
     * 更新数据
     * @return false | int 影响函数
     */
    protected function update(array $data, array $where)
    {
        $dbObj = self::$master;
        $stmt = $dbObj->update(static::$name, $data, $where);

        if ($stmt === false) {
            file_put_contents(DB_LOG_FILE, $dbObj->last() . "\n", FILE_APPEND);
            return false;
        }

        if ($stmt->errorCode() === self::SUCC_CODE) {
            return $stmt->rowCount();
        } else {
            file_put_contents(DB_LOG_FILE, $dbObj->last(). ' ' . $stmt->errorInfo()[2] . "\n", FILE_APPEND);
        }

        return false;
    }

    /**
     * 删除数据
     * @return mixed : false | 影响行数
     */
    protected function delete(array $where)
    {
        $dbObj = self::$master;
        $stmt = $dbObj->delete(static::$name, $where);

        if ($stmt === false) {
            file_put_contents(DB_LOG_FILE, $dbObj->last() . "\n", FILE_APPEND);
            return false;
        }

        if ($stmt->errorCode() === self::SUCC_CODE) {
            return $stmt->rowCount();
        } else {
            file_put_contents(DB_LOG_FILE, $dbObj->last(). ' ' . $stmt->errorInfo()[2] . "\n", FILE_APPEND);
        }

        return false;
    }

    /**
     * 构造原始SQL
     */
    public static function raw($string, $map = [])
    {
        return \Medoo\Medoo::raw($string, $map);
    }

    /**
     * 打印SQL
     */
    public function last()
    {   
        $dbObj = self::$multi ? self::$slave : self::$master;   // 主从读从库
        return $dbObj->last();
    }

    /**
     * 事务处理
     * @param $func: 事务函数，在开启主从时，$func中涉及查询时会进行切库，导致事务错误*****
     * @return true/false/自定义函数返回
     */
    public function process(callable $func)
    {
        if (is_callable($func)) {
            $dbObj = self::$master;

            try {
                $result = $dbObj->action($func);
            } catch (\Exception $ex) {
                file_put_contents(DB_LOG_FILE, $ex->getMessage() . "\n", FILE_APPEND);
                return false;
            }

            return $result;
        }

        return false;
    }

}