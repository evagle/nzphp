<?php
/**
 * Created by PhpStorm.
 * User: abing
 * Date: 22/12/2016
 * Time: 18:40
 */

namespace ZPHP\DB;


use ZPHP\Core\ZConfig;
use ZPHP\DB\Connection\ConnectionPool;

class ActiveRecord
{

    const COLUMN_TYPE_UNDEFINED = 0;
    const COLUMN_TYPE_INT = 1;
    const COLUMN_TYPE_FLOAT = 2;
    const COLUMN_TYPE_STRING = 3;
//    const COLUMN_TYPE_BINARY = 4;
//    const COLUMN_TYPE_DATE = 5;

    /**
     * primary key of table
     */
    protected $primary_key = 'id';

    /**
     * table name
     */
    protected $table;

    /**
     * set order by rules
     */
    protected $orderBy;

    /**
     * store columns of table
     * [
     *   table_name => [column1 => [type, default_value], column2 => [type, default_value]]
     * ]
     */
    private static $tableColumnMetas;

    /**
     * store a instance for each class
     */
    private static $staticInstancesPool;

    /**
     * connection name set in config
     */
    protected $connectionName;

    /**
     * class name bind with table
     */
    protected $className;

    protected $useCache;

    /**
     * use  ZConfig::getField('cache', $cacheConfigName) to get config cache
     */
    protected $cacheConfigName;

    private $baseCacheKey;

    /**
     * @var array callbacks called after data fetched form db
     * [
     *  field1 => callback name,
     *  field2 => callback name,
     * ]
     */
    protected $fetchHooks;

    /**
     * @var array callbacks called before save data to db
     * [
     *  field1 => callback name,
     *  field2 => callback name,
     * ]
     */
    protected $saveHooks;

    public function __construct(array $attributes = [])
    {
        $this->className = static::class;
        if (!isset(self::$staticInstancesPool[$this->className])) {
            $this->initConnection();
        }
        if ($this->fetchHooks) {
            foreach ($this->fetchHooks as $field => $func) {
                if (property_exists($this, $field)) {
                    $this->$field = call_user_func($func, $this->$field);
                }
            }
        }
    }

    /**
     * @return ActiveRecord
     */
    public static function getStaticInstance()
    {
        $className = static::class;
        if (!self::$staticInstancesPool[$className]) {
            $instance = new $className;
            self::$staticInstancesPool[$className] = $instance;
        }
        return self::$staticInstancesPool[$className];
    }

    protected function initConnection()
    {
        if (empty($this->table)) {
            $this->table = strtolower(end(explode("\\", $this->className)));
        }
        $connection = $this->getConnection();

        if (empty(self::$tableColumnMetas[$this->table])) {
            self::$tableColumnMetas[$this->table] = $connection->getTableColumns($this->table);
        }
    }

    /**
     * @param $id
     * @param bool $assoc
     * @param string $columns
     * @return null|ActiveRecord
     */
    public static function find($id, $assoc = false, $columns = "*")
    {
        $results = self::getStaticInstance()->findByIds($id, $assoc, $columns);
        if (count($results) > 0) {
            return $results[0];
        } else {
            return null;
        }
    }

    public static function findMany(array $ids, $assoc = false, $columns = "*")
    {
        return self::getStaticInstance()->findByIds($ids, $assoc, $columns);
    }

    /**
     * @param array $fields: ["a" => 1, "b" =>2]
     * @param bool $assoc
     * @param string $columns
     * @return mixed
     */
    public static function findByFields(array $fields, $assoc = false, $columns = "*")
    {
        return self::getStaticInstance()->findByCondition($fields, $assoc, $columns);
    }

    public static function all($assoc = false, $columns = "*")
    {
        return self::getStaticInstance()->findAll($assoc, $columns);
    }

    public static function getColumnNames()
    {
        return array_keys(self::getStaticInstance()->_getColumnMetas());
    }

    public static function rowsCount($where = "1")
    {
        $instance = self::getStaticInstance();
        $connection = $instance->getConnection();
        return $connection->rowsCount($instance->table, $instance->primary_key, $where);
    }

