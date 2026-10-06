<?php

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
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => true,
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            return ApiResponse::validationError(
                $e->errors(),
                'Input tidak valid'
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return ApiResponse::unauthenticated(
                'Autentikasi diperlukan'
            );
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            return ApiResponse::notFound(
                'Resource tidak ditemukan'
            );
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return ApiResponse::notFound(
                'Resource tidak ditemukan'
            );
        });
    })->create();
