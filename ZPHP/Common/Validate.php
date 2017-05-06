<?php
namespace ZPHP\Common;

class Validate
{
    /**
     * IP地址
     * @param $value
     * @return bool
     */
    static function ip($value)
    {
        $arr = explode('.', $value);
        if (count($arr) != 4) {
            return false;
        }
        //第一个和第四个不能为0或255
        if (($arr[0] < 1 or $arr[0] > 254) or ($arr[3] < 1 or $arr[3] > 254)) {
            return false;
        }
        //中间2个可以为0,但不能超过254
        if (($arr[1] < 0 or $arr[1] > 254) or ($arr[2] < 0 or $arr[2] > 254)) {
            return false;
        }
        return true;
    }

}
