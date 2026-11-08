<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*')) {
                return null;
            }

            return route('login');
        });

        $middleware->api(prepend: [
            HandleCors::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json(['message' => 'Tu sesión ha expirado. Inicia sesión nuevamente.'], 401);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $hasDefaultMessage = in_array($e->getMessage(), ['', 'This action is unauthorized.'], true);

            $message = match ($e->getStatusCode()) {
                403 => $hasDefaultMessage ? 'No tienes permiso para realizar esta acción.' : null,
                404 => 'El registro solicitado no existe o fue eliminado.',
                405 => 'Esta acción no está permitida.',
                429 => 'Demasiadas solicitudes. Espera un momento e intenta de nuevo.',
                default => null,
            };

            return $message === null ? null : response()->json(['message' => $message], $e->getStatusCode(), $e->getHeaders());
        });
    })->create();
