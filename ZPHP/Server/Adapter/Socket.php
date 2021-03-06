<?php
/**
 * User: shenzhe
 * Date: 13-6-17
 */


namespace ZPHP\Server\Adapter;
use ZPHP\Core\Request;
use ZPHP\Protocol\Factory as ZProtocol;
use ZPHP\Socket\Factory as SFactory;
use ZPHP\Core\ZConfig;
use ZPHP\Core\Factory as CFactory;
use ZPHP\Server\IServer;

class Socket implements IServer
{
    public function run()
    {
        $config = ZConfig::get('socket');
        if (empty($config)) {
            throw new \Exception("socket config empty");
        }
        $socket = SFactory::getInstance($config['adapter'], $config);
        if(method_exists($socket, 'setCallbackHandler')) {
            $callbackHandler = CFactory::getInstance($config['socket_callback_class']);
            $socket->setCallbackHandler($callbackHandler);
        }
        Request::setProtocol(ZProtocol::getInstance($config['protocol']));
        Request::setLongServer(1);
        Request::setHttpServer(0);
        $socket->run();
    }
}