<?php
declare (strict_types = 1);

namespace think\service;

use think\facade\App;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Event;
use think\facade\Route;
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

        // 定义路由
        Route::any('addons/:addon/[:controller]/[:action]', "\\think\\addons\\Route@execute");

        // 如果插件目录不存在则创建
        if (!is_dir(ADDON_PATH)) {
            @mkdir(ADDON_PATH, 0755, true);
        }

        // 监听addon_init
        Event::listen('addon_init');

        // 闭包自动识别插件目录配置
        Event::trigger('AppInit', function () {
            // 获取开关
            $autoload = (bool) Config::get('addons.autoload', false);
            // 非正是返回
            if (!$autoload) {
                return;
            }
            // 当debug时不缓存配置
            $config = App::is_debug() ? [] : Cache::get('addons', []);
            if (empty($config)) {
                $config = get_addon_autoload_config();
                Cache::set('addons', $config);
            }
        });

        // 闭包初始化行为
        Event::trigger('AppInit', function () {
            //注册路由
            $routeArr = (array) Config::get('addons.route');
            $domains = [];
            $rules = [];
            $execute = "\\think\\addons\\Route@execute?addon=%s&controller=%s&action=%s";
            foreach ($routeArr as $k => $v) {
                if (is_array($v)) {
                    $addon = $v['addon'];
                    $domain = $v['domain'];
                    $drules = [];
                    foreach ($v['rule'] as $m => $n) {
                        list($addon, $controller, $action) = explode('/', $n);
                        $drules[$m] = sprintf($execute . '&indomain=1', $addon, $controller, $action);
                    }
                    //$domains[$domain] = $drules ? $drules : "\\addons\\{$k}\\controller";
                    $domains[$domain] = $drules ? $drules : [];
                    $domains[$domain][':controller/[:action]'] = sprintf($execute . '&indomain=1', $addon, ":controller", ":action");
                } else {
                    if (!$v) {
                        continue;
                    }
                    list($addon, $controller, $action) = explode('/', $v);
                    $rules[$k] = sprintf($execute, $addon, $controller, $action);
                }
            }
            Route::rule($rules);
            if ($domains) {
                Route::domain($domains);
            }

            // 获取系统配置
            $hooks = App::is_debug() ? [] : Cache::get('hooks', []);
            if (empty($hooks)) {
                $hooks = (array) Config::get('addons.hooks');
                // 初始化钩子
                foreach ($hooks as $key => $values) {
                    if (is_string($values)) {
                        $values = explode(',', $values);
                    } else {
                        $values = (array) $values;
                    }
                    $hooks[$key] = array_filter(array_map('get_addon_class', $values));
                }
                Cache::set('hooks', $hooks);
            }
            //如果在插件中有定义AppInit，则直接执行
            if (isset($hooks['AppInit'])) {
                foreach ($hooks['AppInit'] as $k => $v) {
                    Event::trigger('AppInit', $v);
                }
            }
            Event::listenEvents($hooks);
        });
    }
}
