<?php

namespace ZPHP\Conn\Adapter;
use ZPHP\Conn\IConn;

/**
 *  php内置数组
 */
class Php extends SocketConnectionManager implements IConn
{

    private $_cache = array();

    public function __construct($config)
    {
        
    }

    protected function get($key)
    {
        return $this->getByKey($key);
    }

    protected function set($key, $data)
    {
        $this->_cache[$key] = $data;
    }


    protected function delete($key)
    {
        unset($this->_cache[$key]);
    }

    public function clear()
    {
        $this->_cache = array();
    }

    private function getByKey($key)
    {
        if(isset($this->_cache[$key])) {
            return $this->_cache[$key];
        }
        return null;
    }

    /**
     * 获取指定的channel信息
     * @param $channel
     * @return array [uid1=> fd, uid2 => fd]
     */
    public function getChannelInfo($channel)
    {
        $key = $this->getKey($channel);
        return $this->getByKey($key);
    }

    /**
     * @param $uid
     * @return array
     * array(
     * 'fd' => $fd,
     * 'time' => time(),
     * 'channels' => array('ALL' => 1)
     * );
     */
    protected function getConnectionInfo($uid)
    {
        $key = $this->getKey($uid);
        return $this->getByKey($key);
    }

    protected function addToChannel($channel, $uid, $fd)
    {
        $key = $this->getKey($channel);
        $channelInfo = $this->getByKey($key);
        $channelInfo[$uid] = $fd;
        $this->_cache[$key] = $channelInfo;
    }

    protected function deleteFromChannel($channel, $uid)
    {
        $key = $this->getKey($channel);
        $channelInfo = $this->getByKey($key);
        unset($channelInfo[$uid]);
        $this->_cache[$key] = $channelInfo;
    }
}