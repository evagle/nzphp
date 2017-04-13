<?php

namespace ZPHP\DB\Redis;
use ZPHP\Common\ZLog;

/**
 * Created by PhpStorm.
 * User: abing
 * Date: 5/4/2017
 * Time: 12:27
 */
class RedisConnection
{
    /**
     * @var \Redis
     */
    private $redisInstance;
    protected $config;
    protected $ispconnect;
    protected $pingtime;
    protected $timeout = 0;
    protected $lastPingTime;
    protected $isClosed;

    public function setConfig($config)
    {
        $this->config = $config;
        if (isset($config['pingtime'])) {
            $this->pingtime = intval($config['pingtime']);
        }
        if (isset($config['timeout'])) {
            $this->timeout = floatval($config['timeout']);
        }
        if (isset($config['pconnect'])) {
            $this->ispconnect = boolval($config['pconnect']);
        }
    }

    public function checkPing()
    {
        if ($this->isClosed) {
            ZLog::emergency('redis.error', ["Redis ping failed. Connection is closed.", $this->config]);
            return false;
        }
        $now = time();
        if ($this->pingtime && $this->lastPingTime + $this->pingtime <= $now) {
            try {
                if ($this->redisInstance->ping() != "PONG") {
                    $this->close();
                    ZLog::emergency('redis.error', ["Redis ping failed.", $this->config]);
                    return false;
                }
            } catch (\RedisException $e) {
                $this->close();
                ZLog::emergency('redis.error', ["Redis ping throws exception.", $this->config, $e]);
                return false;
            }
        }
        $this->lastPingTime = $now;
        return true;
    }

    /**
     * @return \Redis
     */
    public function getRedisInstance()
    {
        return $this->redisInstance;
    }

    public function connect()
    {
        $this->redisInstance = new \Redis();
        if ($this->ispconnect) {
            $connectResult = $this->redisInstance->pconnect($this->config['host'], $this->config['port'], $this->timeout);
        } else {
            $connectResult = $this->redisInstance->connect($this->config['host'], $this->config['port'], $this->timeout);
        }
        if ($connectResult != true) {
            $msg = "Connect to redis failed. ErrorMsg = " . $this->redisInstance->getLastError() ." Config = " . json_encode($this->config);
            ZLog::emergency('redis.error', [$msg]);
            $this->redisInstance = null;
            throw new \RedisException("Connect to redis failed. config=".json_encode($this->config));
        }
        $this->isClosed = false;
        $this->lastPingTime = time();
    }

    public function close()
    {
        $this->redisInstance->close();
        if (!$this->ispconnect) {
            $this->isClosed = true;
        }
        $this->redisInstance = null;
    }
}