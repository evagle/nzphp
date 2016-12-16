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
        $ctrlName = ZConfig::getField('project', 'default_ctrl_name', 'main\\main');
        $methodName = ZConfig::getField('project', 'default_method_name', 'main');
        $apn = ZConfig::getField('project', 'ctrl_name', 'a');
        $mpn = ZConfig::getField('project', 'method_name', 'm');
        if (isset($data[$apn])) {
            $ctrlName = \str_replace('/', '\\', $data[$apn]);
        }
        if (isset($data[$mpn])) {
            $methodName = $data[$mpn];
        }
        Request::init($ctrlName, $methodName, $data, ZConfig::getField('project', 'view_mode', 'Rpc'));
        return true;
    }
}
