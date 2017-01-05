<?php

namespace ZPHP\Conn\Adapter;
use ZPHP\Conn\IConn;

/**
 *  swoole table 容器
 */
class Swoole extends SocketConnectionBase implements IConn
{

    private $table;

    public function __construct($config)
    {
        if(empty($this->table)) {
            $table = new \swoole_table(1024);
            $table->column('data', \swoole_table::TYPE_STRING, 64);
            $table->create();
            $this->table = $table;
        }
    }

    protected function get($key)
    {
        $this->table->get($key);
    }

    protected function set($key, $data)
    {
        $this->table->set($key, $data, 0);
    }

    protected function delete($key)
    {
        $this->table->delete($key);
    }

    protected function addToChannel($channel, $uid, $fd)
    {
        return $this->table->hSet($this->getKey($channel), $uid, $fd);
    }

    protected function deleteFromChannel($channel, $uid)
    {
        $this->table->hDel($this->getKey($channel), $uid);
    }

    public function getChannelInfo($channel)
    {
        return $this->table->hGetAll($this->getKey($channel));
    }

    public function getConnectionInfo($uid)
    {
        $data = $this->table->get($this->getKey($uid));
        if (empty($data)) {
            return array();
        }

        return json_decode($data, true);
    }

    public function clear()
    {
        $this->table->flushDB();
    }
}