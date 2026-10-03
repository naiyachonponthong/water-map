<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'idle' => \App\Http\Middleware\IdleTimeout::class,
            '2fa' => \App\Http\Middleware\RequireTwoFactor::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SetCurrentProvince::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // LINE เรียก webhook โดยตรง ตรวจลายเซ็นแทน CSRF
        $middleware->validateCsrfTokens(except: ['line/webhook/*']);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
