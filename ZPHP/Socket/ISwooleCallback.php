<?php
/**
 * User: abing
 * Date: 13-6-17
 * socket swoole callback接口
 */
namespace ZPHP\Socket;
interface ISwooleCallback
{
	/**
	 * 当socket服务启动时，回调此方法
     * $server是swoole_server对象
	 */
    public function onStart(\swoole_server $server);

    /**
	 * 当有client连接上socket服务时，回调此方法
     *  $server是swoole_server对象
        $fd是连接的文件描述符，发送数据/关闭连接时需要此参数
        $from_id来自那个Reactor线程
	 */
    public function onConnect(\swoole_server $server, $fd, $from_id);

    /**
	 * 当有数据到达时，回调此方法
     *  $server，swoole_server对象
        $fd，TCP客户端连接的文件描述符
        $from_id，TCP连接所在的Reactor线程ID
        $data，收到的数据内容，可能是文本或者二进制内容
	 */
    public function onReceive(\swoole_server $server, $fd, $from_id, $data);

    /**
	 * 当有client断开时，回调此方法
     *  $server，swoole_server对象
        $fd，TCP客户端连接的文件描述符
        $from_id，TCP连接所在的Reactor线程ID
	 */
    public function onClose(\swoole_server $server, $fd, $from_id);

    /**
	 * 当socket服务关闭时，回调此方法
     * 在此之前Swoole Server已进行了如下操作

        已关闭所有线程
        已关闭所有worker进程
        已close所有TCP/UDP监听端口
        已关闭主Rector
     * 注意：
        强制kill进程不会回调onShutdown，如kill -9
        需要使用kill -15来发送SIGTREM信号到主进程才能按照正常的流程终止
	 */
    public function onShutdown(\swoole_server $server);

    public function setServer(\swoole_server $server);
}