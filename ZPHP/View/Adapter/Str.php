<?php
/**
 * User: shenzhe
 * Date: 13-6-17
 * 
 */


namespace ZPHP\View\Adapter;
use ZPHP\Core\Request;
use ZPHP\Core\Response;
use ZPHP\View\Base,
    ZPHP\Core\ZConfig;

class Str extends Base
{
    public function display()
    {
        if(Request::isHttp()) {
            Response::header("Content-Type", "text/plain; charset=utf-8");
        }

        if (\is_string($this->model)) {
            $data =  $this->model;
        } else {
            $data =  json_encode($this->model);
        }
        if(Request::isLongServer()) {
            return $data;
        }

        echo $data;
        return null;
    }
}