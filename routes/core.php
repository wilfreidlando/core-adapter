<?php

declare(strict_types=1);

use CoreAdapter\Laravel\Http\CoreWebhookController;
use CoreAdapter\Laravel\Middleware\VerifyCoreSignature;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Les routes du paquet client du Core
|--------------------------------------------------------------------------
|
| Le point d'entrée des événements du Core. Il est **hors** du groupe `web` :
| pas de CSRF, pas de session — la sécurité, c'est la signature.
|
| À inclure depuis `bootstrap/app.php` (Laravel 11 et plus) :
|
|     ->withRouting(then: function () {
|         Route::middleware('api')->group(
|             base_path('vendor/wilfreidlando/core-adapter/routes/core.php')
|         );
|     })
|
| ou, dans `routes/api.php` (Laravel 10 et avant) :
|
|     require base_path('vendor/wilfreidlando/core-adapter/routes/core.php');
|
*/

Route::post('/webhooks/core', CoreWebhookController::class)
    ->middleware(VerifyCoreSignature::class)
    ->name('core-adapter.webhooks.core');
