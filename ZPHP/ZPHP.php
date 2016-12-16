<?php
/**
 * author: shenzhe
 * Date: 13-6-17
 * 初始化框架相关信息
 */
namespace ZPHP;
use ZPHP\Protocol\Response;
use ZPHP\View,
    ZPHP\Core\ZConfig,
    ZPHP\Common\Debug,
    ZPHP\Common\Formater;
class ZPHP
{
    /**
     * 项目目录
     * @var string
     */
    private static $rootPath;
    /**
     * 配置目录
     * @var string
     */
    private static $configPath = 'default';
    private static $appPath = 'apps';
    private static $zPath;
    private static $libPath='lib';
    private static $classPath = array();

    public static function getRootPath()
    {
        return self::$rootPath;
    }

    public static function setRootPath($rootPath)
    {
        self::$rootPath = $rootPath;
    }

    public static function getConfigPath()
    {
        $dir = self::getRootPath() . DS . 'config' . DS . self::$configPath;
        if (\is_dir($dir)) {
            return $dir;
        }
        return self::getRootPath() . DS . 'config' . DS . 'default';
    }

    public static function setConfigPath($path)
    {
        self::$configPath = $path;
    }

    public static function getAppPath()
    {
        return self::$appPath;
    }

    public static function setAppPath($path)
    {
        self::$appPath = $path;
    }

    public static function getZPath()
    {
        return self::$zPath;
    }

    public static function getLibPath()
    {
        return self::$libPath;
    }

    final public static function autoLoader($class)
    {
        if(isset(self::$classPath[$class])) {
            return;
        }
        $baseClasspath = \str_replace('\\', DS, $class) . '.php';
        $pre = substr($baseClasspath, 0, 4);
        if('ZPHP' === $pre) {
            $classpath = self::$zPath . DS . $baseClasspath;
            self::$classPath[$class] = $classpath;
            if (substr($classpath, 0, -3) == "Log") {
                file_put_contents("/tmp/a.log", json_encode(debug_backtrace()));
            }
            require "{$classpath}";
            return;
        }

        if('open' === $pre || 'SDK/' == $pre || 'Http' == $pre  || 'Serv'==$pre || 'Prot'==$pre || 'noti' == $pre) {
            $classpath = self::$rootPath . DS . '../lib'. DS . $baseClasspath;
            self::$classPath[$class] = $classpath;
            require "{$classpath}";
            return;
        }

        $classpath = self::$rootPath . DS . self::$appPath . DS . $baseClasspath;
        if (!file_exists($classpath)) {
            $classpath = self::$rootPath . DS . '../lib'. DS . $baseClasspath;
        }
        self::$classPath[$class] = $classpath;
        require "{$classpath}";
        return;
    }

    final public static function exceptionHandler($exception)
    {
        return Response::display(Formater::exception($exception));
    }

    final public static function fatalHandler()
    {
        $error = \error_get_last();
        if(empty($error)) {
            return;
        }
        if(!in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
            return;
        }
        Response::status('200');
        return Response::display(Formater::fatal($error));
    }

    /**
     * @param $rootPath
     * @param bool $run
     * @param null $configPath
     * @return \ZPHP\Server\IServer
     * @throws \Exception
     */
    public static function run($rootPath, $run=true, $configPath=null)
    {
        if (!defined('DS')) {
            define('DS', DIRECTORY_SEPARATOR);
        }
        self::$zPath = \dirname(__DIR__);
        self::setRootPath($rootPath);
        if (!empty($configPath)) {
            self::setConfigPath($configPath);
        }
        \spl_autoload_register(__CLASS__ . '::autoLoader');
        ZConfig::load(self::getConfigPath());
        self::$libPath = ZConfig::get('lib_path', self::$zPath . DS .'lib');
        if ($run && ZConfig::get('debug', 0)) {
            Debug::start();
        }
        $appPath = ZConfig::get('app_path', self::$appPath);
        self::setAppPath($appPath);
        $eh = ZConfig::getField('project', 'exception_handler', __CLASS__ . '::exceptionHandler');
        \set_exception_handler($eh);
        \register_shutdown_function( ZConfig::getField('project', 'fatal_handler', __CLASS__ . '::fatalHandler') );
        if(ZConfig::getField('project', 'error_handler')) {
            \set_error_handler(ZConfig::getField('project', 'error_handler'));
        }
        $timeZone = ZConfig::get('time_zone', 'Asia/Shanghai');
        \date_default_timezone_set($timeZone);
        $serverMode = ZConfig::get('server_mode', 'Http');
        $service = Server\Factory::getInstance($serverMode);
        if($run) {
            $service->run();
        }else{
            return $service;
        }
        if ($run && ZConfig::get('debug', 0)) {
            Debug::end();
        }
    }
}
