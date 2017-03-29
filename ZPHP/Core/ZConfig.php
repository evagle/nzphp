<?php
/**
 * User: shenzhe
 * Date: 13-6-17
 * config配置处理
 */

namespace ZPHP\Core;

use ZPHP\Common\Dir;
use ZPHP\Protocol\Request;

class ZConfig
{

    private static $config;
    private static $nextCheckTime = 0;
    private static $lastModifyTime = 0;
    private static $configPath;

    public static function load($configPath)
    {
        $files = Dir::tree($configPath, "/.php$/");
        // 不要直接使用$config,include app.php时会导出变量$config,产生覆盖
        // 优先加载public.configs.php的配置, 项目配置覆盖public配置
        $__zconfig = array();
        if (!empty($files)) {
            foreach ($files as $i => $file) {
                if (substr($file, -18) == "public.configs.php") {
                    $__zconfig += include "{$file}";
                    unset($files[$i]);
                    break;
                }
            }
            foreach ($files as $file) {
                $__zconfig += include "{$file}";
            }
        }
        self::$config = $__zconfig;
        if (Request::isLongServer()) {
            self::$configPath = $configPath;
            self::$nextCheckTime = time() + empty($config['config_check_time']) ? 5 : $config['config_check_time'];
            self::$lastModifyTime = \filectime($configPath);
        }
        return self::$config;
    }

    public static function loadFiles(array $files)
    {
        $__config = array();
        foreach ($files as $file) {
            $__config += include "{$file}";
        }
        self::$config = $__config;
        return self::$config;
    }

    public static function get($key, $default = null, $throw = false)
    {
        self::checkTime();
        $result = isset(self::$config[$key]) ? self::$config[$key] : $default;
        if ($throw && is_null($result)) {
            throw new \Exception("{key} config empty");
        }
        return $result;
    }

    public static function set($key, $value, $set = true)
    {
        if ($set) {
            self::$config[$key] = $value;
        } else {
            if (empty(self::$config[$key])) {
                self::$config[$key] = $value;
            }
        }

        return true;
    }

    public static function getField($key, $field, $default = null, $throw = false)
    {
        $result = isset(self::$config[$key][$field]) ? self::$config[$key][$field] : $default;
        if ($throw && is_null($result)) {
            throw new \Exception("Cannot find config: key={$key} field={$field}.");
        }
        return $result;
    }

    public static function setField($key, $field, $value, $set = true)
    {
        if ($set) {
            self::$config[$key][$field] = $value;
        } else {
            if (empty(self::$config[$key][$field])) {
                self::$config[$key][$field] = $value;
            }
        }

        return true;
    }

    public static function all()
    {
        return self::$config;
    }

    public static function checkTime()
    {
        if (Request::isLongServer()) {
            if (self::$nextCheckTime < time()) {
                if (self::$lastModifyTime < \filectime(self::$configPath)) {
                    self::load(self::$configPath);
                }
            }
        }
        return;
    }
}
