<?php
/**
 * User: shenzhe
 * Date: 13-6-17
 */


namespace ZPHP\Protocol\Adapter;

use ZPHP\Core\ZConfig,
    ZPHP\Protocol\IProtocol;
use ZPHP\Protocol\Request;

class Json implements IProtocol
{
    public function parse($_data)
    {
        $ctrlName = ZConfig::get('default_ctrl_name', 'main\\main');
        $methodName = ZConfig::get('default_method_name', 'main');
        $data = [];
        if (!empty($_data)) {
            if (is_array($_data)) {
                $data = $_data;
            } else {
                $data = \json_decode($_data, true);
            }
        }
        $apn = ZConfig::get('ctrl_name', 'a');
        $mpn = ZConfig::get('method_name', 'm');
        if (isset($data[$apn])) {
            $ctrlName = \str_replace('/', '\\', $data[$apn]);
        }
        if (isset($data[$mpn])) {
            $methodName = $data[$mpn];
        }
        Request::init($ctrlName, $methodName, $data, ZConfig::get('view_mode', 'Json'));
        return true;
    }
}
