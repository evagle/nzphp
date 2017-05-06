<?php
/**
 * User: shenzhe
 * Date: 13-6-17
 */


namespace ZPHP\Server\Adapter;
use ZPHP\Core,
    ZPHP\Server\IServer,
    ZPHP\Protocol;

class Http implements IServer
{

    public function run()
    {
        $protocol = Core\ZConfig::get('protocol', 'Http');
        Core\Request::setProtocol(Protocol\Factory::getInstance($protocol));
        Core\Request::parse($_REQUEST);
        return Core\Route::route();
    }

}