<?php

use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetInstallerSessionKey;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(SetInstallerSessionKey::class);
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($exception instanceof AuthenticationException || $exception instanceof ValidationException) {
                return null;
            }

            if ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();

                return $request->expectsJson()
                    ? response()->json(['message' => 'The request could not be completed.'], $status)
                    : response()->view('errors.server-error', [], $status);
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'The request could not be completed.'], 500);
            }

            return response()->view('errors.server-error', [], 500);
        });
    })->create();
