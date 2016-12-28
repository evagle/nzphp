<?php
/**
 * Created by PhpStorm.
 * User: abing
 * Date: 22/12/2016
 * Time: 18:40
 */

namespace ZPHP\DB;


use ZPHP\Common\ZLog;
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
     *   table_name => [column1 => type, column2 => type]
     * ]
     */
    private static $tableColumnMetas;

    /**
     * store a instance for each class
     */
    private static $staticInstancesPool;

    /**
     * record data
     */
    protected $data;

    /**
     * connection name set in config
     */
    protected $connectionName;

    /**
     * class name bind with table
     */
    protected $className;

    protected $useCache;

    protected $cacheType;

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
     * @return mixed
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
        return self::getStaticInstance()->_getColumnMetas();
    }

    public static function rowsCount($where = "1")
    {
        $instance = new static();
        $connection = $instance->getConnection();
        return $connection->rowsCount($instance->table, $instance->primary_key, $where);
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
        $connection = $this->getConnection();
        if (is_array($ids)) {
            foreach ($ids as &$id) {
                $id = $this->wrapColumnData($this->primary_key, $id);
            }
            $where =  "`{$this->primary_key}` IN ( " . implode(",", $ids). " ) ";
            $limit = 0;
        } else {
            $where = "`{$this->primary_key}` = " . $this->wrapColumnData($this->primary_key, $ids);
            $limit = 1;
        }
        $className = $assoc ? "" : $this->className;
        return $connection->find($this->table, $where, null, $columns,  $this->orderBy, $limit, $className);
    }

    public function findAll($assoc = false, $columns = "*")
    {
        $connection = $this->getConnection();
        $className = $assoc ? "" : $this->className;
        return $connection->find($this->table, "1", null, $columns, $this->orderBy, 0, $className);
    }

    public function findByCondition($fields, $assoc = false, $columns = "*")
    {
        if (empty($fields)) {
            throw new \Exception('query fields is empty');
        }
        $connection = $this->getConnection();
        $conditions = [];
        foreach ($fields as $k => $v) {
            $conditions[] = "`{$k}` = " . $this->wrapColumnData($k, $v);
        }
        $where = implode(" and ", $conditions);
        $className = $assoc ? "" : $this->className;
        return $connection->find($this->table, $where, null, $columns,  $this->orderBy, 0, $className);
    }

    public function findWhere($where, $assoc = false, $columns = "*")
    {
        if (empty($where)) {
            throw new \Exception('where condition is empty!');
        }
        $connection = $this->getConnection();
        $className = $assoc ? "" : $this->className;
        return $connection->find($this->table, $where, null, $columns,  $this->orderBy, 0, $className);
    }

    public function save()
    {
        $connection = $this->getConnection();
        return $connection->replace($this->table, $this, array_keys($this->_getColumnMetas()));
    }

    public function update()
    {
        $connection = $this->getConnection();
        $params = array();
        $columns = array_keys($this->_getColumnMetas());
        foreach ($columns as $field) {
            $params[$field] = $this->getValueForDb($field);
        }
        $key = $this->primary_key;
        $where = "`{$this->primary_key}` = " . $this->wrapColumnData($this->primary_key, $this->$key);
        return $connection->update($this->table, $columns, $params, $where,false);
    }

    protected function deleteById($id)
    {
        $connection = $this->getConnection();
        $where = "`{$this->primary_key}` = " . $this->wrapColumnData($this->primary_key, $id);
        $connection->delete($this->table, $where);
    }

    public function deleteSelf()
    {
        $key = $this->primary_key;
        $this->deleteById($this->$key);
    }

    public function executeQuery($query)
    {
        $connection = $this->getConnection();
        $connection->executeQuery($query);
    }

    protected function getColumnType($columnName)
    {
        $columnNames = self::$tableColumnMetas[$this->table];
        if (isset($columnNames[$columnName])) {
            return $columnNames[$columnName];
        }

        return self::COLUMN_TYPE_UNDEFINED;
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
                return $this->getConnection()->getPdo()->quote($value);
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
        if ($this->saveHooks && isset($this->saveHooks[$field])) {
            return call_user_func($this->saveHooks[$field], $this->$field);
        }
        return $this->$field;
    }
}