<?php
/**
 * User: shenzhe
 * Date: 13-6-17
 */
namespace ZPHP\Queue\Adapter;


use ZPHP\DB\Redis\RedisFactory;

class Redis
{
    private $redis;

    public function __construct($config)
    {
        if (empty($this->redis)) {
            $this->redis = RedisFactory::getRedis($config);
        }
    }

    public function add($key, $data)
    {
        return $this->redis->rPush($key, $data);
    }

    public function get($key)
    {
        return $this->redis->lPop($key);
    }

    /**
     * 批量取出并清空所有的数据
     * 需最新redis-storage支持
     * @param $key
     * @return mixed
     */
    public function getAll($key)
    {
        return $this->redis->lAll($key);
    }

}