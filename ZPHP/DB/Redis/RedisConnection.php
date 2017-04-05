<?php

namespace ZPHP\DB\Redis;
use ZPHP\Common\ZLog;

/**
 * Created by PhpStorm.
 * User: abing
 * Date: 5/4/2017
 * Time: 12:27
 */
class RedisConnection extends \Redis
{
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
                if ($this->ping() != "PONG") {
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

    public function reconnect()
    {
        if ($this->ispconnect) {
            $connectResult = $this->pconnect($this->config['host'], $this->config['port'], $this->timeout);
        } else {
            $connectResult = $this->connect($this->config['host'], $this->config['port'], $this->timeout);
        }
        if ($connectResult != true) {
            ZLog::emergency('redis.error', ["Connect to redis failed.", $this->config]);
            throw new \RedisException("Connect to redis failed. config=".json_encode($this->config));
        }
    }

    public function connect($host, $port = 6379, $timeout = 0.0)
    {
        parent::connect($host, $port, $timeout);
        $this->isClosed = false;
        $this->lastPingTime = time();
    }

    public function pconnect($host, $port = 6379, $timeout = 0.0)
    {
        parent::pconnect($host, $port, $timeout);
        $this->isClosed = false;
        $this->lastPingTime = time();
    }

    public function close()
    {
        parent::close();
        if (!$this->ispconnect) {
            $this->isClosed = true;
        }
    }
}