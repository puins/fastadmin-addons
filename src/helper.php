<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2021 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

//------------------------
// 插件助手函数
//-------------------------

use think\App;
use think\facade\Route;
use think\route\AddonUrl as UrlBuild;

if (!function_exists('addon_url')) {
    /**
     * 插件Url生成
     * @param string      $url    路由地址
     * @param array       $vars   变量
     * @param bool|string $suffix 生成的URL后缀
     * @param bool|string $domain 域名
     * @return UrlBuild
     */
    function addon_url(string $url = '', array $vars = [], $suffix = true, $domain = false): UrlBuild
    {
        return app()->make(UrlBuild::class, [app('route'), app(), $url, $vars], true)->suffix($suffix)->domain($domain);
    }
}

if (!function_exists('addons_path')) {
    /**
     * 获取插件根目录
     *
     * @return string
     */
    function addons_path()
    {
        return app()->getRootPath() . 'addons' . DIRECTORY_SEPARATOR;
    }
}
