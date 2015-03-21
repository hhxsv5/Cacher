<?php
include_once CACHE_CACHE_PATH . 'AbstractCache.php';
include_once CACHE_LIB_PATH . '/flexihash/include/init.php';
final class RedisEx extends \Redis
{
    private $_id = NULL;

    public function __construct($id)
    {
        parent::__construct();
        $this -> _id = $id;
    }

    public function isAvailable()
    {
        try {
            $this -> ping();
            return TRUE;
        } catch (\RedisException $e) {
            return FALSE;
        }
    }

    public function getId()
    {
        return $this -> _id;
    }
}
final class RedisCache extends AbstractCache
{
    const REDIS_INI                 = 'redis.ini';
    const REDIS_DEFAULT_WEIGHT      = 5;
    const REDIS_MAX_WEIGHT          = 10;
    const REDIS_FLEXIHASH_PREFIX    = 'RFP_';
    private $_flexihash             = NULL;
    private $_servers               = array ();
    private $_RedisContainer        = array ();

    protected function _init()
    {
        $config = parse_ini_file(CACHE_CONFIG_PATH . self::REDIS_INI, TRUE);
        if ($config === FALSE || empty($config['redis']['server'])) {
            die('Invalid file ' . CACHE_CONFIG_PATH . self::REDIS_INI . ', missing redis server node.');
        }
        
        foreach ($config['redis']['server'] as $server) {
            $tmp = explode(',', $server);
            $count = count($tmp);
            if ($count == 2) {
                $this -> _servers[] = array (
                        $tmp[0],  /* host */
                        (int)$tmp[1] /* port */
                );
            } elseif ($count == 3) {
                $this -> _servers[] = array (
                        $tmp[0],  /* host */
                        (int)$tmp[1],  /* port */
                        (int)$tmp[2] /* timeout */
                );
            } elseif ($count == 4) {
                $this -> _servers[] = array (
                        $tmp[0],  /* host */
                        (int)$tmp[1],  /* port */
                        (int)$tmp[2], /* timeout */
                        (int)$tmp[3] /* weight */
                );
            } elseif ($count == 5) {
                $this -> _servers[] = array (
                        $tmp[0],  /* host */
                        (int)$tmp[1],  /* port */
                        (int)$tmp[2], /* timeout */
                        (int)$tmp[3], /* weight */
                        round((float)$tmp[4], 0) /* table name */
                );
            }
        }
        if (count($this -> _servers) == 0) {
            die('Invalid file ' . CACHE_CONFIG_PATH . self::REDIS_INI . ', redis server format must be host,port[,timeout].');
        }
        
        if (class_exists('\Redis', FALSE)) {
            $lastRedisId = 0;
            $this -> _flexihash = new \Flexihash();
            foreach ($this -> _servers as $server) {
                $tmp = new \RedisEx(self::REDIS_FLEXIHASH_PREFIX . ($lastRedisId++));
                $tmp -> connect($server[0], $server[1], (isset($server[2]) ? $server[2] : 0));
                if (isset($server[4])) {
                    $tmp -> select($server[4]);
                }
                $this -> addServer($tmp, isset($server[3]) ? $server[3] : self::REDIS_DEFAULT_WEIGHT);
            }
        } else {
            die('Missing redis extension.');
        }
    }

    public function addServer(\RedisEx $redisSingle, $weight = self::REDIS_MAX_WEIGHT)
    {
        if (isset($this -> _redisContainer[$redisSingle -> getId()])) {
            return FALSE;
        }
        $this -> _redisContainer[$redisSingle -> getId()] = $redisSingle;
        return $this -> _flexihash -> addTarget($redisSingle -> getId(), $weight);
    }

    public function getRedis($key)
    {
        try {
            $redisId = $this -> _flexihash -> lookup($key);
            // echo "\t\t\t\t", $key, ', ', $redisId, PHP_EOL;
            return $this -> _redisContainer[$redisId];
        } catch (\Flexihash_Exception $e) {
            return FALSE;
        }
    }

    public function add($key, $value, $expire = 0)
    {
        $redis = $this -> getRedis($key);
        return $redis ? ($redis -> setnx($key, $value) && $redis -> expire($key, (int)$expire)) : FALSE;
    }
    const REDIS_OBJECT_VALUE_POSTFIX = '_ROVP_';
    const REDIS_NULL_VALUE_POSTFIX = '_RNVP_';
    const REDIS_BOOLEAN_VALUE_POSTFIX = '_RBVP_';

    public function set($key, $value, $expire = 0)
    {
        $redis = $this -> getRedis($key);
        if (is_array($value) || is_object($value) || is_resource($value)) {
            $value = serialize($value);
            $value .= self::REDIS_OBJECT_VALUE_POSTFIX . md5($key);
        } elseif (is_null($value)) {
            $value = self::REDIS_NULL_VALUE_POSTFIX . md5($key);
        } elseif (is_bool($value)) {
            $value = ($value ? '1' : '0') . self::REDIS_BOOLEAN_VALUE_POSTFIX . md5($key);
        }
        return $redis ? ($redis -> set($key, $value) && ($expire == 0 ? TRUE : $redis -> expire($key, (int)$expire))) : FALSE;
    }

    public function get($key)
    {
        $redis = $this -> getRedis($key);
        if ($redis) {
            $value = $redis -> get($key);
            if (is_string($value)) {
                $md5 = md5($key);
                $objFlag = self::REDIS_OBJECT_VALUE_POSTFIX . $md5;
                $nullFlag = self::REDIS_NULL_VALUE_POSTFIX . $md5;
                $boolFlag = self::REDIS_BOOLEAN_VALUE_POSTFIX . $md5;
                if (stripos($value, $objFlag) !== FALSE) {
                    $value = substr($value, 0, strlen($value) - strlen($objFlag));
                    return unserialize($value);
                } elseif (stripos($value, $boolFlag) !== FALSE) {
                    return substr($value, 0, strlen($value) - strlen($boolFlag)) === '1';
                } elseif ($value === $nullFlag) {
                    return NULL;
                }
            }
            return $value;
        }
        return FALSE;
    }

    public function delete($key)
    {
        $redis = $this -> getRedis($key);
        return $redis ? $redis -> delete($key) : FALSE;
    }

    public function inc($key, $step = 1)
    {
        $redis = $this -> getRedis($key);
        return $redis ? $redis -> incrBy($key, (int)$step) : FALSE;
    }

    public function dec($key, $step = 1)
    {
        $redis = $this -> getRedis($key);
        return $redis ? $redis -> decrBy($key, (int)$step) : FALSE;
    }

    public function isExists($key)
    {
        $redis = $this -> getRedis($key);
        return $redis ? $redis -> exists($key) : FALSE;
    }

    public function clearCache()
    {
        $redisIds = $this -> _flexihash -> getAllTargets();
        foreach ($redisIds as $redisId) {
            $this -> _redisContainer[$redisId] -> flushDB();
        }
    }

    public function cacheInfo()
    {
        $info = array ();
        $redisIds = $this -> _flexihash -> getAllTargets();
        foreach ($redisIds as $redisId) {
            $info[$redisId] = $this -> _redisContainer[$redisId] -> info();
        }
        return $info;
    }
}