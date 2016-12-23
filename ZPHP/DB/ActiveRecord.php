<?php
/**
 * Created by PhpStorm.
 * User: abing
 * Date: 22/12/2016
 * Time: 18:40
 */

namespace ZPHP\DB;


use Illuminate\Contracts\Logging\Log;
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
     * store classes names which had constructed at least once
     */
    private static $inited;

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

    public function __construct(array $attributes = [])
    {
        $this->className = static::class;
        if (!isset(self::$inited[$this->className])) {
            $this->initConnection();
        }
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

        self::$inited[$this->className] = true;
    }

    /**
     * init connection when class first called
     */
    protected static function _initiate()
    {
        $className = static::class;
        if (!isset(self::$inited[$className])) {
            new $className;
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
        self::_initiate();
        $results = (new static())->findByIds($id, $assoc, $columns);
        if (count($results) > 0) {
            return $results[0];
        } else {
            return null;
        }
    }

    public static function findMany(array $ids, $assoc = false, $columns = "*")
    {
        self::_initiate();
        return (new static())->findByIds($ids, $assoc, $columns);
    }

    public static function all($assoc = false, $columns = "*")
    {
        self::_initiate();
        return (new static())->findAll($assoc, $columns);
    }

    public static function getColumnNames()
    {
        self::_initiate();
        return (new static())->_getColumnMetas();
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

    public function findByFields($fields, $assoc = false, $columns = "*")
    {
        $connection = $this->getConnection();
        $conditions = [];
        foreach ($fields as $k => $v) {
            $conditions = "`{$k}` = " . $this->wrapColumnData($k, $v);
        }
        $where = implode(" and ", $conditions);
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
            $params[$field] = $this->$field;
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
}