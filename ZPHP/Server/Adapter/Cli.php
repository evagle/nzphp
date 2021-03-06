<?php
/**
 * User: shenzhe
 * Date: 13-6-17
 */


namespace ZPHP\Server\Adapter;
use ZPHP\Core,
    ZPHP\Server\IServer,
    ZPHP\Protocol;

class Cli implements IServer
{
    public function run()
    {
        $protocol = Protocol\Factory::getInstance('Cli');
        Core\Request::setProtocol($protocol);
        Core\Request::parse($_SERVER['argv']);
        return Core\Route::route();
    }

}
