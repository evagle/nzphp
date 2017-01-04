<?php
/**
 * author: shenzhe
 * Date: 13-6-17
 * 日志输出类
 */

namespace ZPHP\Common;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use ZPHP\ZPHP,
    ZPHP\Core\ZConfig;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class ZLog
{
    const SEPARATOR = "\t";
    protected static $fileLoggers = null;

    /**
     * @param $logFileName
     * @return Logger
     * @throws \Exception
     */
    public static function getFileLogger($logFileName)
    {
        if (empty(self::$fileLoggers[$logFileName])) {
            if (!is_string($logFileName)) {
                throw new \Exception('log name must be string. name='. json_encode($logFileName));
            }
            self::$fileLoggers[$logFileName] = new Logger('NZPHP_File_Logger');
            $date = \date("Ymd");
            $logPath = ZConfig::getField('Paths', 'log_path');
            if(empty($logPath)) {
                $dir = ZPHP::getRootPath() . DS . 'log' . DS . $date;
            } else {
                $dir = $logPath . DS . $date;
            }
            Dir::make($dir);
            $logFile = $dir . \DS . $logFileName . '.log';
            $formatter = new LineFormatter(null, null, false, true);
            $debug = ZConfig::get('debug', 0);
            $logLevel = $debug ? Logger::DEBUG : Logger::INFO;
            $handler = new StreamHandler($logFile, $logLevel);
            $handler->setFormatter($formatter);
            self::$fileLoggers[$logFileName]->pushHandler($handler);
        }
        return self::$fileLoggers[$logFileName];
    }

    protected static function format($data)
    {
        if (is_array($data)) {
            $message = implode(self::SEPARATOR, array_map('ZPHP\Common\ZLog::toJson', $data));
        } else if (is_object($data)) {
            $message = ZLog::toJson($data);
        } else {
            $message = $data . "";
        }
        return $message;
    }

    public static function debug($logFileName, $params = array())
    {
        $message = self::format($params);
        self::getFileLogger($logFileName)->addDebug($message);
    }


    public static function info($logFileName, $params = array())
    {
        $message = self::format($params);
        self::getFileLogger($logFileName)->addInfo($message);
    }

    /**
     * Exceptional occurrences that are not errors
     *
     * Examples: Use of deprecated APIs, poor use of an API,
     * undesirable things that are not necessarily wrong.
     */
    public static function warning($logFileName, $params = array())
    {
        $message = self::format($params);
        self::getFileLogger($logFileName)->addWarning($message);
    }

    /**
     * Runtime errors
     */
    public static function error($logFileName, $params = array())
    {
        $message = self::format($params);
        self::getFileLogger($logFileName)->addError($message);
    }

    /**
     * Critical conditions
     *
     * Example: Application component unavailable, unexpected exception.
     */
    public static function critical($logFileName, $params = array())
    {
        $message = self::format($params);
        self::getFileLogger($logFileName)->addCritical($message);
    }

    /**
     * Action must be taken immediately
     *
     * Example: Entire website down, database unavailable, etc.
     * This should trigger the SMS alerts and wake you up.
     */
    public static function alert($logFileName, $params = array())
    {
        $message = self::format($params);
        self::getFileLogger($logFileName)->addAlert($message);
    }

    /**
     * Urgent alert.
     */
    public static function emergency($logFileName, $params = array())
    {
        $message = self::format($params);
        self::getFileLogger($logFileName)->addEmergency($message);
    }

    public static function toJson($data)
    {
        return json_encode($data,  JSON_UNESCAPED_UNICODE);
	}
}
