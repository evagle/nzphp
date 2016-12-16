<?php

namespace ZPHP\Core;

class AutoLoader
{
    private static $classPath = array();
    private static $searchPaths = [];

    public static function addSearchPath($path)
    {
        $path = str_replace("//", "/", $path);
        if (!in_array($path, self::$searchPaths)) {
            self::$searchPaths[] = $path;
        }
    }

    public static function getSearchPaths()
    {
        return self::$searchPaths;
    }

    final public static function autoLoader($class)
    {
        if(isset(self::$classPath[$class])) {
            return;
        }
        $baseClasspath = \str_replace('\\', DS, $class) . '.php';

        foreach (self::$searchPaths as $searchPath) {
            $classpath = $searchPath . DS . $baseClasspath;
            if (file_exists($classpath)) {
                self::$classPath[$class] = $classpath;
                require "{$classpath}";
                return;
            }
        }
        throw new \Exception("No class: ".$class." found.  Search paths: " . json_encode(self::$searchPaths));
    }
}
