<?php

use App\Domain\Market\MarketServiceProvider;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        api: __DIR__. '/../routes/api.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
        __DIR__.'/../app/Domain/Asset/Application/Commands',
        __DIR__.'/../app/Domain/Market/Application/Commands',

    ])
    ->withProviders([
        App\Domain\Market\MarketServiceProvider::class,
        App\Domain\Asset\AssetServiceProvider::class,

    ])
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withEvents(discover:[
            __DIR__.'/../app/Domain',
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        //
        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {

            return response()->json([
                'success' => false,
                'message' => 'Not Found.',
            ], 404);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Method Not Allowed.',
            ], 405);
        });
    })->create();
