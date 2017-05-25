<?php
/**
 * User: shenzhe
 * Date: 13-6-17
 */


namespace ZPHP\Protocol\Adapter;
use ZPHP\Core\ZConfig;
use ZPHP\Protocol\IProtocol;
use ZPHP\Common\Route as ZRoute;
use ZPHP\Core\Request;

class Http implements IProtocol
{
    /**
     * 直接 parse $_REQUEST
     * @param $data
     * @return bool
     * @throws \Exception
     */
    public function parse($data)
    {
        $ctrlName = "";
        $methodName = "";
        $apn = ZConfig::get( 'ctrl_name', 'a');
        $mpn = ZConfig::get( 'method_name', 'm');
        if (isset($data[$apn])) {
            $ctrlName = \str_replace('/', '\\', $data[$apn]);
        }
        if (isset($data[$mpn])) {
            $methodName = $data[$mpn];
        }

        if(!empty($_SERVER['PATH_INFO']) && '/' !== $_SERVER['PATH_INFO']) {
            //swoole_http模式 需要在onRequest里，设置一下 $_SERVER['PATH_INFO'] = $request->server['path_info']
            $routeMap = ZRoute::match(ZConfig::get('route', false), $_SERVER['PATH_INFO']);
            if(is_array($routeMap)) {
                $ctrlName = \str_replace('/', '\\', $routeMap[0]);
                $methodName = $routeMap[1];
                if(!empty($routeMap[2]) && is_array($routeMap[2])) {
                    //参数优先
                    $data = $data + $routeMap[2];
                }
            }
        }
        if (empty($ctrlName) || empty($methodName)) {
            throw new \Exception("No router found for path : ".$_SERVER['PATH_INFO']);
        }
        Request::init($ctrlName, $methodName, $data, ZConfig::get('view_mode', 'Php'));
        return true;
    }
}
