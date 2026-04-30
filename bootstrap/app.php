<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([
            \Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class,
        ]);

        $exceptions->reportable(function (\TypeError $e) {
            $file = $e->getFile();
            if (str_contains($file, '/vendor/livewire/') || str_contains($file, '/vendor/filament/')) {
                return false;
            }
        });
    })->create();
