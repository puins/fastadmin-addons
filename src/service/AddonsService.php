<?php
declare (strict_types = 1);

namespace think\service;

use think\Addons;
use think\middleware\Route;
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
        //插件路由中间件
        $this->app->event->listen('HttpRun', function () {
            $this->app->middleware->add(Route::class);
        });

        //addons 类库标识绑定
        $this->app->bind([
            'addons' => Addons::class,
        ]);
    }

}
