<?php

final class ManageCookie
{
    static private $instance;

    // 各业务cookie数据管理
    // 业务索引 => array('key' => cookie名, 'tmout' => 失效时间(s))
    // key中{*}形式的字符串需要被替换
    protected static $bussData = [
        'EXAMPLE' => ['key' => 'example', 'tmout' => 86400],
    ];

    /**
     * cookie数据操作
     * @param $bussKey : 业务索引key
     * @param $keyData : 键替换数据
     * @param $operType : 操作类型.get-读;set-置;del-删除
     * @param $bussData : 业务数据
     * @return get - 业务数据(数组) | false; set - true | false
     */
    public function operCookieData($bussKey, $keyData = [], $operType = 'get', $bussData = '')
    {
        if (empty(self::$bussData[ $bussKey ]['key'])) {
            return false;
        }

        $key = self::$bussData[ $bussKey ]['key'];
        if (!empty($keyData) && is_array($keyData)) {
            foreach ($keyData as $replName => $replValue) {
                $key = str_replace("{{$replName}}", $replValue, $key);
            }
        }

        if (strpos($key, '{') !== false || strpos($key, '}') !== false) {
            return false;
        }

        // 区分测试和线上环境的key
        if (IS_DEBUG) {
            $key = 'TEST_' . $key;
        }

        if ($operType == 'get') {
            $respBussData = isset($_COOKIE[ $key ]) ? addslashes(htmlspecialchars(trim($_COOKIE[ $key ]))) : '';
            return $respBussData;
        } elseif($operType == 'set') {
            $exp_time = time() + self::$bussData[ $bussKey ]['tmout'];
            setcookie($key, $bussData, $exp_time, '/');
            return true;
        } elseif($operType == 'del') {
            setcookie($key, '', -1, '/');
            return true;
        }

        return false;
    }

    public static function getInst()
    {
        if(!self::$instance instanceof self){
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    private function __clone()
    {
    }
}