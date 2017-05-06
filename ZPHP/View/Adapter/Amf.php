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

class Amf extends Base
{
    public function display()
    {
        if (Request::isHttp()) {
            Response::header('Content-Type', 'application/amf; charset=utf-8');
        }
        $data =  \amf3_encode($this->model);
        if(Request::isLongServer()) {
        	return $data;
        }
        echo $data;
        return null;
        
    }
}