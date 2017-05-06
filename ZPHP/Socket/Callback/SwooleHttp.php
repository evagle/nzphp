<?php


namespace ZPHP\Socket\Callback;

use ZPHP\Core;
use ZPHP\Protocol;


abstract class SwooleHttp extends Swoole
{

    public function onReceive(\swoole_server $server, $fd, $from_id, $data)
    {
        throw new \Exception('http server must use onRequest');
    }

    public function onWorkerStart(\swoole_server $server, $workerId)
    {
        parent::onWorkerStart($server, $workerId);
        Core\Request::setHttpServer(1);
    }

    public function doRequest($request, $response)
    {
        Core\Request::setRequest($request);
        Core\Response::setResponse($response);
        $this->onRequest($request, $response);
    }

    abstract public function onRequest($request, $response);
}
