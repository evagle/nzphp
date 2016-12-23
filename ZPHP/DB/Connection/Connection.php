<?php
/**
 * User: shenzhe
 * Date: 13-6-17
 */


namespace ZPHP\DB\Connection;
use Illuminate\Contracts\Logging\Log;
use ZPHP\Common\ZLog;
use ZPHP\Core\ZConfig;
use ZPHP\DB\ActiveRecord;
use ZPHP\Protocol\Request;

class Connection
{
    /**
     * @var \PDO
     */
    private $pdo;
    private $dbName;
    private $tableName;
    private $config;
    private $lastTime;
    private $lastSql;
    private $startTime;

    /**
     * @param $config
     * entityDemo
     * <?php
     *    假设数据库有user表,表含有id(自增主键), username, password三个字段
     *    class UserEntity {
     *         const TABLE_NAME = 'user';  //对应的数据表名
     *         const PK_ID = 'id';         //主键id名
     *         public $id;                 //public属性与表字段一一对应
     *         public $username;
     *         public $password;
     *    }
     * @param null $dbName
     * @throws \Exception
     */
    public function __construct($config=null, $dbName = null)
    {
        if(empty($config)) {
            throw new \Exception('config empty', -1);
        }
        $this->config = $config;
        if(empty($this->config['pingtime'])) {
            $this->config['pingtime'] = 3600;
        }
        if (empty($dbName)) {
            $this->dbName = isset($config['dbname']) ? $config['dbname'] : $config['database'];
        } else {
            $this->dbName = $dbName;
        }
        $this->lastTime = time() + $this->config['pingtime'];

        if (empty($this->pdo)) {
            $this->pdo = $this->connect();
        }

        $this->checkPing();
    }

    public function checkPing()
    {
        if ($this->pdo && !empty($this->config['ping'])) {
            $this->ping();
        }
    }

    private function connect()
    {
        if(Request::isLongServer() || empty($this->config['options'])) {
            $options = [
                \PDO::ATTR_PERSISTENT => false
            ];
        } else {
            $options = $this->config['options'];
        }
        return new \PDO($this->getDsn($this->config), $this->config['username'], $this->config['password'],
            array(
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '{$this->config['charset']}';",
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ) + $options);
    }

    /**
     * return table columns of table
     * @param $table
     * @return string
     * @throws \Exception
     */
    public function getTableColumns($table)
    {
        if ($this->config['driver'] == "mysql") {
            $query = "SELECT COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS ".
                "WHERE TABLE_SCHEMA='{$this->dbName}' and table_name = '{$table}'";
            $statement = $this->pdo->prepare($query);
            $this->lastSql = $query;
            $statement->execute();
            $columns = $statement->fetchAll(\PDO::FETCH_ASSOC);
            $result = [];
            foreach ($columns as $item) {
                $result[$item['COLUMN_NAME']] = $this->changeColumnType($item['COLUMN_TYPE']);
            }
            return $result;
        }
        throw new \Exception("getTableColumns not implemented for driver : {$this->config['driver']}.");
    }

    protected function changeColumnType($originType)
    {
        $originType = strtoupper($originType);
        if (strpos($originType, "INT") !== false) {
            return ActiveRecord::COLUMN_TYPE_INT;
        } else if (strpos($originType, "FLOAT") !== false || strpos($originType, "DOUBLE") !== false
                    || strpos($originType, "DECIMAL") !== false) {
            return ActiveRecord::COLUMN_TYPE_FLOAT;
        } else {
            return ActiveRecord::COLUMN_TYPE_STRING;
        }
    }

    protected function getDsn(array $config)
    {
        return $this->configHasSocket($config) ? $this->getSocketDsn($config) : $this->getHostDsn($config);
    }

    protected function configHasSocket(array $config)
    {
        return isset($config['unix_socket']) && ! empty($config['unix_socket']);
    }

    protected function getSocketDsn(array $config)
    {
        return "mysql:unix_socket={$config['unix_socket']};dbname={$config['database']}";
    }

