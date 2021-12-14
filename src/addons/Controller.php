<?php
declare (strict_types = 1);

namespace think\addons;

use app\common\library\Auth;
use think\App;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\facade\Config;
use think\facade\Event;
use think\facade\Lang;
use think\facade\Route;
use think\facade\Validate;
use think\facade\View;
use think\Model;
use think\Response;

/**
 * 插件控制器基础类
 */
abstract class Controller
{
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    /**
     * 视图实例
     * @var \think\View
     */
    protected $view;

    /**
     * 是否批量验证
     * @var bool
     */
    protected $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    protected $middleware = [];

    /**
     * 构造方法
     * @access public
     * @param  App  $app  应用对象
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $this->app->request;
        $this->view = $this->app->view;

        //移除HTML标签
        $this->request->filter('trim,strip_tags,htmlspecialchars');

        $filter = 'strtolower';
        // 处理路由参数

        $param = $this->request->param();
        $dispatch = Route::dispatch($this->request);

        $var = isset($dispatch['var']) ? $dispatch['var'] : [];
        $var = array_merge($param, $var);
        if (isset($dispatch['method']) && substr($dispatch['method'][0], 0, 7) == "\\addons") {
            $arr = explode("\\", $dispatch['method'][0]);
            $addon = strtolower($arr[2]);
            $controller = strtolower(end($arr));
            $action = $dispatch['method'][1];
        } else {
            $addon = isset($var['addon']) ? $var['addon'] : '';
            $controller = isset($var['controller']) ? $var['controller'] : '';
            $action = isset($var['action']) ? $var['action'] : '';
        }

        $this->addon = $addon ? call_user_func($filter, $addon) : '';
        $this->controller = $controller ? call_user_func($filter, $controller) : 'index';
        $this->action = $action ? call_user_func($filter, $action) : 'index';

        // 重置配置
        Config::set('view.view_path', ADDON_PATH . $this->addon . DIRECTORY_SEPARATOR . 'view' . DIRECTORY_SEPARATOR);

        // 检测IP是否允许
        if (function_exists("check_ip_allowed")) {
            check_ip_allowed();
        }

        // 渲染配置到视图中
        $config = get_addon_config($this->addon);
        $this->view->assign("config", $config);

        // 加载系统语言包
        Lang::load([
            ADDON_PATH . $this->addon . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . Lang::getLangset() . 'php',
        ]);

        // 设置替换字符串
        $cdnurl = Config::get('site.cdnurl');
        $this->view->replace('__ADDON__', $cdnurl . "/assets/addons/" . $this->addon);

        $this->auth = Auth::instance();
        // token
        $token = $this->request->server('HTTP_TOKEN', $this->request->request('token', \think\Cookie::get('token')));

        $path = 'addons/' . $this->addon . '/' . str_replace('.', '/', $this->controller) . '/' . $this->action;
        // 设置当前请求的URI
        $this->auth->setRequestUri($path);
        // 检测是否需要验证登录
        if (!$this->auth->match($this->noNeedLogin)) {
            //初始化
            $this->auth->init($token);
            //检测是否登录
            if (!$this->auth->isLogin()) {
                $this->error(__('Please login first'), 'index/user/login');
            }
            // 判断是否需要验证权限
            if (!$this->auth->match($this->noNeedRight)) {
                // 判断控制器和方法判断是否有对应权限
                if (!$this->auth->check($path)) {
                    $this->error(__('You have no permission'));
                }
            }
        } else {
            // 如果有传递token才验证是否登录状态
            if ($token) {
                $this->auth->init($token);
            }
        }

        // 如果有使用模板布局
        if ($this->layout) {
            View::engine()->layout('layout/' . $this->layout);
        }

        $this->view->assign('user', $this->auth->getUser());

        $site = Config::get("site");

        $upload = \app\common\model\Config::upload();

        // 上传信息配置后
        Event::listen("upload_config_init", $upload);
        Config::set(array_merge(Config::get('upload'), $upload), 'upload');

        // 加载当前控制器语言包
        $this->assign('site', $site);

        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {}

    /**
     * 验证数据
     * @access protected
     * @param  array        $data     数据
     * @param  string|array $validate 验证器名或者验证规则数组
     * @param  array        $message  提示信息
     * @param  bool         $batch    是否批量验证
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate(array $data, $validate, array $message = [], bool $batch = false)
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                [$validate, $scene] = explode('.', $validate);
            }
            $class = false !== strpos($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);
            $v = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message);

        // 是否批量验证
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        return $v->failException(true)->check($data);
    }

    /**
     * 加载模板输出
     * @access protected
     * @param  string $template 模板文件名
     * @param  array  $vars     模板输出变量
     * @param  int      $code     状态码
     * @param  callable $filter   内容过滤
     * @return mixed
     */
    protected function fetch(string $template = '', $vars = [], $code = 200, $filter = null)
    {
        return Response::create($template, 'view', $code)->assign($vars)->filter($filter);
    }

