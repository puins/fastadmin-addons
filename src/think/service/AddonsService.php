<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2021 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\service;

use think\facade\Config;
use think\middleware\Addons;
use think\Service;

/**
 * 插件服务类
 */
class AddonsService extends Service
{
    //插件基础目录
    protected $addonsPath;

    public function register()
    {
        $priority = $this->app->config->get('middleware.priority', []);
        $priority = array_merge($priority, [
            \think\app\MultiApp::class,
            \think\middleware\Addons::class,
        ]);
        Config::set([
            'priority' => $priority,
        ], 'middleware');

        // 加载系统语言包
        $this->app->lang->load($this->app->getRootPath() . '/vendor/puins/think-multi-addon/src/lang/zh-cn.php');

        $this->addonsPath = $this->app->getRootPath() . 'addons' . DIRECTORY_SEPARATOR;
    }

    public function boot()
    {
        // 如果插件目录不存在则创建
        if (!is_dir($this->addonsPath)) {
            @mkdir($this->addonsPath, 0755, true);
            @chown($this->addonsPath, 'www');
        }

        if ($this->check()) {
            $this->app->event->listen('HttpRun', function () {
                $this->app->middleware->add(Addons::class);
            });
        }

        $this->commands(['addon' => 'think\console\command\Addon']);

    }

    /**
     * 多插件判定
     * 1 先判断是否域名绑定，先应用后插件
     *
     * @return bool
     */
    private function check()
    {
        if ($this->app->runningInConsole()) {
            return false;
        }

        $subDomain = $this->app->request->subDomain(); //当前子域名
        $domain = $this->app->request->host(true); //当前主域名

        $app_bind = $this->app->config->get('app.domain_bind', []); //应用域名绑定

        if (!empty($app_bind)) {
            if (isset($app_bind[$domain]) || isset($app_bind[$subDomain]) || isset($app_bind['*'])) {
                return false;
            }
        }

        $addons_bind = $this->app->config->get('addons.domain_bind', []); //插件域名绑定

        if (!empty($addons_bind)) {
            $addonName = null;
            if (isset($addons_bind[$domain])) {
                $addonName = $addons_bind[$domain];
            } elseif (isset($addons_bind[$subDomain])) {
                $addonName = $addons_bind[$subDomain];
                return true;
            } elseif (isset($addons_bind['*'])) {
                $addonName = $addons_bind['*'];
            }

            $this->app->http->name($addonName);
            return true;
        }

        $path = $this->app->request->url();

        $path = ltrim($path, '/');
        $name = current(explode('/', $path));

        if (strpos($name, '.')) {
            $name = strstr($name, '.', true);
        }

        //插件目录
        $addonPath = $this->addonsPath . $name . DIRECTORY_SEPARATOR;

        $appPath = $this->app->getBasePath() . $name . DIRECTORY_SEPARATOR;

        if (!empty($name) && is_dir($addonPath) && !is_dir($appPath)) {
            return true;
        }

        return false;

    }

}
