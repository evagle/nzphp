<?php


namespace ZPHP\Socket\Callback;

use ZPHP\Core;


abstract class SwooleWebSocket extends SwooleHttp
{
    public function onRequest($request, $response)
    {
        $response->end('hello zphp');
    }

    /**
     *
        $frame 是swoole_websocket_frame对象，包含了客户端发来的数据帧信息
        onMessage回调必须被设置，未设置服务器将无法启动

     * swoole_websocket_frame 共有4个属性，分别是
        $frame->fd，客户端的socket id，使用$server->push推送数据时需要用到
        $frame->data，数据内容，可以是文本内容也可以是二进制数据，可以通过opcode的值来判断
        $frame->opcode，WebSocket的OpCode类型，可以参考WebSocket协议标准文档
        $frame->finish， 表示数据帧是否完整，一个WebSocket请求可能会分成多个数据帧进行发送
        $data 如果是文本类型，编码格式必然是UTF-8，这是WebSocket协议规定的

     * OpCode与数据类型
        WEBSOCKET_OPCODE_TEXT = 0x1 ，文本数据
        WEBSOCKET_OPCODE_BINARY = 0x2 ，二进制数据
     */
    abstract public function onMessage(\swoole_server $server, \swoole_websocket_frame $frame);

    /**
        $req 是一个Http请求对象，包含了客户端发来的握手请求信息
        onOpen事件函数中可以调用push向客户端发送数据或者调用close关闭连接
        onOpen事件回调是可选的
     */
    public function onOpen(\swoole_websocket_server $svr, \swoole_http_request $req)
    {

    }



    /**
        WebSocket建立连接后进行握手。WebSocket服务器已经内置了handshake，如果用户希望自己进行握手处理，可以设置onHandShake事件回调函数。
        onHandShake事件回调是可选的
        设置onHandShake回调函数后不会再触发onOpen事件，需要应用代码自行处理
        onHandShake函数必须返回true表示握手成功，返回其他值表示握手失败
        内置的握手协议为Sec-WebSocket-Version: 13，低版本浏览器需要自行实现握手
        1.8.1或更高版本可以使用server->defer调用onOpen逻辑
     */
//    public function onHandShake(swoole_http_request $request, swoole_http_response $response);


}
