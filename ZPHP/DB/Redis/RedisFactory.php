<?php

namespace ZPHP\DB\Redis;

/**
 * Created by PhpStorm.
 * User: abing
 * Date: 5/4/2017
 * Time: 12:52
 */
class RedisFactory
{
    protected static $instances = [];

    /**
     * @param $config
     * @return \Redis
     */
    public static function getRedis($config)
    {
        $name = $config['host'].PATH_SEPARATOR.$config['port'];
        if (empty(self::$instances[$name])) {
            $instance = new RedisConnection();
            $instance->setConfig($config);
            $instance->connect();
            self::$instances[$name] = $instance;
        } else {
            $instance = self::$instances[$name];
            if (!$instance->checkPing()) {
                $instance->connect();
            }
        }
        return self::$instances[$name]->getRedisInstance();
    }

    public static function remove($config)
    {
        $name = $config['host'].PATH_SEPARATOR.$config['port'];
        if (!empty(self::$instances[$name])) {
            self::$instances[$name]->close();
            unset(self::$instances[$name]);
        }
    }

}