<?php
/**
 * Created by PhpStorm.
 * User: abing
 * Date: 21/12/2016
 * Time: 20:34
 */

namespace ZPHP\DB;


use Illuminate\Database\Eloquent\Model;
use ZPHP\ZPHP;


class BaseEntity extends Model
{
    public static function setConnectionConfig($name, $config)
    {
        ZPHP::getDbManager()->addConnection($config, $name);
    }

    public static function getConnectionConfig($name)
    {
        $manager = ZPHP::getDbManager();
        $container = $manager->getContainer();
        $connections = $container['config']['database.connections'];
        if ($connections[$name]) {
            return $connections[$name];
        }
        return null;
    }

    public static function find($id, $columns = ["*"])
    {
        $builder = self::query();
        return $builder->find($id, $columns);
    }

}