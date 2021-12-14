<?php
declare (strict_types = 1);

namespace think\addons;

use think\exception\HttpException;
use think\facade\Event;
use think\facade\Route as Router;

/**
 * 插件执行默认控制器
 * @package think\addons
 */
class Route
{

    /**
     * 插件执行
     */
    public function execute($addon = null, $controller = null, $action = null)
    {
        $request = request();

        $addon = $addon ? trim(call_user_func('strtolower', $addon)) : '';
        $controller = $controller ? trim(call_user_func('strtolower', $controller)) : 'index';
        $action = $action ? trim(call_user_func('strtolower', $action)) : 'index';

        Event::listen('addon_begin', $request);
        if (!empty($addon) && !empty($controller) && !empty($action)) {
            $info = get_addon_info($addon);
            if (!$info) {
                throw new HttpException(404, __('addon %s not found', $addon));
            }
            if (!$info['state']) {
                throw new HttpException(500, __('addon %s is disabled', $addon));
            }
            $dispatch = Router::dispatch($request);

            if (isset($dispatch['var']) && $dispatch['var']) {
                $request->route(array_diff_key($dispatch['var'], array_flip(['addon', 'controller', 'action'])));
            }

            // 设置当前请求的控制器、操作
            $request->setController($controller)->setAction($action);

            // 监听addon_module_init
            Event::listen('addon_module_init', $request);
            // 兼容旧版本行为,即将移除,不建议使用
            Event::listen('addons_init', $request);

            $class = get_addon_class($addon, 'controller', $controller);
            if (!$class) {
                throw new HttpException(404, __('addon controller %s not found', parse_name($controller, 1)));
            }

            $instance = new $class($request);

            $vars = [];
            if (is_callable([$instance, $action])) {
                // 执行操作方法
                $call = [$instance, $action];
            } elseif (is_callable([$instance, '__call'])) {
                // 空操作
                $call = [$instance, '__call'];
                $vars = [$action];
            } else {
                // 操作不存在
                throw new HttpException(404, __('addon action %s not found', get_class($instance) . '->' . $action . '()'));
            }

            Event::listen('addon_action_begin', $call);

            return call_user_func_array($call, $vars);
        } else {
            abort(500, lang('addon can not be empty'));
        }
    }

}
