<?php
/**
 * author: shenzhe
 * Date: 13-6-17
 * route处理类
 */
namespace ZPHP\Core;
use ZPHP\Controller\IController;
use ZPHP\Session\Swoole as SSESSION;

class Route
{
    public static function route()
    {
        $action = ZConfig::get('ctrl_path', 'controllers') . '\\' . Request::getCtrl();
        $class = Factory::getInstance($action);

        try {
            if (!($class instanceof IController)) {
                throw new \Exception("ctrl error");
            } else {
                $view = null;
                if($class->_before()) {
                    $method = Request::getMethod();
                    if (!method_exists($class, $method)) {
                        throw new \Exception("method error class = " . $action . " method = " . $method);
                    }
                    $view = $class->$method();
                } else {
                    throw new \Exception($action.':'.Request::getMethod().' _before() no return true');
                }
                $class->_after();
                if(Request::isLongServer()) {
                    SSESSION::save();
                }
                return Response::display($view);
            }
        }catch (\Exception $e) {
            $exceptionHandler = ZConfig::get('exception_handler', 'ZPHP\ZPHP::exceptionHandler');
            $result = \call_user_func($exceptionHandler, $e);
            self::runAfter($class);
            return $result;
        }
    }

    private static function runAfter($class)
    {
        try {
            if ($class instanceof IController) {
                $class->_after();
            }
        } catch (\Exception $e) {
            $exceptionHandler = ZConfig::get('exception_handler', 'ZPHP\ZPHP::exceptionHandler');
            \call_user_func($exceptionHandler, $e);
            return $e;
        }
        return null;
    }

}
