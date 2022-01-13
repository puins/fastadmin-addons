<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2015 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\console\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

class Addon extends Command
{
    /**
     * 插件基础目录
     * @var string
     */
    protected $basePath;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('addon')
            ->addArgument('name', Argument::OPTIONAL, 'addon name .')
            ->setDescription('Build Addon Dirs');
    }

    protected function execute(Input $input, Output $output)
    {
        $this->basePath = $this->app->getRootPath() . 'addons' . DIRECTORY_SEPARATOR;
        $addon = $input->getArgument('name') ?: '';

        $list = [
            '__dir__' => ['controller', 'model', 'view'],
        ];

        $this->createAddon($addon, $list);
        $output->writeln("<info>Successed</info>");

    }

    /**
     * 创建插件
     * @access protected
     * @param  string $addon  插件名
     * @param  array  $list 目录结构
     * @return void
     */
    protected function createAddon(string $addon, array $list = []): void
    {
        if (!is_dir($this->basePath . $addon)) {
            // 创建插件目录
            mkdir($this->basePath . $addon, 0755, true);
            @chown($this->basePath . $addon, 'www');
        }

        $addonPath = $this->basePath . ($addon ? $addon . DIRECTORY_SEPARATOR : '');
        $namespace = 'addons' . ($addon ? '\\' . $addon : '');

        // 创建配置文件和公共文件
        $this->createCommon($addon);
        // 创建插件的默认页面
        $this->createHello($addon, $namespace);
        // 创建插件的配置文件
        $this->createIni($addon);

        foreach ($list as $path => $file) {
            if ('__dir__' == $path) {
                // 生成子目录
                foreach ($file as $dir) {
                    $this->checkDirBuild($addonPath . $dir);
                }
            } elseif ('__file__' == $path) {
                // 生成（空白）文件
                foreach ($file as $name) {
                    if (!is_file($addonPath . $name)) {
                        file_put_contents($addonPath . $name, 'php' == pathinfo($name, PATHINFO_EXTENSION) ? '<?php' . PHP_EOL : '');
                    }
                }
            } else {
                // 生成相关MVC文件
                foreach ($file as $val) {
                    $val = trim($val);
                    $filename = $addonPath . $path . DIRECTORY_SEPARATOR . $val . '.php';
                    $space = $namespace . '\\' . $path;
                    $class = $val;
                    switch ($path) {
                        case 'controller': // 控制器
                            if ($this->app->config->get('route.controller_suffix')) {
                                $filename = $addonPath . $path . DIRECTORY_SEPARATOR . $val . 'Controller.php';
                                $class = $val . 'Controller';
                            }
                            $content = "<?php" . PHP_EOL . "namespace {$space};" . PHP_EOL . PHP_EOL . "class {$class}" . PHP_EOL . "{" . PHP_EOL . PHP_EOL . "}";
                            break;
                        case 'model': // 模型
                            $content = "<?php" . PHP_EOL . "namespace {$space};" . PHP_EOL . PHP_EOL . "use think\Model;" . PHP_EOL . PHP_EOL . "class {$class} extends Model" . PHP_EOL . "{" . PHP_EOL . PHP_EOL . "}";
                            break;
                        case 'view': // 视图
                            $filename = $addonPath . $path . DIRECTORY_SEPARATOR . $val . '.html';
                            $this->checkDirBuild(dirname($filename));
                            $content = '';
                            break;
                        default:
                            // 其他文件
                            $content = "<?php" . PHP_EOL . "namespace {$space};" . PHP_EOL . PHP_EOL . "class {$class}" . PHP_EOL . "{" . PHP_EOL . PHP_EOL . "}";
                    }

                    if (!is_file($filename)) {
                        file_put_contents($filename, $content);
                    }
                }
            }
        }
    }

    /**
     * 创建插件的欢迎页面
     * @access protected
     * @param  string $addon 目录
     * @param  string $namespace 类库命名空间
     * @return void
     */
    protected function createHello(string $addon, string $namespace): void
    {
        $suffix = $this->app->config->get('route.controller_suffix') ? 'Controller' : '';
        $filename = $this->basePath . ($addon ? $addon . DIRECTORY_SEPARATOR : '') . 'controller' . DIRECTORY_SEPARATOR . 'Index' . $suffix . '.php';

        if (!is_file($filename)) {
            $content = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . 'controller.stub');
            $content = str_replace(['{%name%}', '{%app%}', '{%layer%}', '{%suffix%}'], [$addon, $namespace, 'controller', $suffix], $content);
            $this->checkDirBuild(dirname($filename));

            file_put_contents($filename, $content);
        }
    }

    /**
     * 创建插件的公共文件
     * @access protected
     * @param  string $addon 目录
     * @return void
     */
    protected function createCommon(string $addon): void
    {
        $addonPath = $this->basePath . ($addon ? $addon . DIRECTORY_SEPARATOR : '');

        if (!is_file($addonPath . 'common.php')) {
            file_put_contents($addonPath . 'common.php', "<?php" . PHP_EOL . "// 这是系统自动生成的公共文件" . PHP_EOL);
        }

        foreach (['event', 'middleware', 'common'] as $name) {
            if (!is_file($addonPath . $name . '.php')) {
                file_put_contents($addonPath . $name . '.php', "<?php" . PHP_EOL . "// 这是系统自动生成的{$name}定义文件" . PHP_EOL . "return [" . PHP_EOL . PHP_EOL . "];" . PHP_EOL);
            }
        }
    }

    /**
     * 创建插件的配置文件
     * @access protected
     * @param  string $addon 目录
     * @return void
     */
    protected function createIni(string $addon): void
    {
        $addonPath = $this->basePath . ($addon ? $addon . DIRECTORY_SEPARATOR : '');
        $filename = $addonPath . 'info.ini';

        if (!is_file($filename)) {
            $content = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . 'info.stub');
            $content = str_replace(['{%name%}'], [$addon], $content);

            file_put_contents($filename, $content);

        }
    }

    /**
     * 创建目录
     * @access protected
     * @param  string $dirname 目录名称
     * @return void
     */
    protected function checkDirBuild(string $dirname): void
    {
        if (!is_dir($dirname)) {
            mkdir($dirname, 0755, true);
            @chown($dirname, 'www');
        }
    }
}
