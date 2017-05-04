<?php
/**
 * User: shenzhe
 * Date: 13-6-17
 */


namespace ZPHP\Protocol\Adapter;

use ZPHP\Core\ZConfig;
use ZPHP\Protocol\IProtocol;
use ZPHP\Common\Route as ZRoute;
use ZPHP\Protocol\Request;

class GzHttp implements IProtocol
{
    /**
     * 直接 parse $_REQUEST
     * @param $_data
     * @return bool
     */
    public function parse($data)
    {
        $ctrlName = ZConfig::get('default_ctrl_name', 'main\\main');
        $methodName = ZConfig::get('default_method_name', 'main');
        $apn = ZConfig::get('ctrl_name', 'a');
        $mpn = ZConfig::get('method_name', 'm');
        if (isset($data[$apn])) {
            $ctrlName = \str_replace('/', '\\', $data[$apn]);
        }
        if (isset($data[$mpn])) {
            $methodName = $data[$mpn];
        }

        // 处理gzip压缩的数据
        $encoding = $_SERVER['HTTP_CONTENT_ENCODING'];
        if ($encoding == "gzip") {
            $rawBody = file_get_contents('php://input');
            $decodedBody = gzdecode($rawBody);
            $params = $_GET;
            parse_str($decodedBody, $params);
            if (!empty($params)) {
                $data = $params;
                $data[$apn] = $ctrlName;
                $data[$mpn] = $methodName;
            }
        }

        if (!empty($_SERVER['PATH_INFO']) && '/' !== $_SERVER['PATH_INFO']) {
            //swoole_http模式 需要在onRequest里，设置一下 $_SERVER['PATH_INFO'] = $request->server['path_info']
            $routeMap = ZRoute::match(ZConfig::get('route', false), $_SERVER['PATH_INFO']);
            if (is_array($routeMap)) {
                $ctrlName = \str_replace('/', '\\', $routeMap[0]);
                $methodName = $routeMap[1];
                if (!empty($routeMap[2]) && is_array($routeMap[2])) {
                    //参数优先
                    $data = $data + $routeMap[2];
                }
            }
        }

        Request::init($ctrlName, $methodName, $data, ZConfig::get('view_mode', 'Php'));
        return true;
    }
}
