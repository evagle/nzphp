<?php
/**
 * author: shenzhe
 * Date: 13-6-17
 * 公用方法类
 */

namespace ZPHP\Common;

class Utils
{
    public static function isAjax()
    {
        if (!empty($_REQUEST['ajax'])
            || !empty($_REQUEST['jsoncallback'])
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
                && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
        ) {
            return true;
        }
        return false;
    }

    public static function uniqueArray(array &$arr)
    {
        $map = array();
        foreach ($arr as $k => $v) {
            if (is_object($v)) {
                $hash = spl_object_hash($v);
            } elseif (is_resource($v)) {
                $hash = intval($v);
            } else {
                $hash = $v;
            }
            if (isset($map[$hash])) {
                unset($arr[$k]);
            } else {
                $map[$hash] = true;
            }
        }
    }
}
