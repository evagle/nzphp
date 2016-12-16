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
     * @return \Monolog\Logger
     */
    public static function getFileLogger($logFileName)
    {
        if (!self::$fileLoggers[$logFileName]) {
            self::$fileLoggers[$logFileName] = new Logger('NZPHP_File_Logger');
            $date = \date("Ymd");
            $logPath = ZConfig::getField('project', 'log_path', '');
            if(empty($logPath)) {
                $dir = ZPHP::getRootPath() . DS . 'log' . DS . $date;
            } else {
                $dir = $logPath . DS . $date;
            }
            Dir::make($dir);
            $logFile = $dir . \DS . $logFileName . '.log';
            $formatter = new LineFormatter(null, null, false, true);
            $debug = ZConfig::get('debug');
            $logLevel = $debug ? Logger::DEBUG : Logger::INFO;
            $handler = new StreamHandler($logFile, $logLevel);
            $handler->setFormatter($formatter);
            self::$fileLoggers[$logFileName]->pushHandler($handler);
        }
        return self::$fileLoggers[$logFileName];
    }

    public static function info($logFileName, $params = array())
    {
        $message = implode(self::SEPARATOR, array_map('ZPHP\Common\ZLog::toJson', $params));
        self::getFileLogger($logFileName)->addInfo($message);
    }

    public static function warning($logFileName, $params = array())
    {
        $message = implode(self::SEPARATOR, array_map('ZPHP\Common\ZLog::toJson', $params));
        self::getFileLogger($logFileName)->addWarning($message);
    }

    public static function debug($logFileName, $params = array())
    {
        $message = implode(self::SEPARATOR, array_map('ZPHP\Common\ZLog::toJson', $params));
        self::getFileLogger($logFileName)->addDebug($message);
    }
    
    public static function toJson($data)
    {
        if (is_string($data)) {
            return $data;
        } else {
		    return json_encode($data,  JSON_UNESCAPED_UNICODE);
        }
	}
}
