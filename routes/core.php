<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use SaasCommerce\Laravel\Http\CoreWebhookController;
use SaasCommerce\Laravel\Middleware\VerifyCoreSignature;

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
|             base_path('vendor/wilfreidlando/saas-commerce-dep/routes/core.php')
|         );
|     })
|
| ou, dans `routes/api.php` (Laravel 10 et avant) :
|
|     require base_path('vendor/wilfreidlando/saas-commerce-dep/routes/core.php');
|
*/

Route::post('/webhooks/core', CoreWebhookController::class)
    ->middleware(VerifyCoreSignature::class)
    ->name('saas-commerce.webhooks.core');
