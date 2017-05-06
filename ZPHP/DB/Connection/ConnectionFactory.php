<?php
/**
 * User: shenzhe
 * Date: 13-6-17
 */


namespace ZPHP\DB\Connection;

use ZPHP\Core\Request;

class ConnectionFactory
{

    protected static $connections = [];

    /**
     * @param $connectionName
     * @return null|Connection
     */
    public static function getConnection($connectionName)
    {
        if(!isset(self::$connections[$connectionName])) {
            return null;
        }
        if (Request::isLongServer()) {
            self::$connections[$connectionName]->checkPing();
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
