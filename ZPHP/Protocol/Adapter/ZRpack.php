<?php
/**
 * User: shenzhe
 * Date: 13-6-17
 */


namespace ZPHP\Protocol\Adapter;
use ZPHP\Core\ZConfig;
use ZPHP\Common\MessagePacker;
use ZPHP\Protocol\IProtocol;
use ZPHP\Cache\ZCache as ZCache;
use ZPHP\Common\Route as ZRoute;
use ZPHP\Protocol\Request;

class ZRpack implements IProtocol
{
    private $_cache;

    /**
     * 包格式： 包总长+命令id+请求id+数据
     * 
     * @param $_data
     * @return bool
     */
    public function parse($_data)
    {
        $ctrlName = ZConfig::get( 'default_ctrl_name', 'main\\main');
        $methodName = ZConfig::get( 'default_method_name', 'main');
        if (empty($this->_cache)) {
            $this->_cache = ZCache::getInstance('Php');
        }
        $fd = Request::getFd();
        $cacheData = $this->_cache->get($fd);
        if (!empty($cacheData)) {
            $_data = $cacheData . $_data;
            $this->_cache->delete($fd);
        }
        if (empty($_data)) {
            return false;
        }
        $packData = new MessagePacker($_data);
        $packLen = $packData->readInt();
        $dataLen = \strlen($_data);
        if ($packLen > $dataLen) {
            $this->_cache->set($fd, $_data);
            return false;
        } elseif ($packLen < $dataLen) {
            $this->_cache->set($fd,  \substr($_data, $packLen, $dataLen - $packLen));
        }
        $packData->resetOffset(4);
        $data = [];
        $data['_cmd'] = $packData->readInt();
        $pathinfo = ZConfig::getField('cmdlist', $data['_cmd']);
        $data['_rid'] = $packData->readInt();
        $params = $packData->readString();
        $unpackData = \json_decode(gzdecode($params), true);
        if(!empty($unpackData) && \is_array($unpackData)) {
            $data += $unpackData;
        }
        $routeMap = ZRoute::match(ZConfig::get('route', false), $pathinfo);
        if(is_array($routeMap)) {
            $ctrlName = $routeMap[0];
            $methodName = $routeMap[1];
            if(!empty($routeMap[2]) && is_array($routeMap[2])) {
                //参数优先
                $data = $data + $routeMap[2];
            }
        }
        Request::init($ctrlName, $methodName, $data, ZConfig::get( 'view_mode', 'Zpack'));
        return true;
    }
}