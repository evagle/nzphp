<?php

namespace ZPHP\Common;

class ZPaths
{
    /**
        $framework_path = "";
        $root_path = "";
        $app_path = "";
        $lib_path = "";
        $view_path = "";
        $log_path = "";
        ....
     * @var array
     */
    private static $paths = [
    ];
    public static function getPath($name)
    {
        return self::$paths[$name];
    }

    public static function setPath($name, $path)
    {
        self::$paths[$name] = $path;
    }

    public static function getAllPaths()
    {
        return self::$paths;
    }

    public static function clear()
    {
        self::$paths = [];
    }

}
