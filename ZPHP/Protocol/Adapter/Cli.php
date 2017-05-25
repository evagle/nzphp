<?php
/**
 * User: shenzhe
 * Date: 13-6-17
 */


namespace ZPHP\Protocol\Adapter;

use ZPHP\Core;
use ZPHP\Core\ZConfig;
use ZPHP\Core\Request;
use ZPHP\View;
use ZPHP\Protocol\IProtocol;

class Cli implements IProtocol
{

    /**
     * 会取$_SERVER['argv']最后一个参数
     * 原始格式： a=action&m=method&param1=val1
     * @param $_data
     * @return bool
     * @throws \Exception
     */
    public function parse($_data)
    {
        $ctrlName = "";
        $methodName = "";
        \parse_str(array_pop($_data), $data);
        $apn = ZConfig::get('ctrl_name', 'a');
        $mpn = ZConfig::get('method_name', 'm');
        if (isset($data[$apn])) {
            $ctrlName = \str_replace('/', '\\', $data[$apn]);
        }
        if (isset($data[$mpn])) {
            $methodName = $data[$mpn];
        }
        if (empty($ctrlName) || empty($methodName)) {
            throw new \Exception("No router found, params = ".json_encode($data));
        }
        Request::init($ctrlName, $methodName, $data, ZConfig::get('view_mode', 'String'));
        return true;
    }
}
