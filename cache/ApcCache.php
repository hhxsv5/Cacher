<?php
include_once 'AbstractCache.php';
final class ApcCache extends AbstractCache
{

    protected function _init()
    {
    }

    public function add($key, $value, $expire = 0)
    {
        return apc_add($key, $value, (int)$expire);
    }

    public function set($key, $value, $expire = 0)
    {
        return apc_store($key, $value, (int)$expire);
    }

    public function get($key)
    {
        return apc_fetch($key);
    }

    public function delete($key)
    {
        return apc_delete($key);
    }

    public function inc($key, $step = 1)
    {
        return apc_inc($key, (int)$step);
    }

    public function dec($key, $step = 1)
    {
        return apc_dec($key, (int)$step);
    }

    public function isExists($key)
    {
        return apc_exists($key);
    }

    public function clearCache()
    {
        return apc_clear_cache('user');
    }

    public function cacheInfo()
    {
        return apc_cache_info('user');
    }
}