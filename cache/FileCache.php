<?php
include_once CACHE_CACHE_PATH . 'AbstractCache.php';
final class FileCache extends AbstractCache
{
    private $_handle;
    const FILE_LIFE_KEY = 'FILE_LIFE_KEY';
    const CLEAR_ALL_KEY = 'CLEAR_ALL';
    const OPT_CACHE_DIR = 'cache_dir';
    const OPT_FILE_LOCK = 'file_lock';
    const OPT_FILE_PREFIX = 'file_prefix';
    const OPT_FILE_POSTFIX = 'file_postfix';
    const OPT_FILE_LIFE = 'file_life';
    const FILECACHE_INI = 'filecache.ini';
    protected $_options = array (
            self::OPT_CACHE_DIR => '/tmp',
            self::OPT_FILE_LOCK => TRUE,
            self::OPT_FILE_PREFIX => 'cache',
            self::OPT_FILE_POSTFIX => '.ch',
            self::OPT_FILE_LIFE => 0
    );

    protected function _init()
    {
        $config = parse_ini_file(CACHE_CONFIG_PATH . self::FILECACHE_INI, TRUE);
        if ($config === FALSE || empty($config['filecache'])) {
            die('Invalid file ' . CACHE_CONFIG_PATH . self::FILECACHE_INI . ', missing filecache node.');
        }

        if (!isset($config['filecache'][self::OPT_CACHE_DIR]) || !isset($config['filecache'][self::OPT_FILE_LIFE]) || !isset($config['filecache'][self::OPT_FILE_LOCK]) || !isset($config['filecache'][self::OPT_FILE_PREFIX]) || !isset($config['filecache'][self::OPT_FILE_POSTFIX])) {
            die('Invalid file ' . CACHE_CONFIG_PATH . self::FILECACHE_INI . ', filecache must contains cache_dir, file_lock, file_prefix, file_postfix, file_umask, file_life.');
        }

        $this -> _options[self::OPT_CACHE_DIR] = rtrim(rtrim($config['filecache'][self::OPT_CACHE_DIR], '\\'), '/');
        $realpath = realpath($this -> _options[self::OPT_CACHE_DIR]);
        if (!is_dir($realpath)) {
            mkdir($realpath, 0777, TRUE);
        }
        if (!is_writable($realpath)) {
            die('Path: ' . $realpath . ' is not writable.');
        }
        $this -> _options[self::OPT_CACHE_DIR] = $realpath;
        $this -> _options[self::OPT_FILE_LIFE] = (int)$config['filecache'][self::OPT_FILE_LIFE];
        $this -> _options[self::OPT_FILE_LOCK] = (bool)$config['filecache'][self::OPT_FILE_LOCK];
        $this -> _options[self::OPT_FILE_PREFIX] = $config['filecache'][self::OPT_FILE_PREFIX];
        $this -> _options[self::OPT_FILE_POSTFIX] = $config['filecache'][self::OPT_FILE_POSTFIX];
    }

    private function _write($filename, $data)
    {
        $dir = dirname($filename);
        if (!file_exists($dir)) {
            mkdir($dir, 0777, TRUE);
        }

        if ($this -> _options[self::OPT_FILE_LOCK]) {
            return file_put_contents($filename, $data, LOCK_EX);
        } else {
            return file_put_contents($filename, $data);
        }
    }

    private function _keyToFilename($key)
    {
        $filename = $this -> _options[self::OPT_FILE_PREFIX] . '_' . hash('fnv132', $key);
        $len = strlen($filename);
        $foldername = $filename[$len - 2] . $filename[$len - 1];
        return $this -> _options[self::OPT_CACHE_DIR] . DIRECTORY_SEPARATOR . $foldername . DIRECTORY_SEPARATOR . $filename . $this -> _options[self::OPT_FILE_POSTFIX];
    }

    public function add($key, $value, $expire = 0)
    {
        if (!$this -> isExists($key)) {
            return FALSE;
        }
        return $this -> set($key, $value, $expire);
    }

    public function set($key, $value, $expire = 0)
    {
        $expire = (int)$expire;
        $expire = $expire > 0 ? $expire : 365 * 24 * 60 * 60; /* one year for expire=0 */
        // if (is_object($value) || is_array($value) || is_resource($value)) {
        $value = serialize($value);
        // }
        $filename = $this -> _keyToFilename($key);
        $res = $this -> _write($filename, $value);
        $time = time() + $expire;
        return touch($filename, $time, $time) && $res;
    }

    public function get($key)
    {
        if (!$this -> isExists($key)) {
            return FALSE;
        }
        $filename = $this -> _keyToFilename($key);
        $data = file_get_contents($filename);
        return unserialize($data);
    }

    public function delete($key)
    {
        if ($this -> isExists($key)) {
            return unlink($this -> _keyToFilename($key));
        }
        return TRUE;
    }

    public function inc($key, $step = 1)
    {
        $value = (int)($this -> get($key)) + (int)$step;
        return $this -> set($key, $value, 0);
    }

    public function dec($key, $step = 1)
    {
        $value = (int)($this -> get($key)) - (int)$step;
        return $this -> set($key, $value, 0);
    }

    public function isExists($key, $convert = TRUE)
    {
        $filename = $convert ? $this -> _keyToFilename($key) : $key;
        if (!file_exists($filename)) {
            return FALSE;
        }
        if (time() > filemtime($filename)) {
            @unlink($filename);
            return FALSE;
        }
        return TRUE;
    }

    public function clearCache()
    {
        $this -> removeDir($this -> _options[self::OPT_CACHE_DIR]);
        return TRUE;
    }

    public function cacheInfo()
    {
        $files = array ();
        $expire = array ();
        $this -> getCacheFiles($this -> _options[self::OPT_CACHE_DIR], $files, $expire);
        $size = 0;
        $this -> dirSize($this -> _options[self::OPT_CACHE_DIR], $size);
        $total = count($files);
        $expire = count($expire);
        $active = $total - $expire;
        return array (
                'cache_count' => $total,
                'active_count' => $active,
                'expire_count' => $expire,
                'cache_size' => $this -> formatSize($size)
        );
    }

    public function removeDir($dir)
    {
        $files = glob(rtrim(rtrim($dir, '/'), '\\') . DIRECTORY_SEPARATOR . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
            if (($file !== '.' || $file !== '..') && is_dir($file)) {
                $this -> removeDir($file);
                rmdir($file);
            }
        }
    }

    public function dirSize($dir, &$size)
    {
        $contents = glob(rtrim(rtrim($dir, '/'), '\\') . DIRECTORY_SEPARATOR . '*', GLOB_NOSORT);
        foreach ($contents as $value) {
            if (is_file($value)) {
                $size += filesize($value);
            } else {
                $this -> dirSize($value, $size);
            }
        }
    }

    public function formatSize($size)
    {
        $type = array (
                'B',
                'KB',
                'MB',
                'GB',
                'TB'
        );
        $counter = 0;
        while ($size >= 1024) {
            $size /= 1024.0;
            ++$counter;
        }
        return round($size, 2) . $type[$counter];
    }

    public function getCacheFiles($dir, &$result, &$expire)
    {
        $files = glob(rtrim(rtrim($dir, '/'), '\\') . DIRECTORY_SEPARATOR . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                $result[] = $file;
                if (!$this -> isExists($file, FALSE)) {
                    $expire[] = $file;
                }
            } elseif (is_dir($file)) {
                $this -> getCacheFiles($file, $result, $expire);
            }
        }
    }
}