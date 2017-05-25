<?php
/**
 * Created by PhpStorm.
 * User: abing
 * Date: 22/12/2016
 * Time: 18:40
 */

namespace ZPHP\DB;


use ZPHP\Cache\ZCache;
use ZPHP\Core\ZConfig;
use ZPHP\DB\Connection\ConnectionFactory;

class ActiveRecord
{

    const COLUMN_TYPE_UNDEFINED = 0;
    const COLUMN_TYPE_INT = 1;
    const COLUMN_TYPE_FLOAT = 2;
    const COLUMN_TYPE_STRING = 3;
//    const COLUMN_TYPE_BINARY = 4;
//    const COLUMN_TYPE_DATE = 5;

    const AR_SOURCE_NEW = 1;
    const AR_SOURCE_FETCH = 2;

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
    protected $_orderBy;

    /**
     * set limit count
     */
    protected $_limit;

    /**
     * store columns of table
     * [
     *   table_name => [column1 => [type, default_value], column2 => [type, default_value]]
     * ]
     */
    private static $_tableColumnMetas;

    /**
     * store a instance for each class
     */
    private static $_staticInstancesPool;

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

    private $_baseCacheKey;

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

    private $_source;

    public function __construct(array $attributes = [])
    {
        $this->className = static::class;
        if (!isset(self::$_staticInstancesPool[$this->className])) {
            $this->initConnection();
        }
        if ($this->fetchHooks) {
            foreach ($this->fetchHooks as $field => $func) {
                if (property_exists($this, $field)) {
                    $this->$field = call_user_func($func, $this->$field);
                }
            }
        }
        $this->_source = self::AR_SOURCE_NEW;
    }

    /**
     * @return ActiveRecord
     */
    public static function getStaticInstance()
    {
        $className = static::class;
        if (empty(self::$_staticInstancesPool[$className])) {
            $instance = new $className;
            self::$_staticInstancesPool[$className] = $instance;
        }
        return self::$_staticInstancesPool[$className];
    }

    protected function initConnection()
    {
        if (empty($this->table)) {
            $this->table = strtolower(end(explode("\\", $this->className)));
        }
        $connection = $this->getConnection();

        if (empty(self::$_tableColumnMetas[$this->connectionName.$this->table])) {
            self::$_tableColumnMetas[$this->connectionName.$this->table] = $connection->getTableColumns($this->table);
        }
    }

    /**
     * @param $id
     * @param bool $assoc
     * @param string $columns
     * @return null|ActiveRecord
     */
    public static function find($id, $assoc = false, $columns = "*", $orderBy = "")
    {
        $results = self::getStaticInstance()->findByIds($id, $assoc, $columns, $orderBy);
        if (count($results) > 0) {
            return $results[0];
        } else {
            return null;
        }
    }

    public static function findMany(array $ids, $assoc = false, $columns = "*", $orderBy = "")
    {
        return self::getStaticInstance()->findByIds($ids, $assoc, $columns, $orderBy);
    }

    /**
     * @param array $fields : ["a" => 1, "b" =>2]
     * @param bool $assoc
     * @param string $columns
     * @param string $orderBy
     * @return mixed
     */
    public static function findByFields(array $fields, $assoc = false, $columns = "*", $orderBy = "")
    {
        return self::getStaticInstance()->findByCondition($fields, $assoc, $columns, $orderBy);
    }

    public static function all($assoc = false, $columns = "*", $orderBy = "")
    {
        return self::getStaticInstance()->findAll($assoc, $columns, $orderBy);
    }

    public static function delete($id)
    {
        return self::getStaticInstance()->deleteById($id);
    }

    public static function getColumnNames()
    {
        return array_keys(self::getStaticInstance()->_getColumnMetas());
    }

    public static function rowsCount($whereParams = [])
    {
        $instance = self::getStaticInstance();
        $connection = $instance->getConnection();
        list($where, $bindParams) = $instance->toWhereString($whereParams);
        $where = empty($where) ? "1" : $where;
        return $connection->rowsCount($instance->table, $instance->primary_key, $where, $bindParams);
    }

    public static function executeQuery($query)
    {
        $connection = self::getStaticInstance()->getConnection();
        return $connection->executeQuery($query);
    }

