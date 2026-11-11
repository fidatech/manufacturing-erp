<?php

use App\Exceptions\UserError;
use App\Http\Middleware\ConvertShamsiDates;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
        $middleware->web(append: [ConvertShamsiDates::class, EnsureUserIsActive::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A problem the user can fix: go back to the form and show the message.
        $exceptions->render(function (UserError $e, Request $request) {
            return back()->withInput()->with('danger', $e->getMessage());
        });
        $exceptions->dontReport(UserError::class);
    })->create();
