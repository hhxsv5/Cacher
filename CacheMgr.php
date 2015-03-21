<?php
define('CACHE_ROOT_PATH', realpath(__DIR__) . DIRECTORY_SEPARATOR);
define('CACHE_CACHE_PATH', realpath(CACHE_ROOT_PATH . 'cache') . DIRECTORY_SEPARATOR);
define('CACHE_CONFIG_PATH', realpath(CACHE_ROOT_PATH . 'config') . DIRECTORY_SEPARATOR);
define('CACHE_COMMON_PATH', realpath(CACHE_ROOT_PATH . 'common') . DIRECTORY_SEPARATOR);
define('CACHE_LIB_PATH', realpath(CACHE_ROOT_PATH . 'lib') . DIRECTORY_SEPARATOR);
final class CacheMgr
{
    const CACHE_TYPE_APC        = 'apc';
    const CACHE_TYPE_FILE       = 'file';
    const CACHE_TYPE_MEMCACHE   = 'memcache';
    const CACHE_TYPE_REDIS      = 'redis';
    const CACHE_INI             = 'cache.ini';
    const CACHE_CLASS_POSTFIX   = 'Cache';
    const CACHE_FILE_POSTFIX    = '.php';
    private static $_Cache;
    private static $_CacheType;
    private static $_IsInited   = FALSE;
    private static $_CacheTypePair = array (
            self::CACHE_TYPE_FILE,
            self::CACHE_TYPE_APC,
            self::CACHE_TYPE_MEMCACHE,
            self::CACHE_TYPE_REDIS
    );

    private static function _InitCache()
    {
        self::GetCacheType();
        $className = ucfirst(strtolower(self::$_CacheType)) . self::CACHE_CLASS_POSTFIX;
        if (!class_exists($className, FALSE)) {
            if ((@include_once CACHE_CACHE_PATH . $className . self::CACHE_FILE_POSTFIX) === FALSE) {
                die('File ' . $className . self::CACHE_FILE_POSTFIX . ' is not found, include_path: ' . get_include_path());
            }
        }
        self::$_Cache = $className::getInstance();
        self::$_IsInited = TRUE;
    }

    public static function GetCacheType()
    {
        if (!empty(self::$_CacheType)) {
            return self::$_CacheType;
        }
        $config = parse_ini_file(CACHE_CONFIG_PATH . self::CACHE_INI, TRUE);
        if ($config === FALSE || empty($config['cache']['type'])) {
            die('Invalid file ' . CACHE_CONFIG_PATH . self::CACHE_INI . ', missing cache type node.');
        }

        if (!in_array($config['cache']['type'], self::$_CacheTypePair)) {
            die('Invalid file ' . CACHE_CONFIG_PATH . self::CACHE_INI . ', cache type must be in [' . implode(',', self::$_CacheTypePair) . '].');
        }
        self::$_CacheType = $config['cache']['type'];
        return self::$_CacheType;
    }

    public static function SetCacheInstance(AbstractCache $cache)
    {
        self::$_Cache = $cache;
    }

    public static function GetCacheInstance()
    {
        if (!self::$_IsInited) {
            self::_InitCache();
        }
        return self::$_Cache;
    }

    public function Add($key, $value, $expire = 0)
    {
        return self::GetCacheInstance() -> add($key, $value, $expire);
    }

    public static function Set($key, $value, $expire = 0)
    {
        return self::GetCacheInstance() -> set($key, $value, $expire);
    }

    public static function Get($key)
    {
        return self::GetCacheInstance() -> get($key);
    }

    public static function Delete($key)
    {
        return self::GetCacheInstance() -> delete($key);
    }

    public static function Inc($key, $step = 1)
    {
        return self::GetCacheInstance() -> inc($key, $step);
    }

    public static function Dec($key, $step = 1)
    {
        return self::GetCacheInstance() -> dec($key, $step);
    }

    public static function IsExists($key)
    {
        return self::GetCacheInstance() -> isExists($key);
    }

    public static function ClearCache()
    {
        return self::GetCacheInstance() -> clearCache();
    }

    public static function CacheInfo()
    {
        return self::GetCacheInstance() -> cacheInfo();
    }
}