    public static function executeQuery($query)
    {
        $connection = self::getStaticInstance()->getConnection();
        return $connection->executeQuery($query);
    }

    protected function _getColumnMetas()
    {
        return self::$tableColumnMetas[$this->table];
    }

    /**
     * Override this function to set connection config as you wish.
     *  For example, dynamically select one from many db servers.
     * @return null
     */
    public function getConnectionConfig()
    {
        $config = ZConfig::getField('pdo', $this->connectionName);
        return $config;
    }

    /**
     * @return null|\ZPHP\DB\Connection\Connection
     * @throws \Exception
     */
    protected function getConnection()
    {
        $connection = ConnectionPool::getConnection($this->connectionName);
        if (!$connection) {
            $config = $this->getConnectionConfig();
            if ($config) {
                $connection = ConnectionPool::addConnection($this->connectionName, $config);
            } else {
                throw new \Exception('Database config empty. connection name = '.$this->connectionName);
            }
        }
        return $connection;
    }

    public function findByIds($ids, $assoc = false, $columns = "*")
    {
        $cacheKey = $this->getCacheKey($ids);
        $data = $this->getFromCache($cacheKey);
        if ($data) {
            return $data;
        }

        if (is_array($ids)) {
            $placeHolderStr = "";
            foreach ($ids as &$id) {
                $id = $this->wrapColumnData($this->primary_key, $id);
                $placeHolderStr .= ",?";
            }
            $params = $ids;
            $where =  "`{$this->primary_key}` IN ( " . substr($placeHolderStr, 1) . " ) ";
            $limit = 0;
        } else {
            $params = [$this->wrapColumnData($this->primary_key, $ids)];
            $where = "`{$this->primary_key}` = ?";
            $limit = 1;
        }
        $className = $assoc ? "" : $this->className;
        $connection = $this->getConnection();
        $result = $connection->find($this->table, $where, $params, $columns,  $this->orderBy, $limit, $className);

        $this->addToCache($cacheKey, $result);
        return $result;
    }

    public function findAll($assoc = false, $columns = "*")
    {
        $cacheKey = $this->getCacheKey("all");
        $data = $this->getFromCache($cacheKey);
        if ($data) {
            return $data;
        }
        $className = $assoc ? "" : $this->className;
        $connection = $this->getConnection();
        $result = $connection->find($this->table, "1", null, $columns, $this->orderBy, 0, $className);

        $this->addToCache($cacheKey, $result);
        return $result;
    }

    public function findByCondition($fields, $assoc = false, $columns = "*")
    {
        if (empty($fields)) {
            throw new \Exception('query fields is empty');
        }

        $cacheKey = $this->getCacheKey("all");
        $data = $this->getFromCache($cacheKey);
        if ($data) {
            return $data;
        }

        $params = [];
        $conditions = [];
        foreach ($fields as $k => $v) {
            $conditions[] = "`{$k}` = ?";
            $params[] = $v;
        }
        $where = implode(" and ", $conditions);
        $className = $assoc ? "" : $this->className;
        $connection = $this->getConnection();
        $result = $connection->find($this->table, $where, $params, $columns,  $this->orderBy, 0, $className);

        $this->addToCache($cacheKey, $result);
        return $result;
    }

    public function findWhere($where, $assoc = false, $columns = "*")
    {
        if (empty($where)) {
            throw new \Exception('where condition is empty!');
        }
        $cacheKey = $this->getCacheKey($where);
        $data = $this->getFromCache($cacheKey);
        if ($data) {
            return $data;
        }

        $connection = $this->getConnection();
        $className = $assoc ? "" : $this->className;
        $result = $connection->find($this->table, $where, null, $columns,  $this->orderBy, 0, $className);

        $this->addToCache($cacheKey, $result);
        return $result;
    }

    public function insert()
    {
        $connection = $this->getConnection();
        return $connection->insert($this->table, $this, array_keys($this->_getColumnMetas()));
    }

    public function save()
    {
        $connection = $this->getConnection();
        return $connection->replace($this->table, $this, array_keys($this->_getColumnMetas()));
    }

