<?php


namespace ZPHP\Socket\Callback;

use ZPHP\Common\ZPaths;
use ZPHP\Socket\ISwooleCallback;
use ZPHP\Core\ZConfig as ZConfig;
use ZPHP\Core;
use ZPHP\Protocol;


abstract class Swoole implements ISwooleCallback
{

    protected $protocol;

    protected $server;

    /**
     * @throws \Exception
     * @desc 服务启动，设置进程名及写主进程id
     */
    public function onStart(\swoole_server $server)
    {
        if (PHP_OS != "Darwin") {
            swoole_set_process_name(ZConfig::get('project_name') .
                ' server running ' .
                ZConfig::getField('socket', 'server_type', 'tcp') . '://' . ZConfig::getField('socket', 'host') . ':' . ZConfig::getField('socket', 'port')
                . " time:" . date('Y-m-d H:i:s') . "  master:" . $server->master_pid);
        }

        if (!empty(ZPaths::getPath('pid_path'))) {
            file_put_contents(ZPaths::getPath('pid_path') . DS . ZConfig::get('project_name') . '_master.pid', $server->master_pid);
        }
    }

    /**
     * @throws \Exception
     */
    public function onShutDown(\swoole_server $server)
    {
        if (!empty(ZPaths::getPath('pid_path'))) {
            $filename = ZPaths::getPath('pid_path') . DS . ZConfig::get('project_name') . '_master.pid';
            if (is_file($filename)) {
                unlink($filename);
            }
            $filename = ZPaths::getPath('pid_path') . DS . ZConfig::get('project_name') . '_manager.pid';
            if (is_file($filename)) {
                unlink($filename);
            }
        }
    }

    /**
     * @param $server
     * @throws \Exception
     * @desc 服务启动，设置进程名
     */
    public function onManagerStart(\swoole_server $server)
    {
        if (PHP_OS != "Darwin") {
            swoole_set_process_name(ZConfig::get('project_name') .
                ' server manager:' . $server->manager_pid);
        }

        if (!empty(ZPaths::getPath('pid_path'))) {
            file_put_contents(ZPaths::getPath('pid_path') . DS . ZConfig::get('project_name') . '_manager.pid', $server->manager_pid);
        }
    }

    /**
     * @param $server
     * @throws \Exception
     * @desc 服务关闭，删除进程id文件
     */
    public function onManagerStop(\swoole_server $server)
    {
        if (!empty(ZPaths::getPath('pid_path'))) {
            $filename = ZPaths::getPath('pid_path') . DS . ZConfig::get('project_name') . '_manager.pid';
            if (is_file($filename)) {
                unlink($filename);
            }
        }
    }

    public function onWorkerStart(\swoole_server $server, $workerId)
    {
        do {
            if (PHP_OS == "Darwin") {
                break;
            }
            $workNum = ZConfig::getField('socket', 'worker_num');
            if ($workerId >= $workNum) {
                swoole_set_process_name(ZConfig::get('project_name') . " server tasker  num: " . ($server->worker_id - $workNum) . " pid " . $server->worker_pid);
            } else {
                swoole_set_process_name(ZConfig::get('project_name') . " server worker  num: {$server->worker_id} pid " . $server->worker_pid);
            }
        } while (0);

        if(function_exists('opcache_reset')) {
            opcache_reset();
        }

        Core\Request::setSocket($server);
    }

    public function onWorkerStop(\swoole_server $server, $workerId)
    {
    }

    /**
     * @param \swoole_server $server
     * @param int $workerId 是异常进程的编号
     * @param int $workerPid 是异常进程的ID
     * @param int $errorCode 退出的状态码，范围是 1 ～255
     */
    public function onWorkerError(\swoole_server $server, $workerId, $workerPid, $errorCode)
    {

    }

    /**
         $server是swoole_server对象
         $fd是连接的文件描述符，发送数据/关闭连接时需要此参数
         $from_id来自那个Reactor线程
     */
    public function onConnect(\swoole_server $server, $fd,  $from_id)
    {

    }


    public function doReceive(\swoole_server $server, $fd, $from_id, $data)
    {
        Core\Request::setFd($fd);
        $this->onReceive($server, $fd, $from_id, $data);
    }

    /**
        $server，swoole_server对象
        $fd，TCP客户端连接的文件描述符
        $from_id，TCP连接所在的Reactor线程ID
        $data，收到的数据内容，可能是文本或者二进制内容
     */
    abstract public function onReceive(\swoole_server $server, $fd, $from_id, $data);


    /**
        $server，swoole_server对象
        $data，收到的数据内容，可能是文本或者二进制内容
        $client_info，客户端信息包括address/port/server_socket 3项数据

       服务器同时监听TCP/UDP端口时，收到TCP协议的数据会回调onReceive，收到UDP数据包回调onPacket

     * onPacket回调可以通过计算得到onReceive的$fd和$reactor_id参数值。计算方法如下：

        $fd = unpack('L', pack('N', ip2long($clientInfo['address'])))[1];
        $reactor_id = ($clientInfo['server_socket'] << 16) + $clientInfo['port'];

     */
    public function onPacket(\swoole_server $server, $data, $clientInfo)
    {

    }


    public function onClose(\swoole_server $server, $fd, $from_id)
    {

    }


    /**
        $task_id是任务ID，由swoole扩展内自动生成，用于区分不同的任务。
        $task_id和$src_worker_id组合起来才是全局唯一的，不同的worker进程投递的任务ID可能会有相同
        $src_worker_id来自于哪个worker进程
        $data 是任务的内容
     */
    public function onTask(\swoole_server $server, $taskId, $fromId, $data)
    {

    }

    /**
        $task_id是任务的ID
        $data是任务处理的结果内容
        task进程的onTask事件中没有调用finish方法或者return结果。worker进程不会触发onFinish
     */
    public function onFinish(\swoole_server $server, $taskId, $data)
    {

    }

    /**
        当工作进程收到由sendMessage发送的管道消息时会触发onPipeMessage事件。worker/task进程都可能会触发onPipeMessage事件。
        onPipeMessage在swoole-1.7.9以上版本可用
     */
    public function onPipeMessage(\swoole_server $server, $fromWorkerId, $data)
    {

    }

    public function setServer(\swoole_server $server)
    {
        $this->server = $server;
    }

}
