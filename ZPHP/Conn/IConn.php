<?php

namespace ZPHP\Conn;


interface IConn
{
    /***********************************
     *  connection 相关接口
     ***********************************/
    /**
     * 添加一个连接，将uid和fd进行关联
     * @param $uid
     * @param $fd
     * @return mixed
     */
    public function addConnection($uid, $fd);

    /**
     * 通过uid获得Fd
     * @param $uid
     * @return mixed|null
     */
    public function getFdByUid($uid);

    /**
     * 通过fd获得uid
     * @param $fd
     * @return mixed
     */
    public function getUidByFd($fd);

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
    public function getConnectionByUid($uid);

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
    public function getConnectionByFd($fd);

    /**
     * 删除fd及其相关数据,在connection关闭时调用
     * @param $fd
     * @param null $uid, uid可选
     */
    public function deleteConnection($fd, $uid = null);


    /***********************************
     *  channel 相关接口
     ***********************************/
    /**
     * 把uid加入到指定的channel
     * @param $uid
     * @param $channel
     * @param $fd
     * @return mixed
     */
    public function addUidToChannel($channel, $uid, $fd);

    /**
     * 获取指定的channel信息
     * @param $channel
     * @return array [uid1=> fd, uid2 => fd]
     */
    public function getChannelInfo($channel);

    /**
     * 获取uid所属的channel列表
     * @param $uid
     * @return array [channel1, channel2]
     */
    public function getChannelsByUid($uid);

    /**
     * 把uid从指定的channel删除
     * @param $uid
     * @param $channel
     * @return mixed
     */
    public function deleteUidFromChannel($channel, $uid);

    /**
     * 删除一个channel, 所有这个channel下的用户信息也会更新
     * @param $channel
     */
    public function deleteChannel($channel);


    /***********************************
     *  heartbeat 相关接口
     ***********************************/
    /**
     * 更新心跳信息
     * @param $uid
     * @return mixed
     */
    public function updateHeartbeat($uid);

    /**
     *  心跳检测
     * @param $uid
     * @param $ntime
     * @return mixed
     */
    public function heartbeat($uid, $ntime);

    /**
     * 清空所有的连接信息
     */
    public function clear();

}