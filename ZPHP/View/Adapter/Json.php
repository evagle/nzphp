<?php
/**
 * User: shenzhe
 * Date: 13-6-17
 * Json view
 */


namespace ZPHP\View\Adapter;
use ZPHP\Core\Request;
use ZPHP\Core\Response;
use ZPHP\View\Base,
    ZPHP\Core\ZConfig;

class Json extends Base
{
    public function display()
    {
        $data = \json_encode($this->model, JSON_UNESCAPED_UNICODE);
        if (Request::isHttp()) {
            $params = Request::getParams();
            $key = ZConfig::get( 'jsonp', 'jsoncallback');
            if(isset($params[$key])) {
                Response::header("Content-Type", 'application/x-javascript; charset=utf-8');
                $data = $params[$key].'('.$data.')';
            } else {
                Response::header("Content-Type", "application/json; charset=utf-8");
            }
        }
        if(Request::isLongServer()) {
            return $data;
        }
        echo $data;
        return null;

    }


}
