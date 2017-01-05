<?php

namespace ZPHP\Conn\Adapter;
use ZPHP\Core\ZConfig as ZConfig,
    ZPHP\Conn\IConn;

/**
 *  redis 容器
 */
abstract class SocketConnectionManager implements IConn
{

    protected function getKey($uid, $prefix = 'uf')
    {
        return "{$prefix}_{$uid}_" . ZConfig::getField('connection', 'prefix');
    }

    /**
     * @param $uid
     * @return
     * array(
        'fd' => $fd,
        'time' => time(),
        'channels' => array('ALL' => 1)
        );
     */
    protected abstract function getConnectionInfo($uid);

    protected abstract function get($key);
    protected abstract function set($key, $data);
    protected abstract function delete($key);
    protected abstract function addToChannel($channel, $uid, $fd);
    protected abstract function deleteFromChannel($channel, $uid);

    public function addConnection($uid, $fd)
    {
        $key = $this->getKey($fd, 'fu');
        $this->set($key, $uid);
        $connInfo = $this->getConnectionInfo($uid);
        // 将旧连接清除
        if (!empty($connInfo)) {
            $oldFd = $connInfo['fd'];
            $oldUid = $this->getUidByFd($fd);
            if($oldUid == $uid) {
                $this->deleteConnection($oldFd, $uid);
            }
        }
        // 设置新连接
        $data = array(
            'fd' => $fd,
            'time' => time(),
            'channels' => array('ALL' => 1)
        );

        $this->set($this->getKey($uid), \json_encode($data));
        $this->addUidToChannel('ALL', $uid, $fd);
        return $connInfo;
    }


    /**
     * 通过uid获得Fd
     * @param $uid
     * @return mixed|null
     */
    public function getFdByUid($uid)
    {
        $connInfo = $this->getConnectionInfo($uid);
        if ($connInfo) {
            return $connInfo['fd'];
        }
        return null;
    }

    /**
     * 通过fd获得uid
     * @param $fd
     * @return mixed
     */
    public function getUidByFd($fd)
    {
        $key = $this->getKey($fd, 'fu');
        return $this->get($key);
    }

    /**
     * 通过uid获取连接信息
     * @param $uid
     * @return mixed
     *  array(
        'fd' => $fd,
        'time' => time(),
        'channels' => array('ALL' => 1)
        );
     */
    public function getConnectionByUid($uid)
    {
        return $this->getConnectionInfo($uid);
    }

    /**
     * 通过fd获取连接信息
     * @param $fd
     * @return mixed|null
     *  array(
        'fd' => $fd,
        'time' => time(),
        'channels' => array('ALL' => 1)
        );
     */
    public function getConnectionByFd($fd)
    {
        $uid = $this->getUidByFd($fd);
        if ($uid) {
            return $this->getConnectionInfo($uid);
        }
        return null;
    }

    /**
     * 删除fd及其相关数据,在connection关闭时调用
     * @param $fd
     * @param null $uid, uid可选
     */
    public function deleteConnection($fd, $uid = null)
    {
        if (null === $uid) {
            $uid = $this->getUidByFd($fd);
        }
        $this->delete($this->getKey($fd, 'fu'));
        if (empty($uid)) {
            return;
        }
        $connInfo = $this->getConnectionInfo($uid);
        if (!empty($connInfo)) {
            $this->delete($this->getKey($uid));
            foreach ($connInfo['channels'] as $channel => $val) {
                $this->deleteFromChannel($channel, $uid);
            }
        }
    }

    public function addUidToChannel($channel, $uid, $fd)
    {
        $connInfo = $this->getConnectionInfo($uid);
        if(empty($connInfo))
            return false;
        $connInfo['channels'][$channel] = 1;
        $fd = empty($fd) ? $connInfo['fd'] : $fd;
        if ($this->addToChannel($channel, $uid, $fd)) {
            $this->set($this->getKey($uid), json_encode($connInfo));
            return true;
        }

        return false;
    }

    public function deleteUidFromChannel($uid, $channel)
    {
        $this->deleteFromChannel($channel, $uid);

        $connInfo = $this->getConnectionInfo($uid);
        if(!empty($connInfo['channels'][$channel])) {
            unset($connInfo['channels'][$channel]);
            $this->set($this->getKey($uid), json_encode($connInfo));
        }

    }


    /**
     * 获取uid所属的channel列表
     * @param $uid
     * @return array [channel1, channel2]
     */
    public function getChannelsByUid($uid)
    {
        $data = $this->getConnectionInfo($uid);
        return array_keys($data['channels']);
    }

    /**
     * 删除一个channel
     * @param $channel
     */
    public function deleteChannel($channel)
    {
        $channelInfo = $this->getChannelInfo($channel);
        foreach ($channelInfo as $uid => $fd) {
            $connInfo = $this->getConnectionInfo($uid);
            if (!empty($connInfo) && isset($connInfo['channels'][$channel])) {
                unset($connInfo['channels'][$channel]);
                $this->set($this->getKey($uid), json_encode($connInfo));
            }
        }
    }

    public function updateHeartbeat($uid)
    {
        $connInfo = $this->getConnectionInfo($uid);
        if (empty($connInfo)) {
            return false;
        }
        $connInfo['time'] = time();
        return $this->set($this->getKey($uid), json_encode($connInfo));
    }

    public function heartbeat($uid, $ntime = 60)
    {
        $connInfo = $this->getConnectionInfo($uid);
        if (empty($connInfo)) {
            return false;
        }
        $time = time();
        if ($time - $connInfo['time'] > $ntime) {
            $this->deleteConnection($connInfo['fd'], $uid);
            return false;
        }
        return true;
    }

}