<?php

namespace Task4ItAPI\Http\Middleware;

use Closure;

class FreelancerMiddleware
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
            
        if (!$user->professional) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Must be a professional to access this endpoint');
        }

        return $next($request);
    }
}
