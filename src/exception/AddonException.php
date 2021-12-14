<?php
declare (strict_types = 1);

namespace think\exception;

use think\Exception;

/**
 * 插件异常处理类
 * @package think\addons
 */
class AddonException extends Exception
{

    public function __construct($message, $code = 0, $data = '')
    {
        $this->message = $message;
        $this->code = $code;
        $this->data = $data;
    }

}
