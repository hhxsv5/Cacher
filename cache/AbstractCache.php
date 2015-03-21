<?php
include_once CACHE_COMMON_PATH . 'Singleton.php';
abstract class AbstractCache extends Singleton
{

    protected function __construct()
    {
        $this -> _init();
    }

    abstract protected function _init();

    abstract public function add($key, $value, $expire = 0);

    abstract public function set($key, $value, $expire = 0);

    abstract public function get($key);

    abstract public function delete($key);

    abstract public function inc($key, $step = 1);

    abstract public function dec($key, $step = 1);

    abstract public function isExists($key);

    abstract public function clearCache();

    abstract public function cacheInfo();
}