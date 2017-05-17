<?php

namespace Task4ItAPI\Http\Middleware;

use Closure;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $user = app('Dingo\Api\Auth\Auth')->user();

        if (!$user->is_admin) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Must be an admin user to access this endpoint');
        }

        return $next($request);
    }
}
