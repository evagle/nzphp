<?php
/**
 * User: shenzhe
 * Date: 13-6-17
 */


namespace ZPHP\Protocol\Adapter;
use ZPHP\Core;
use ZPHP\Core\ZConfig;
use ZPHP\Protocol\IProtocol;
use ZPHP\Protocol\Request;

class Rpc implements IProtocol
{

    /**
     * 直接 parse $_REQUEST
     * @param $data
     * @return bool
     */
    public function parse($data)
    {
        $ctrlName = ZConfig::get( 'default_ctrl_name', 'main\\main');
        $methodName = ZConfig::get( 'default_method_name', 'main');
        $apn = ZConfig::get( 'ctrl_name', 'a');
        $mpn = ZConfig::get( 'method_name', 'm');
        if (isset($data[$apn])) {
            $ctrlName = \str_replace('/', '\\', $data[$apn]);
        }
        if (isset($data[$mpn])) {
            $methodName = $data[$mpn];
        }
        Request::init($ctrlName, $methodName, $data, ZConfig::get( 'view_mode', 'Rpc'));
        return true;
    }
}
