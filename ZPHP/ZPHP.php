<?php
/**
 * author: shenzhe
 * Date: 13-6-17
 * 初始化框架相关信息
 */
namespace ZPHP;
use ZPHP\Common\ZLog;
use ZPHP\Core\AutoLoader;
use ZPHP\Common\ZPaths;
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
//    private static $zPath;

    public static function getRootPath()
    {
        return ZPaths::getPath('root_path');
    }

    public static function setRootPath($rootPath)
    {
        ZPaths::setPath("root_path", $rootPath);
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
        return ZPaths::getPath('app_path');
    }

    public static function getLibPath()
    {
        return ZPaths::getPath("lib_path");
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

        ///// set root path
        self::setRootPath($rootPath);
        if (!empty($configPath)) {
            self::setConfigPath($configPath);
        }
        /// set auto loader
        \spl_autoload_register('\ZPHP\Core\AutoLoader::autoLoader');
        ZConfig::load(self::getConfigPath());

        /// set timezone
        $timeZone = ZConfig::get('time_zone', 'Asia/Shanghai');
        \date_default_timezone_set($timeZone);

        // set paths
        $frameworkPath = dirname(__DIR__);
        ZPaths::setPath('framework_path', $frameworkPath);

        $appPath = ZConfig::getField('paths', 'app_path', 'app');
        ZPaths::setPath('app_path', $rootPath . DS . $appPath);

        $libPath = ZConfig::getField('paths', 'lib_path', $rootPath . DS .'lib');
        ZPaths::setPath('lib_path', $libPath);

        $viewsPath = ZConfig::getField('paths', 'views_path', $rootPath . DS .'views' . DS . "default");
        ZPaths::setPath('views_path', $viewsPath);

        $paths = ZConfig::get('paths', []);
        foreach ($paths as $name => $path) {
            ZPaths::setPath($name, $rootPath . DS . $path);
        }

        /// add search paths
        AutoLoader::addSearchPath(ZPaths::getPath('framework_path') . DS);
        AutoLoader::addSearchPath(ZPaths::getPath('app_path') . DS);
        AutoLoader::addSearchPath(ZPaths::getPath('lib_path') . DS);
        $searchPaths = ZConfig::get('searchpaths', []);
        foreach ($searchPaths as $path) {
            AutoLoader::addSearchPath(self::$rootPath . DS . $path);
        }

        if ($run && ZConfig::get('debug', 0)) {
            Debug::start();
        }

        /// set exception handler
        $eh = ZConfig::getField('project', 'exception_handler', __CLASS__ . '::exceptionHandler');
        \set_exception_handler($eh);
        \register_shutdown_function( ZConfig::getField('project', 'fatal_handler', __CLASS__ . '::fatalHandler') );
        if(ZConfig::getField('project', 'error_handler')) {
            \set_error_handler(ZConfig::getField('project', 'error_handler'));
        }

        /// start server
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
        return null;
    }
}