    protected function getHostDsn(array $config)
    {
        return isset($config['port'])
            ? "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}"
            : "mysql:host={$config['host']};dbname={$config['database']}";
    }

    public function getDBName()
    {
        return $this->dbName;
    }


    public function setDBName($dbName)
    {
        if (empty($dbName)) {
            return;
        }
        $this->dbName = $dbName;
    }

    public function getPdo()
    {
        return $this->pdo;
    }

    protected function setPdo($pdo)
    {
        $this->pdo = $pdo;
    }

    protected function begin($table, $func)
    {
        $this->startTime = microtime(true);
        $debug = ZConfig::get('debug', 0);
        if ($debug) {
            ZLog::info('pdo_sql', ["start", $func, $table, $this->lastSql]);
        }
    }

    protected function end($table, $func)
    {
        $debug = ZConfig::get('debug', 0);
        if ($debug) {
            ZLog::info('pdo_sql', ["end", $func, $table, microtime(true) - $this->startTime, $this->lastSql]);
        }
    }

    /**
     * @param $table
     * @param string $where
     * @param null $input_params
     * @param string $fields
     * @param null $orderBy
     * @param int $limit
     * @param null $class
     * @return mixed
     * @throws \Exception
     */
    public function find($table, $where = '1', $input_params = null, $fields = '*', $orderBy = null, int $limit = 0, $class = null)
    {
        if (empty($table)) {
            throw new \Exception('table name not given');
        }
        $query = "SELECT {$fields} FROM `{$this->dbName}`.`{$table}` WHERE {$where}";

        if ($orderBy) {
            $query .= " order by {$orderBy}";
        }

        if ($limit) {
            $query .= " limit " . $limit;
        }
        $statement = $this->pdo->prepare($query);
        $this->lastSql = $query;
        $this->begin($table, "find");
        $statement->execute($input_params);
        if ($class) {
            $statement->setFetchMode(\PDO::FETCH_CLASS, $class);
        } else {
            $statement->setFetchMode(\PDO::FETCH_ASSOC);
        }

        $result = $statement->fetchAll();
        $this->end($table, "find");
        return $result;
    }

    public function insert($table, $model, $fields, $onDuplicate = false)
    {
        if ($onDuplicate) {
            return $this->replace($table, $model, $fields);
        }

        $strFields = '`' . implode('`,`', $fields) . '`';
        $strValues = ':' . implode(', :', $fields);

        $query = "INSERT INTO `{$this->dbName}`.`{$table}` ({$strFields}) VALUES ({$strValues})";
        $this->lastSql = $query;
        $this->begin($table, "insert");

        $statement = $this->pdo->prepare($query);
        $params = array();
        foreach ($fields as $field) {
            $params[$field] = $model->$field;
        }
        $statement->execute($params);
        $this->end($table, "insert");
        return $this->pdo->lastInsertId();
    }

    public function batchInsert($table, $models, $fields)
    {
        $items = array();
        $params = array();

        $strFields = '`' . implode('`,`', $fields) . '`';

        foreach ($models as $index => $model) {
            $items[] = '(:' . implode($index . ', :', $fields) . $index . ')';

            foreach ($fields as $field) {
                $params[$field.$index] = $model->$field;
            }
        }

        $query = "INSERT INTO `{$this->dbName}`.`{$table}` ({$strFields}) VALUES " . implode(',', $items);
        $this->lastSql = $query;
        $this->begin($table, "batchInsert");

        $statement = $this->pdo->prepare($query);
        $statement->execute($params);

        $this->end($table, "batchInsert");
        return $statement->rowCount();
    }


    public function update($table, $fields, $params, $where, $change = false)
    {
        if ($change) {
            $updateFields = array_map(__CLASS__ . '::changeFieldMap', $fields);
        } else {
            $updateFields = array_map(__CLASS__ . '::updateFieldMap', $fields);
        }

        $strUpdateFields = implode(',', $updateFields);
        $query = "UPDATE `{$this->dbName}`.`{$table}` SET {$strUpdateFields} WHERE {$where}";
        $this->lastSql = $query;
        $this->begin($table, "update");
        $statement = $this->pdo->prepare($query);

        $statement->execute($params);
        $this->end($table, "update");
        return $statement->rowCount();
    }

