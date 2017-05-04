<?php
/**
 * User: shenzhe
 * Date: 13-6-17
 */
namespace ZPHP\Protocol;

use ZPHP\Core\Factory as CFactory;

class Factory
{
    public static function getInstance($adapter = 'Http')
    {
        $filename = __DIR__ . DS . 'Adapter' . DS . $adapter . '.php';
        if (is_file($filename)) {
            $className = __NAMESPACE__ . "\\Adapter\\{$adapter}";
        } else {
            throw new \Exception('file not found: '.$filename);
        }
        return CFactory::getInstance($className);
    }
}