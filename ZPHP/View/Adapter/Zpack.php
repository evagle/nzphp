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
    ZPHP\Core\ZConfig,
    ZPHP\Common\MessagePacker;;

class Zpack extends Base
{
    public function display()
    {
        $pack = new MessagePacker();
        $pack->writeString(json_encode($this->model));

        if(Request::isHttp()) {
            Response::header("Content-Type", "application/zpack; charset=utf-8");
        }

        if (Request::isLongServer()) {
            return array($this->model, $pack->getData());
        }
        echo $pack->getData();
        return null;

        

    }


}
