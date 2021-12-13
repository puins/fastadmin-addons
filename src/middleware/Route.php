<?php
declare (strict_types = 1);

namespace think\middleware;

/**
 * 插件路由中间件
 *
 * @author JuMeng <hnjumneg@gmail.com>
 */
class Route
{
    /**
     * 插件中间件
     * @param $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        return $next($request);
    }
}