    protected function _getColumnMetas()
    {
        return self::$_tableColumnMetas[$this->connectionName.$this->table];
    }

    public function orderBy($orderBy)
    {
        $this->_orderBy = filter_var($orderBy, FILTER_SANITIZE_MAGIC_QUOTES);
        return $this;
    }

    public function limit($start, $length = false)
    {
        $this->_limit = "limit ".filter_var($start, FILTER_VALIDATE_INT);
        if ($length) {
            $this->_limit .= ", ".filter_var($length, FILTER_VALIDATE_INT);
        }
        return $this;
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
        $connection = ConnectionFactory::getConnection($this->connectionName);
        if (!$connection) {
            $config = $this->getConnectionConfig();
            if ($config) {
                $connection = ConnectionFactory::addConnection($this->connectionName, $config);
            } else {
                throw new \Exception('Database config empty. connection name = '.$this->connectionName);
            }
        }
        return $connection;
    }

    public function findByIds($ids, $assoc = false, $columns = "*", $orderBy = "")
    {
        if (!empty($orderBy)) {
            $this->_orderBy = $orderBy;
        }
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
            $limit = "";
        } else {
            $params = [$this->wrapColumnData($this->primary_key, $ids)];
            $where = "`{$this->primary_key}` = ?";
            $limit = "limit 1";
        }
        $className = $assoc ? "" : $this->className;
        $connection = $this->getConnection();
        $result = $connection->find($this->table, $where, $params, $columns,  $this->_orderBy, $limit, $className);

