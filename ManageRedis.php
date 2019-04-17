<?php

final class ManageRedis
{
    static private $instance;
    private $connStmt;

    // 各业务redis数据管理
    // 业务索引 => array('key' => redis键, 'tmout' => 默认超时时间(s), 'fmt' => 数据存储格式, 'type' => redis数据类型)
    // key中{*}形式的字符串需要被替换
    // fmt : json、txt、numb、float
    // type : str、sset、set、list
    protected static $bussData = [
        'EXAMPLE' => ['db' => 0, 'key' => 'example_{id}', 'tmout' => 86400, 'type' => 'str', 'fmt' => 'json'],
    ];


    public function operRedisData($bussKey, $keyData = [], $operType = 'get', $bussData = '', $tmout = 0)
    {
        if (empty(self::$bussData[ $bussKey ]['key'])) {
            return false;
        }

        $key = self::$bussData[ $bussKey ]['key'];
        foreach ($keyData as $replName => $replValue) {
            $key = str_replace("{{$replName}}", $replValue, $key);
        }


        if (strpos($key, '{') !== false || strpos($key, '}') !== false) {
            return false;
        }

        if (IS_DEBUG) {
            $key = 'TEST_' . $key;
        }

        // 根据key指定redis的db索引
        if (!$this->connStmt->select(self::$bussData[ $bussKey ]['db'])) {
            return false;
        }

        $result = false;
        $setTmout = empty($tmout) ? (empty(self::$bussData[$bussKey]['tmout']) ? 0:self::$bussData[$bussKey]['tmout']):$tmout;


        // 公共处理
        switch ($operType) {
            case 'del':
                $this->connStmt->del($key);
                return true;
            case 'exp':
                if (!empty($setTmout)) {
                    $result = $this->connStmt->expire($key, $setTmout);
                }
                return $result;
            case 'exist':
                $result = $this->connStmt->exists($key);
                return $result;
            default:
                break;
        }


        // 各类型处理
        switch(self::$bussData[$bussKey]['type']) {
            case 'str':
                {
                    switch ($operType) {
                        case 'get':
                            $cash_data = $this->connStmt->get($key);
                            if ($cash_data !== false) {
                                $result = $this->turnDataFmt($cash_data, self::$bussData[$bussKey]['fmt']);
                            }
                            break;
                        case 'set':
                            $save_data = $this->turnDataFmt($bussData, self::$bussData[$bussKey]['fmt'], 1);

                            if (!empty($save_data)) {
                                if (!empty($setTmout)) {
                                    $result = $this->connStmt->setex($key, $setTmout, $save_data);
                                } else {
                                    $result = $this->connStmt->set($key, $save_data);
                                }
                            }
                            break;
                        case 'incr':
                            $step = empty($bussData) ? 0:(int)$bussData;

                            if (empty($step)) {
                                $result = $this->connStmt->incr($key);
                            } else {
                                $result = $this->connStmt->incrBy($key, $step);
                            }

                            // 起初key不存在时,操作不会设置超时时间
                            if (false !== $result && !empty($setTmout)) {
                                $ttl = $this->connStmt->ttl($key);
                                if ($ttl <= 0) {
                                    $this->connStmt->expire($key, $setTmout);
                                }
                            }
                            break;
                        case 'decr':
                            $step = empty($bussData) ? 0:(int)$bussData;

                            if (empty($step)) {
                                $result = $this->connStmt->decr($key);
                            } else {
                                $result = $this->connStmt->decrBy($key, $step);
                            }

                            // 起初key不存在时,操作不会设置超时时间
                            if (false !== $result && !empty($setTmout)) {
                                $ttl = $this->connStmt->ttl($key);
                                if ($ttl <= 0) {
                                    $this->connStmt->expire($key, $setTmout);
                                }
                            }
                            break;
                        default:
                            return false;
                    }
                }
                break;
            case 'list':
                {
                    switch ($operType) {
                        case 'lpush':
                            if (!empty($bussData)) {
                                if (is_array($bussData)) {
                                    foreach ($bussData as $eachData) {
                                        $ret = $this->connStmt->lPush($key, $eachData);
                                        if ((int)$ret === 1) {
                                            $this->connStmt->expire($key, $setTmout);
                                        }
                                    }
                                    $result = true;
                                } else {
                                    $result = $this->connStmt->lPush($key, $bussData);
                                    if ((int)$result === 1) {
                                        $this->connStmt->expire($key, $setTmout);
                                    }
                                }
                            }
                            break;
                        case 'rpush':
                            if (!empty($bussData)) {
                                if (is_array($bussData)) {
                                    foreach ($bussData as $eachData) {
                                        // 针对 $ret = false 时未加处理
                                        $ret = $this->connStmt->rPush($key, $eachData);
                                        if ((int)$ret === 1) {
                                            $this->connStmt->expire($key, $setTmout);
                                        }
                                    }
                                    $result = true;
                                } else {
                                    $result = $this->connStmt->rPush($key, $bussData);
                                    if ((int)$result === 1) {
                                        $this->connStmt->expire($key, $setTmout);
                                    }
                                }
                            }
                            break;
                        case 'lpop':
                            $result = $this->connStmt->lPop($key);
                            break;
                        case 'rpop':
                            $result = $this->connStmt->rPop($key);
                            break;
                        case 'getIdx':
                            $result = $this->connStmt->lIndex($key, $bussData);
                            break;
                        case 'size':
                            $result = $this->connStmt->lLen($key);
                            break;
                        default:
                            return false;
                    }
                }
                break;
//            case 'sset':
           case 'set':
               {
                   switch ($operType) {
                       case 'get':
                           $result = $this->connStmt->sMembers($key);
                           break;
                       case 'add':
                           if (empty($bussData)) {
                               return false;
                           }

                           $cacheData = is_array($bussData) ? $bussData : [$bussData];
                           $result = $this->connStmt->sAddArray($key, $cacheData);

                           if (false !== $result && !empty($setTmout)) {
                               $ttl = $this->connStmt->ttl($key);
                               if ($ttl <= 0) {
                                   $this->connStmt->expire($key, $setTmout);
                               }
                           }
                           break;
                       default:
                           return false;
                   }
               }
               break;
            default:
                return false;
        }

        return $result;
    }


    protected function turnDataFmt($src, $dstFmt, $direct = 0)
    {
        switch($dstFmt){
            case 'json':
                return empty($direct) ? json_decode($src, true) : json_encode($src);
            case 'numb':
                return empty($direct) ? (int)$src : $src;
            case 'float':
                return empty($direct) ? (float)$src : $src;
            case 'txt':
                return $src;
        }
    }

    public static function getInst()
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct()
    {
        $config = \Yaf\Registry::get('config')->get('redis')->toArray();

        $this->connStmt = new \Redis();
        $ret = $this->connStmt->pconnect($config['host'], $config['port']);
        if (!$ret) {
            exit('pconnect redis fail');
        }

        if (!empty($config['auth'])) {
            $ret = $this->connStmt->auth($config['auth']);
            if (!$ret) {
                $this->connStmt->close();
                exit('auth redis fail');
            }
        }
    }

    public function __destruct()
    {
        if ($this->connStmt) {
            $this->connStmt->close();
        }
    }

    private function __clone()
    {
    }




}