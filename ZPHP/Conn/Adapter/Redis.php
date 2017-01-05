<?php

namespace ZPHP\Conn\Adapter;
use ZPHP\Core\ZConfig as ZConfig,
    ZPHP\Conn\IConn,
    ZPHP\Manager\Redis as ZRedis;

/**
 *  redis 容器
 */
class Redis extends SocketConnectionManager implements IConn
{

    private $redis;

    public function __construct($config)
    {
        if(empty($this->redis)) {
            $this->redis = ZRedis::getInstance($config);
            $db = ZConfig::getField('connection', 'db', 0);
            if(!empty($db)) {
                $this->redis->select($db);
            }
        }
    }

    /**
     * 获取指定的channel信息
     * @param $channel
     * @return array [uid1=> fd, uid2 => fd]
     */
    public function getChannelInfo($channel)
    {
        return $this->redis->hGetAll($this->getKey($channel));
    }

    public function clear()
    {
        $this->redis->flushDB();
    }

    /**
     * @param $uid
     * @return array|mixed array(
     * array(
     * 'fd' => $fd,
     * 'time' => time(),
     * 'channels' => array('ALL' => 1)
     * );
     */
    protected function getConnectionInfo($uid)
    {
        $data = $this->redis->get($this->getKey($uid));
        if (empty($data)) {
            return array();
        }

        return json_decode($data, true);
    }

    protected function get($key)
    {
        return $this->redis->get($key);
    }

    protected function set($key, $data)
    {
        return $this->redis->set($key, $data);
    }

    protected function delete($key)
    {
        $this->redis->delete($key);
    }

    protected function addToChannel($channel, $uid, $fd)
    {
        $ret = $this->redis->hSet($this->getKey($channel), $uid, $fd);
        return $ret !== false;
    }

    protected function deleteFromChannel($channel, $uid)
    {
        return $this->redis->hDel($this->getKey($channel), $uid);
    }


}