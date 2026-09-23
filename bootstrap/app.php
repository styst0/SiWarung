<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Percayai header X-Forwarded-* dari proxy lokal (mis. cloudflared/ngrok
        // saat --tunnel dipakai) agar URL yang dibuat aplikasi (login, redirect,
        // aset) memakai https:// yang benar, bukan http:// yang bisa diblokir
        // browser sebagai mixed content. Hanya proxy di komputer yang sama
        // (127.0.0.1) yang dipercaya, jadi ini aman dipakai selalu.
        $middleware->trustProxies(at: '127.0.0.1');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
