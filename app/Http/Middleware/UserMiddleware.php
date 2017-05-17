<?php

namespace Task4ItAPI\Http\Middleware;

use Dingo\Api\Routing\Helpers;

use Closure;

class UserMiddleware
{

    use Helpers;

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

        if ($user->professional) {
            return $this->response->errorForbidden('Must be a non-freelancer user to access this endpoint');
        }

        return $next($request);
    }
}
