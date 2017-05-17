<?php

namespace Task4ItAPI\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * @var array
     */
    protected $middleware = [
        \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
        \Task4ItAPI\Http\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        // \Task4ItAPI\Http\Middleware\VerifyCsrfToken::class,
    ];

    /**
     * The application's route middleware.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => \Task4ItAPI\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'guest' => \Task4ItAPI\Http\Middleware\RedirectIfAuthenticated::class,
        'freelancer' => \Task4ItAPI\Http\Middleware\FreelancerMiddleware::class,
        'user' => \Task4ItAPI\Http\Middleware\UserMiddleware::class,
        'admin' => \Task4ItAPI\Http\Middleware\AdminMiddleware::class,
    ];
}