        $this->addToCache($cacheKey, $result);
        return $result;
    }

    public function findAll($assoc = false, $columns = "*", $orderBy = "")
    {
        if (!empty($orderBy)) {
            $this->_orderBy = $orderBy;
        }
        $cacheKey = $this->getCacheKey("all_".$this->_orderBy."_".$this->_limit);
        $data = $this->getFromCache($cacheKey);
        if ($data) {
            return $data;
        }
        $className = $assoc ? "" : $this->className;
        $connection = $this->getConnection();
        $result = $connection->find($this->table, "1", null, $columns, $this->_orderBy, $this->_limit, $className);

        $this->addToCache($cacheKey, $result);
        return $result;
    }

    /**
     * @param $fields :支持两种格式, 数组形式和kv形式
     * 1. [['id', ">=", 1], ["name", "is", "null"]]
     * 2. ['id' => 1, "name" => "x"]
     * @param bool $assoc
     * @param string $columns
     * @param string $orderBy
     * @return bool|mixed
     * @throws \Exception
     */
    public function findByCondition($fields, $assoc = false, $columns = "*", $orderBy = "")
    {
        if (empty($fields)) {
            throw new \Exception('query fields is empty');
        }
        if (!empty($orderBy)) {
            $this->_orderBy = $orderBy;
        }

        $cacheKey = $this->getCacheKey(json_encode($fields).$this->_orderBy."_".$this->_limit);
        $data = $this->getFromCache($cacheKey);
        if ($data) {
            return $data;
        }

        list($where, $params) = $this->toWhereString($fields);

        $className = $assoc ? "" : $this->className;
        $connection = $this->getConnection();
        $result = $connection->find($this->table, $where, $params, $columns,  $this->_orderBy, $this->_limit, $className);

        $this->addToCache($cacheKey, $result);
        return $result;
    }

    /**
     * @param $whereParams :支持两种格式, 数组形式和kv形式
     * 1. [['id', ">=", 1], ["name", "is", "null"]]
     * 2. ['id' => 1, "name" => "x"]
     * @param bool $assoc
     * @param string $columns
     * @param string $orderBy
     * @return bool|mixed
     * @throws \Exception
     */
    public function findWhere($whereParams, $assoc = false, $columns = "*", $orderBy = "")
    {
        if (empty($whereParams)) {
            throw new \Exception('where condition is empty!');
        }
        if (!empty($orderBy)) {
            $this->_orderBy = $orderBy;
        }
        $cacheKey = $this->getCacheKey(json_encode($whereParams).$this->_orderBy."_".$this->_limit);
        $data = $this->getFromCache($cacheKey);
        if ($data) {
            return $data;
        }

        list($where, $params) = $this->toWhereString($whereParams);

        $connection = $this->getConnection();
        $className = $assoc ? "" : $this->className;
        $result = $connection->find($this->table, $where, $params, $columns, $this->_orderBy, $this->_limit, $className);

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
        if ($this->_source == self::AR_SOURCE_FETCH) {
            return $this->update();
        } else {
            return $this->insert();
        }
    }

    public function replace()
    {
        $connection = $this->getConnection();
        return $connection->replace($this->table, $this, array_keys($this->_getColumnMetas()));
    }

    /**
     * @param $whereParams :支持两种格式, 数组形式和kv形式
     * 1. [['id', ">=", 1], ["name", "is", "null"]]
     * 2. ['id' => 1, "name" => "x"]
     * @return int
     */
    public function update($whereParams = [])
    {
        $connection = $this->getConnection();
        $params = array();
        $columns = array_keys($this->_getColumnMetas());
        foreach ($columns as $field) {
            $params[$field] = $this->getValueForDb($field);
        }

        if (!empty($whereParams)) {
            list($where, $bindParams) = $this->toWhereString($whereParams);
            $params = array_merge($params, $bindParams);
        } else {
            $where = "`{$this->primary_key}` = :_primary_key_";
            $key = $this->primary_key;
            $params[':_primary_key_'] = $this->$key;
        }
        return $connection->update($this->table, $columns, $params, $where, false);
    }

    protected function deleteById($id)
    {
        if (empty($id)) {
            throw new \Exception("Id to delete cannot be empty.");
        }
        $connection = $this->getConnection();
        $where = "`{$this->primary_key}` = :_primary_key_";
        $params[':_primary_key_'] = $id;
        return $connection->delete($this->table, $where, $params);
    }

    public function deleteSelf()
    {
        $key = $this->primary_key;
        return $this->deleteById($this->$key);
    }


    protected function getColumnType($columnName)
    {
        $columnNames = self::$_tableColumnMetas[$this->connectionName.$this->table];
        if (isset($columnNames[$columnName])) {
            return $columnNames[$columnName][0];
        }

        return self::COLUMN_TYPE_UNDEFINED;
    }

    public function getColumnDefaultValue($columnName)
    {
        $columnNames = self::$_tableColumnMetas[$this->connectionName.$this->table];
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

    protected function toWhereString($whereParams)
    {
        if (empty($whereParams)) {
            return false;
        }
        $params = [];
        if (isset($whereParams[0]) && is_array($whereParams[0]) && count($whereParams[0]) == 3) {
            $whereComponents = [];
            foreach ($whereParams as $i => $item) {
                $item = array_map(function ($var) {
                    return filter_var($var, FILTER_SANITIZE_MAGIC_QUOTES);
                }, $item);
                $whereComponents[] = "`{$item[0]}` {$item[1]} :w_{$i}_{$item[0]}";
                $params[":w_{$i}_{$item[0]}"] = $item[2];
            }
            return [implode(' and ', $whereComponents), $params];
        } else {
            $conditions = [];
            foreach ($whereParams as $k => $v) {
                $k = filter_var($k, FILTER_SANITIZE_MAGIC_QUOTES);
                $v = filter_var($v, FILTER_SANITIZE_MAGIC_QUOTES);
                $key = ":w_{$k}";
                $conditions[] = "`{$k}` = :w_{$k}";
                $params[$key] = $v;
            }
            return [implode(" and ", $conditions), $params];
        }
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
        $cacheInstance = ZCache::getInstance($config['adapter'], $config);
        return $cacheInstance;
    }

    public function getCacheKey($suffix = false)
    {
        if (empty($this->_baseCacheKey)) {
            $projectKey = ZConfig::get('project_name', '');
            $this->_baseCacheKey = $projectKey.str_replace('\\', '_', $this->className);
        }

        $cacheKey = $this->_baseCacheKey;
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

    public function setSource($source)
    {
        $this->_source = $source;
    }

    public function toArray()
    {
        $columns = self::getColumnNames();
        $data = [];
        foreach ($columns as $key) {
            if (property_exists($this, $key)) {
                $data[$key] = $this->$key;
            }
        }
        return $data;
    }
}