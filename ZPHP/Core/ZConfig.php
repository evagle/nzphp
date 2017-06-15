<?php
/**
 * User: shenzhe
 * Date: 13-6-17
 * config配置处理
 */

namespace ZPHP\Core;

use ZPHP\Common\Dir;

class ZConfig
{

    private static $config = array();
    private static $nextCheckTime = 0;
    private static $lastModifyTime = 0;
    private static $configPath;

    public static function load($configPath)
    {
        // 配置文件入口为main.php
        $file = $configPath . "/main.php";
        if (!file_exists($file)) {
            throw new \Exception("Config file not found：$file");
        }
        include "$file";
        if (Request::isLongServer()) {
            self::$configPath = $configPath;
            self::$nextCheckTime = time() + empty($config['config_check_time']) ? 5 : $config['config_check_time'];
            self::$lastModifyTime = \filectime($configPath);
        }
        return self::$config;
    }

    public static function loadFiles(array $files, $dir = null)
    {
        $__config = array();
        foreach ($files as $file) {
            if (!empty($dir)) {
                $file = $dir . "/" .$file;
            }
            $content =  include "{$file}";
            if (!empty($content)) {
                $__config = array_merge($__config, $content);
            }
        }
        self::$config = array_merge(self::$config, $__config);
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
