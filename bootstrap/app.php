<?php

use App\Http\Controllers\PartyFinderController;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 個別に除外した日記IDの Cookie は画面の JS が書くので暗号化しない
        $middleware->encryptCookies(except: [PartyFinderController::HIDDEN_COOKIE]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
