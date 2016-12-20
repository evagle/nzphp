<?php


namespace ZPHP\Socket\Callback;



abstract class SwooleUdp extends Swoole
{
    public function onReceive(\swoole_server $server, int $fd, int $from_id, string $data)
    {
        throw new \Exception('udp server must use onPacker');
    }

    /**
    $server，swoole_server对象
    $data，收到的数据内容，可能是文本或者二进制内容
    $client_info，客户端信息包括address/port/server_socket 3项数据

    服务器同时监听TCP/UDP端口时，收到TCP协议的数据会回调onReceive，收到UDP数据包回调onPacket

     * onPacket回调可以通过计算得到onReceive的$fd和$reactor_id参数值。计算方法如下：

    $fd = unpack('L', pack('N', ip2long($clientInfo['address'])))[1];
    $reactor_id = ($clientInfo['server_socket'] << 16) + $clientInfo['port'];

     */
    public function onPacket(\swoole_server $server, string $data, $clientInfo)
    {
        throw new \Exception('onPacket not implemented');
    }
}
