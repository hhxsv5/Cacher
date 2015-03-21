<?php
abstract class Singleton
{
    private static $_instances = array ();

    protected function __construct()
    {
    }

    protected function __clone()
    {
    }

    public static function getInstance()
    {
        $calledClass = get_called_class();
        if (array_key_exists($calledClass, self::$_instances)) {
            return self::$_instances[$calledClass];
        }
        self::$_instances[$calledClass] = new $calledClass();
        return self::$_instances[$calledClass];
    }
}