    public function replace($table, $model, $fields)
    {
        $strFields = '`' . implode('`,`', $fields) . '`';
        $strValues = ':' . implode(', :', $fields);

        $query = "REPLACE INTO `{$this->dbName}`.`{$table}` ({$strFields}) VALUES ({$strValues})";
        $this->lastSql = $query;
        $this->begin($table, "replace");

        $params = array();
        foreach ($fields as $field) {
            $params[$field] = $model->$field;
        }

        $statement = $this->pdo->prepare($query);
        $statement->execute($params);
        $this->end($table, "replace");
        return $this->pdo->lastInsertId();
    }

    public function batchReplace($table, $models, $fields)
    {
        $strFields = '`' . implode('`,`', $fields) . '`';

        $params = array();
        $items = array();
        foreach ($models as $index => $model) {
            $items[] = '(:' . implode($index . ', :', $fields) . $index . ')';

            foreach ($fields as $field) {
                $params[$field.$index] = $model->$field;
            }
        }

        $query = "REPLACE INTO `{$this->dbName}`.`{$table}` ({$strFields}) VALUES " . implode(',', $items);
        $this->lastSql = $query;
        $this->begin($table, "batchReplace");

        $statement = $this->pdo->prepare($query);
        $statement->execute($params);
        $this->end($table, "batchReplace");
        return $this->pdo->lastInsertId();
    }

    public function delete($table, $where)
    {
        if (empty($where)) {
            return false;
        }

        $query = "DELETE FROM `{$this->dbName}`.`{$table}` WHERE {$where}";
        $this->lastSql = $query;
        $this->begin($table, "delete");

        $statement = $this->pdo->prepare($query);
        $statement->execute();
        $this->end($table, "delete");
        return $statement->rowCount();
    }

    public function flush($table)
    {
        $query = "TRUNCATE `{$this->dbName}`.`{$table}`";
        $statement = $this->pdo->prepare($query);
        $this->lastSql = $query;
        return $statement->execute();
    }

    public function rowsCount($table, $primary_key, $where = "1")
    {
        $query = "SELECT count({$primary_key}) as count FROM `{$this->dbName}`.`{$table}` WHERE {$where}";
        $statement = $this->pdo->prepare($query);
        $this->lastSql = $query;
        $this->begin($table, "rowsCount");

        $statement->execute();
        $result = $statement->fetch();
        $this->end($table, "rowsCount");
        return $result["count"];
    }

    public function executeQuery($query)
    {
        $statement = $this->pdo->prepare($query);
        $this->lastSql = $query;
        $this->begin("rawquery", "executeQuery");

        $statement->execute();
        $statement->setFetchMode(\PDO::FETCH_ASSOC);

        $result = $statement->fetchAll();
        $this->end("rawquery", "executeQuery");
        return $result;
    }

    public static function updateFieldMap($field)
    {
        return '`' . $field . '`=:' . $field;
    }

    public static function changeFieldMap($field)
    {
        return '`' . $field . '`=`' . $field . '`+:' . $field;
    }

    public function ping()
    {
        $now = time();
        if($this->lastTime < $now) {
            if (empty($this->pdo)) {
                $this->pdo = $this->connect();
            } else {
                try {
                    $status = $this->pdo->getAttribute(\PDO::ATTR_SERVER_INFO);
                } catch (\Exception $e) {
                    if ($e->getCode() == 'HY000') {
                        $this->pdo = $this->connect();
                    } else {
                        throw $e;
                    }
                }
            }
        }
        $this->lastTime = $now + $this->config['pingtime'];
        return $this->pdo;
    }

    public function close()
    {
        if(empty($this->config['options'][\PDO::ATTR_PERSISTENT])) {
            $this->pdo = null;
        }
    }

    public function getLastSql()
    {
        return $this->lastSql;
    }
}