    /**
     * 渲染内容输出
     * @access protected
     * @param  string $content 模板内容
     * @param  array  $vars    模板输出变量
     * @param  int      $code    状态码
     * @param  callable $filter  内容过滤
     * @return mixed
     */
    protected function display(string $content, $vars = [], $code = 200, $filter = null)
    {
        return Response::create($content, 'view', $code)->isContent(true)->assign($vars)->filter($filter);
    }

    /**
     * 模板变量赋值
     * @access protected
     * @param  mixed $name  要显示的模板变量
     * @param  mixed $value 变量的值
     * @return $this
     */
    protected function assign($name, $value = '')
    {
        $this->app['view']->assign($name, $value);

        return $this;
    }

    /**
     * 操作成功跳转的快捷方法
     * @access protected
     * @param  mixed $msg 提示信息
     * @param  string $url 跳转的URL地址
     * @param  mixed $data 返回的数据
     * @param  integer $wait 跳转等待时间
     * @param  array $header 发送的Header信息
     * @return void
     */
    protected function success($msg = '', string $url = null, $data = '', int $wait = 3, array $header = [])
    {
        if (is_null($url) && isset($_SERVER["HTTP_REFERER"])) {
            $url = $_SERVER["HTTP_REFERER"];
        } elseif ($url) {
            $url = (strpos($url, '://') || 0 === strpos($url, '/')) ? $url : (string) $this->app->route->buildUrl($url);
        }

        $result = [
            'code' => 1,
            'msg' => $msg,
            'data' => $data,
            'url' => $url,
            'wait' => $wait,
        ];

        $type = $this->getResponseType();
        // 把跳转模板的渲染下沉，这样在 response_send 行为里通过getData()获得的数据是一致性的格式
        if ('html' == strtolower($type)) {
            $type = 'view';
            $response = Response::create($this->app->config->get('app.dispatch_success_tmpl'), $type)->assign($result)->header($header);
        } else {
            $response = Response::create($result, $type)->header($header);
        }

        throw new HttpResponseException($response);
    }

    /**
     * 操作错误跳转的快捷方法
     * @access protected
     * @param  mixed $msg 提示信息
     * @param  string $url 跳转的URL地址
     * @param  mixed $data 返回的数据
     * @param  integer $wait 跳转等待时间
     * @param  array $header 发送的Header信息
     * @return void
     */
    protected function error($msg = '', string $url = null, $data = '', int $wait = 3, array $header = [])
    {
        if (is_null($url)) {
            $url = $this->request->isAjax() ? '' : 'javascript:history.back(-1);';
        } elseif ($url) {
            $url = (strpos($url, '://') || 0 === strpos($url, '/')) ? $url : (string) $this->app->route->buildUrl($url);
        }

        $result = [
            'code' => 0,
            'msg' => $msg,
            'data' => $data,
            'url' => $url,
            'wait' => $wait,
        ];

        $type = $this->getResponseType();

        if ('html' == strtolower($type)) {
            $type = 'view';
            $response = Response::create($this->app->config->get('app.dispatch_error_tmpl'), $type)->assign($result)->header($header);
        } else {
            $response = Response::create($result, $type)->header($header);
        }

        throw new HttpResponseException($response);
    }

    /**
     * 返回封装后的API数据到客户端
     * @access protected
     * @param  mixed $data 要返回的数据
     * @param  integer $code 返回的code
     * @param  mixed $msg 提示信息
     * @param  string $type 返回数据格式
     * @param  array $header 发送的Header信息
     * @return void
     */
    protected function result($data, $code = 0, $msg = '', $type = '', array $header = [])
    {
        $result = [
            'code' => $code,
            'msg' => $msg,
            'time' => time(),
            'data' => $data,
        ];

        $type = $type ?: $this->getResponseType();
        $response = Response::create($result, $type)->header($header);

        throw new HttpResponseException($response);
    }

    /**
     * URL重定向
     * @access protected
     * @param  string $url 跳转的URL表达式
     * @param  integer $code http code
     * @param  array $with 隐式传参
     * @return void
     */
    protected function redirect($url, $code = 302, $with = [])
    {
        $response = Response::create($url, 'redirect');

        $response->code($code)->with($with);

        throw new HttpResponseException($response);
    }

    /**
     * 获取当前的response 输出类型
     * @access protected
     * @return string
     */
    protected function getResponseType()
    {
        return $this->request->isJson() || $this->request->isAjax() ? 'json' : 'html';
    }

    /**
     * 刷新Token
     */
    protected function token()
    {
        $token = $this->request->param('__token__');

        //验证Token
        if (!Validate::check(['__token__' => $token], ['__token__' => 'token:__token__'])) {
            $this->error(__('Token verification error'), '', ['__token__' => $this->request->buildToken()]);
        }

        //刷新Token
        $this->request->buildToken();
    }

}
