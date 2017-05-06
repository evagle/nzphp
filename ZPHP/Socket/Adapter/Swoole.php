<?php
/**
 * User: shenzhe
 * Date: 13-6-17
 * 所需扩展地址：https://github.com/matyhtf/swoole
 */


namespace ZPHP\Socket\Adapter;
use ZPHP\Socket\IServer,
    ZPHP\Socket\Callback;

class Swoole implements IServer
{
    private $callbackHandler;
    private $config;
    private $server;
    const TYPE_TCP = 'tcp';
    const TYPE_UDP = 'udp';
    const TYPE_HTTP = 'http';
    const TYPE_WEBSOCKET = 'websocket';

    public function __construct(array $config)
    {
        if(!\extension_loaded('swoole')) {
            throw new \Exception("no swoole extension. get: https://github.com/swoole/swoole-src");
        }
        if (empty($config['server_type'])) {
            throw new \Exception("server_type not given. Options: tcp|udp|http|websocket");
        }
        if (empty($config['work_mode'])) {
            throw new \Exception("work_mode not given. Options: 1|2|3. Doc:http://wiki.swoole.com/wiki/page/353.html");
        }
        $this->config = $config;
        $socketType = strtolower($config['server_type']);
        $this->config['server_type'] = $socketType;
        $server_work_mode = $config['work_mode'];
        switch($socketType) {
            case self::TYPE_TCP:
                $this->server = new \swoole_server($config['host'], $config['port'], $server_work_mode, SWOOLE_SOCK_TCP);
                break;
            case self::TYPE_UDP:
                $this->server = new \swoole_server($config['host'], $config['port'], $server_work_mode, SWOOLE_SOCK_UDP);
                break;
            case self::TYPE_HTTP:
                $this->server = new \swoole_http_server($config['host'], $config['port'], $server_work_mode);
                break;
            case self::TYPE_WEBSOCKET:
                $this->server = new \swoole_websocket_server($config['host'], $config['port'], $server_work_mode);
                break;

        }

        if(!empty($config['addlisten']) && $socketType != self::TYPE_UDP && SWOOLE_PROCESS == $server_work_mode) {
            $this->server->addlistener($config['addlisten']['ip'], $config['addlisten']['port'], SWOOLE_SOCK_UDP);
        }

        $this->server->set($config);
    }

    public function setCallbackHandler($callbackHandler)
    {
        if(!is_object($callbackHandler)) {
            throw new \Exception('client must object');
        }
        switch($this->config['server_type']) {
            case self::TYPE_WEBSOCKET:
                if (!($callbackHandler instanceof Callback\SwooleWebSocket)) {
                    throw new \Exception('client must instanceof ZPHP\Socket\Callback\SwooleWebSocket');
                }
                break;
            case self::TYPE_HTTP:
                if (!($callbackHandler instanceof Callback\SwooleHttp)) {
                    throw new \Exception('client must instanceof ZPHP\Socket\Callback\SwooleHttp');
                }
                break;
            case self::TYPE_UDP:
                if (!($callbackHandler instanceof Callback\SwooleUdp)) {
                    throw new \Exception('client must instanceof ZPHP\Socket\Callback\SwooleUdp');
                }
                break;
            default:
                if (!($callbackHandler instanceof Callback\Swoole)) {
                    throw new \Exception('client must instanceof ZPHP\Socket\Callback\Swoole');
                }
                break;
        }
        $callbackHandler->setServer($this->server);
        $this->callbackHandler = $callbackHandler;
        return true;
    }

    public function run()
    {
        $handlerArray = array(
            'onTimer',
            'onWorkerStart',
            'onWorkerStop',
            'onWorkerError',
            'onTask',
            'onFinish',
            'onWorkerError',
            'onManagerStart',
            'onManagerStop',
            'onPipeMessage',
            'onPacket',
        );
        $this->server->on('Start', array($this->callbackHandler, 'onStart'));
        $this->server->on('Shutdown', array($this->callbackHandler, 'onShutdown'));
        $this->server->on('Connect', array($this->callbackHandler, 'onConnect'));
        $this->server->on('Close', array($this->callbackHandler, 'onClose'));
        switch($this->config['server_type']) {
            case self::TYPE_TCP:
                $this->server->on('Receive', array($this->callbackHandler, 'doReceive'));
                break;
            case self::TYPE_HTTP:
                $this->server->on('Request', array($this->callbackHandler, 'doRequest'));
                break;
            case self::TYPE_WEBSOCKET:
                if(method_exists($this->callbackHandler, 'onHandShake')) {
                    $this->server->on('HandShake', array($this->callbackHandler, 'onHandShake'));
                }
                if(method_exists($this->callbackHandler, 'onOpen')) {
                    $this->server->on('Open', array($this->callbackHandler, 'onOpen'));
                }
                if(method_exists($this->callbackHandler, 'doRequest')) {
                    $this->server->on('Request', array($this->callbackHandler, 'doRequest'));
                }
                $this->server->on('Message', array($this->callbackHandler, 'onMessage'));
                break;
            case self::TYPE_UDP:
                array_pop($handlerArray);
                $this->server->on('Packet', array($this->callbackHandler, 'onPacket'));
                break;
        }

        foreach($handlerArray as $handler) {
            if(method_exists($this->callbackHandler, $handler)) {
                $this->server->on(\substr($handler, 2), array($this->callbackHandler, $handler));
            }
        }

        if(!empty($this->config['start_hook']) && is_callable($this->config['start_hook'])) {
            call_user_func($this->config['start_hook']);
        }
        $this->server->start();
    }
}
