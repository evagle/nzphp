<?php
/**
 * User: shenzhe
 * Date: 13-6-17
 */


namespace ZPHP\DB\Connection;
use ZPHP\Protocol\Request;

class ConnectionPool
{

    protected static $connections = [];

    /**
     * @param $connectionName
     * @param bool $throw
     * @return Connection|null
     * @throws \Exception
     */
    public static function getConnection($connectionName)
    {
        if(!isset(self::$connections[$connectionName])) {
            return null;
        }
        return self::$connections[$connectionName];
    }

    public static function addConnection($connectionName, $config)
    {
        if(!isset(self::$connections[$connectionName])) {
            self::$connections[$connectionName] = new Connection($config);
        } else if(Request::isLongServer()){
            self::$connections[$connectionName]->ping();
        }
        return self::$connections[$connectionName];
    }


}
