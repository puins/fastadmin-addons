<?php
declare (strict_types = 1);

namespace think\service;

use think\Service;

class AddonsService extends Service
{
    /**
     * 注册服务
     *
     * @return mixed
     */
    public function register()
    {
        
    }

    /**
     * 执行服务
     *
     * @return mixed
     */
    public function boot()
    {
        // 插件目录
        define('ADDON_PATH', root_path() . 'addons' . DIRECTORY_SEPARATOR);
    }
}
