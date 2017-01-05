<?php

namespace ZPHP\Conn\Adapter;
use ZPHP\Conn\IConn,
    ZPHP\Cache\Factory as ZCache;

/**
 *  yac共享内存
 */
class Yac extends SocketConnectionBase implements IConn
{

    private $yac;

    public function __construct($config)
    {
        if(empty($this->yac)) {
            $this->yac = ZCache::getInstance($config['adapter'], $config);
            if(!$this->yac->enable()) {
                throw new \Exception("Yac no enable");
                
            }
        }
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
    public function getConnectionInfo($uid)
    {
        $data = $this->yac->get($this->getKey($uid));
        if (empty($data)) {
            return array();
        }

        return json_decode($data, true);
    }

    public function clear()
    {
        $this->yac->clear();
    }

    /**
     * 获取指定的channel信息
     * @param $channel
     * @return array [uid1=> fd, uid2 => fd]
     */
    public function getChannelInfo($channel)
    {
        return json_decode($this->yac->get($this->getKey($channel)), true);
    }


    protected function get($key)
    {
        $this->yac->get($key);
    }

    protected function set($key, $data)
    {
        $this->yac->set($key, $data, 0);
    }

    protected function delete($key)
    {
        $this->yac->delete($key);
    }

    protected function addToChannel($channel, $uid, $fd)
    {
        $channelInfo = $this->getChannelInfo($channel);
        $channelInfo[$uid] = $fd;
        $this->yac->set($this->getKey($channel), json_encode($channelInfo), 0);
    }

    protected function deleteFromChannel($channel, $uid)
    {
        $channelInfo = $this->getChannelInfo($channel);
        unset($channelInfo[$uid]);
        $this->yac->set($this->getKey($channel), json_encode($channelInfo), 0);
    }
}