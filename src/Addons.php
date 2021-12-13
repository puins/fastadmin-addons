<?php
declare (strict_types = 1);
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2015 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: jumeng <hnjumeng@163.com>
// +----------------------------------------------------------------------

namespace think;

abstract class Addons
{
    // app 容器
    protected $app;
    // 请求对象
    protected $request;
    // 当前插件标识
    protected $name;
    // 插件路径
    protected $addon_path;
    // 视图模型
    protected $view;
    // 插件配置
    protected $addon_config;
    // 插件信息
    protected $addon_info;

    /**
     * 插件构造函数
     * Addons constructor.
     * @param \think\App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $app->request;
        // $this->name = $this->getName();
        // $this->addon_path = $app->addons->getAddonsPath() . $this->name . DIRECTORY_SEPARATOR;
        // $this->addon_config = "addon_{$this->name}_config";
        // $this->addon_info = "addon_{$this->name}_info";

        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {}
    //必须实现安装插件方法
    abstract public function install();

    //必须实现卸载插件方法
    abstract public function uninstall();
}
