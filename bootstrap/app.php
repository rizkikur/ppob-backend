<?php

use App\Domain\Admin\Http\Middleware\AdminContentSecurityPolicy;
use App\Domain\Admin\Http\Middleware\AdminRoleMiddleware;
use App\Domain\Admin\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Domain\Shared\Http\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (Request $request) {
            $prefix = config('admin.prefix', 'office');
            if ($request->is("{$prefix}*") || $request->is('admin*')) {
                return route('admin.login');
            }

            return '/login';
        });

        $middleware->alias([
            'admin.csp' => AdminContentSecurityPolicy::class,
            'admin.role' => AdminRoleMiddleware::class,
            'admin.2fa' => EnsureTwoFactorAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            function (Request $request) {
                $prefix = config('admin.prefix', 'office');

                return $request->expectsJson() || (! $request->is("{$prefix}*") && ! $request->is('admin*') && ! $request->is('docs*') && $request->path() !== '/');
            }
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            $prefix = config('admin.prefix', 'office');
            if (($request->is("{$prefix}*") || $request->is('admin*')) && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::validationError(
                $e->errors(),
                'Input tidak valid'
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            $prefix = config('admin.prefix', 'office');
            if (($request->is("{$prefix}*") || $request->is('admin*')) && ! $request->expectsJson()) {
                return redirect()->guest(route('admin.login'));
            }

            return ApiResponse::unauthenticated(
                'Autentikasi diperlukan'
            );
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            $prefix = config('admin.prefix', 'office');
            if (($request->is("{$prefix}*") || $request->is('admin*')) && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::notFound(
                'Resource tidak ditemukan'
            );
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('admin*') && ! $request->expectsJson()) {
                return null;
            }

            return ApiResponse::notFound(
                'Resource tidak ditemukan'
            );
        });
    })->create();
