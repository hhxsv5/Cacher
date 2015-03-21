<?php
include_once CACHE_CACHE_PATH . 'AbstractCache.php';
final class MemcacheCache extends AbstractCache
{
    const MEMCACHE_INI = 'memcache.ini';
    private static $_Servers = array ();
    private $_memcache;
    private $_isMemcached;

    protected function _init()
    {
        $config = parse_ini_file(CACHE_CONFIG_PATH . self::MEMCACHE_INI, TRUE);
        if ($config === FALSE || empty($config['memcache']['server'])) {
            die('Invalid file ' . CACHE_CONFIG_PATH . self::MEMCACHE_INI . ', missing memcache server node.');
        }

        foreach ($config['memcache']['server'] as $server) {
            $tmp = explode(',', $server);
            $count = count($tmp);
            if ($count == 2) {
                self::$_Servers[] = array (
                        $tmp[0],  /* host */
                        $tmp[1] /* port */
                );
            } elseif ($count == 3) {
                self::$_Servers[] = array (
                        $tmp[0],  /* host */
                        $tmp[1],  /* port */
                        $tmp[2] /* weight */
                );
            }
        }
        if (count(self::$_Servers) == 0) {
            die('Invalid file ' . CACHE_CONFIG_PATH . self::MEMCACHE_INI . ', memcache server format must be host,port[,weight].');
        }

        if (class_exists('\Memcached', FALSE)) {
            $this -> _isMemcached = TRUE;
            $this -> _memcache = new \Memcached('memcached_for_cache_service');
            foreach (self::$_Servers as $server) {
                $this -> _memcache -> addserver($server[0], $server[1], isset($server[2]) ? $server[2] : 0);
            }
        } else if (class_exists('\Memcache', FALSE)) {
            $this -> _isMemcached = FALSE;
            $this -> _memcache = new \Memcache();
            foreach (self::$_Servers as $server) {
                $this -> _memcache -> addserver($server[0], $server[1], TRUE, isset($server[2]) ? $server[2] : 0);
            }
        } else {
            die('Missing memcache extension.');
        }
    }

    public function add($key, $value, $expire = 0)
    {
        return $this -> _memcache -> add($key, $value, (int)$expire);
    }

    public function set($key, $value, $expire = 0)
    {
        if ($this -> _isMemcached) {
            return $this -> _memcache -> set($key, $value, (int)$expire);
        } else {
            return $this -> _memcache -> set($key, $value, MEMCACHE_COMPRESSED, (int)$expire);
        }
    }

    public function get($key)
    {
        return $this -> _memcache -> get($key);
    }

    public function delete($key)
    {
        return $this -> _memcache -> delete($key);
    }

    public function inc($key, $step = 1)
    {
        return $this -> _memcache -> increment($key, (int)$step);
    }

    public function dec($key, $step = 1)
    {
        return $this -> _memcache -> decrement($key, (int)$step);
    }

    public function isExists($key)
    {
        if ($this -> _isMemcached) {
            $this -> _memcache -> get($key);
            return $this -> _memcache -> getResultCode() != \Memcached::RES_NOTFOUND;
        } else {
            $flag = FALSE;
            $this -> _memcache -> get($key, $flag);
            return $flag ? TRUE : FALSE;
        }
    }

    public function clearCache()
    {
        return $this -> _memcache -> flush();
    }

    public function cacheInfo()
    {
        return $this -> _memcache -> getStats();
    }
}