<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\middleware;

use Closure;
use think\App;
use think\exception\HttpException;
use think\Request;
use think\Response;

/**
 * 插件支持
 */
class Addons
{

    /** @var App */
    protected $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * 多插件解析
     * @access public
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle($request, Closure $next)
    {
        if (!$this->parseMultiAddon()) {
            return $next($request);
        }

        return $this->app->middleware->pipeline('app')
            ->send($request)
            ->then(function ($request) use ($next) {
                return $next($request);
            });
    }

    /**
     * 获取路由目录
     * @access protected
     * @return string
     */
    protected function getRoutePath(): string
    {
        return $this->app->getAppPath() . 'route' . DIRECTORY_SEPARATOR;
    }

    /**
     * 解析多插件
     * @return bool
     */
    protected function parseMultiAddon(): bool
    {
        // 自动多插件识别
        $this->app->http->setBind(false);
        $addonName = null;

        $bind = $this->app->config->get('addons.domain_bind', []);

        if (!empty($bind)) {
            // 获取当前子域名
            $subDomain = $this->app->request->subDomain();
            $domain = $this->app->request->host(true);

            if (isset($bind[$domain])) {
                $addonName = $bind[$domain];
            } elseif (isset($bind[$subDomain])) {
                $addonName = $bind[$subDomain];
            } elseif (isset($bind['*'])) {
                $addonName = $bind['*'];
            }

            if (!$this->getInfo($addonName)['state']) {
                throw new HttpException(404, 'addon is disabled:' . $addonName);
            }
            $this->app->http->setBind();
        }

        if (!$this->app->http->isBind()) {
            $path = $this->app->request->url();
            $path = ltrim($path, '/');
            $name = current(explode('/', $path));

            if (strpos($name, '.')) {
                $name = strstr($name, '.', true);
            }

            if ($name) {
                $addonName = $name;

                if (!$this->getInfo($addonName)['state']) {
                    throw new HttpException(404, 'addon is disabled:' . $addonName);
                }
                $this->app->request->setRoot('/' . $name);
                $this->app->request->setPathinfo(strpos($path, '/') ? ltrim(strstr($path, '/'), '/') : '');
            }
        }

        $this->setApp($addonName);
        return true;
    }

    /**
     * 设置插件
     * @param string $addonName
     */
    protected function setApp(string $addonName): void
    {
        $this->app->http->name($addonName);

        $addonPath = $this->getAddonsPath() . $addonName . DIRECTORY_SEPARATOR;

        $this->app->setAppPath($addonPath);
        // 设置插件命名空间
        $this->app->setNamespace($this->app->config->get('addons.addon_namespace') ?: 'addons\\' . $addonName);

        if (is_dir($addonPath)) {
            $this->app->setRuntimePath($this->app->getRuntimePath() . $addonName . DIRECTORY_SEPARATOR);
            $this->app->http->setRoutePath($this->getRoutePath());

            //加载插件
            $this->loadAddon($addonName, $addonPath);
        }
    }

    /**
     * 加载插件文件
     * @param string $addonName 插件名
     * @return void
     */
    protected function loadAddon(string $addonName, string $addonPath): void
    {
        if (is_file($addonPath . 'common.php')) {
            include_once $addonPath . 'common.php';
        }

        $files = [];

        $files = array_merge($files, glob($addonPath . 'config' . DIRECTORY_SEPARATOR . '*' . $this->app->getConfigExt()));

        foreach ($files as $file) {
            $this->app->config->load($file, pathinfo($file, PATHINFO_FILENAME));
        }

        if (is_file($addonPath . 'event.php')) {
            $this->app->loadEvent(include $addonPath . 'event.php');
        }

        if (is_file($addonPath . 'middleware.php')) {
            $this->app->middleware->import(include $addonPath . 'middleware.php', 'app');
        }

        // 加载插件默认语言包
        $this->app->loadLangPack($this->app->lang->defaultLangSet());
    }

    /**
     * 获取插件基础目录
     * @access protected
     * @return string
     */
    protected function getAddonsPath(): string
    {
        return $this->app->getRootPath() . 'addons' . DIRECTORY_SEPARATOR;
    }

    /**
     * 获取插件基础配置信息
     *
     * @param string $addonName
     * @return array
     */
    protected function getInfo(string $addonName)
    {
        $infoFile = $this->getAddonsPath() . $addonName . DIRECTORY_SEPARATOR . 'info.ini';
        if (is_file($infoFile)) {
            $info = parse_ini_file($infoFile, true, INI_SCANNER_TYPED) ?: [];
            return $info;
        } else {
            return false;
        }
    }

}