    /**
     * @param array $whereCondition: [['id', "=", $id], ['status' , "<>",  1]]
     * @return int
     */
    public function update($whereCondition = [])
    {
        $connection = $this->getConnection();
        $params = array();
        $columns = array_keys($this->_getColumnMetas());
        foreach ($columns as $field) {
            $params[$field] = $this->getValueForDb($field);
        }
        $where = "";
        if (!empty($whereCondition)) {
            foreach ($whereCondition as $item) {
                $item = array_map(function($var){
                    return filter_var(trim($var), FILTER_SANITIZE_MAGIC_QUOTES);
                }, $item);
                $where .= "`{$item[0]}` {$item[1]} :where_{$item[0]}";
                $params[":where_{$item[0]}"] = $item[2];
            }
        } else {
            $where = "`{$this->primary_key}` = :_primary_key_";
            $params[':_primary_key_'] = $this->$key;
        }
        return $connection->update($this->table, $columns, $params, $where, false);
    }

    protected function deleteById($id)
    {
        $connection = $this->getConnection();
        $where = "`{$this->primary_key}` = :_primary_key_";
        $params[':_primary_key_'] = $id;
        $connection->delete($this->table, $where, $params);
    }

    public function deleteSelf()
    {
        $key = $this->primary_key;
        $this->deleteById($this->$key);
    }


    protected function getColumnType($columnName)
    {
        $columnNames = self::$tableColumnMetas[$this->table];
        if (isset($columnNames[$columnName])) {
            return $columnNames[$columnName][0];
        }

        return self::COLUMN_TYPE_UNDEFINED;
    }

    public function getColumnDefaultValue($columnName)
    {
        $columnNames = self::$tableColumnMetas[$this->table];
        if (isset($columnNames[$columnName])) {
            return $columnNames[$columnName][1];
        }

        return null;
    }

    protected function wrapColumnData($columnName, $value)
    {
        $columnType = $this->getColumnType($columnName);
        switch ($columnType) {
            case self::COLUMN_TYPE_INT:
                return $value . '';
                break;
            case self::COLUMN_TYPE_FLOAT:
                return $value . '';
                break;
            case self::COLUMN_TYPE_STRING:
                return $value;
                break;
        }
        return "";
    }

    public function flush()
    {
        $connection = $this->getConnection();
        $connection->flush($this->table);
    }

    public function getValueForDb($field)
    {
        if (property_exists($this, $field)) {
            if ($this->saveHooks && isset($this->saveHooks[$field])) {
                return call_user_func($this->saveHooks[$field], $this->$field);
            } else {
                return $this->$field;
            }
        } else {
            return $this->getColumnDefaultValue($field);
        }
    }

    /**
     * @return \ZPHP\Cache\ICache
     */
    public function getCache()
    {
        $config = ZConfig::getField('cache', $this->cacheConfigName, null, true);
        $cacheInstance = \ZPHP\Cache\Factory::getInstance($config['adapter'], $config);
        return $cacheInstance;
    }

    public function getCacheKey($suffix = false)
    {
        if (empty($this->baseCacheKey)) {
            $projectKey = ZConfig::get('project_name', '');
            $this->baseCacheKey = $projectKey.str_replace('\\', '_', $this->className);
        }

        $cacheKey = $this->baseCacheKey;
        if($suffix) {
            if (\is_array($suffix)) {
                $cacheKey .= json_encode($suffix);
            } else {
                $cacheKey .= $suffix;
            }
        }
        if (strlen($cacheKey) > 48) {
            $cacheKey = md5($cacheKey);
        }
        return $cacheKey;
    }

    public function addToCache($cacheKey, $data, $timeout = 0)
    {
        if (!$this->useCache) {
            return false;
        }
        $cacheInstance = $this->getCache();
        return $cacheInstance->add($cacheKey, $data, $timeout);
    }

    public function getFromCache($cacheKey)
    {
        if (!$this->useCache) {
            return false;
        }
        $cacheInstance = $this->getCache();
        return $cacheInstance->get($cacheKey);
    }

}