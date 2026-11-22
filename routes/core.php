<?php

declare(strict_types=1);

use CoreAdapter\Laravel\Http\CoreWebhookController;
use CoreAdapter\Laravel\Http\ListTenantsController;
use CoreAdapter\Laravel\Http\ShowTenantSchemaController;
use CoreAdapter\Laravel\Middleware\VerifyCoreReadSignature;
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

/*
|--------------------------------------------------------------------------
| Les lectures que le Core vient chercher
|--------------------------------------------------------------------------
|
| Le Core est le seul écrivain de son état commercial, mais il sait **lire**
| une application cliente : c'est ainsi que l'opérateur, depuis la console du
| Core, récupère la forme que l'application veut et les clients qu'elle avait
| déjà (ADR-0133).
|
| Ces deux routes sont signées comme les événements — même secret, même
| schéma : `X-Signature = HMAC-SHA256(timestamp + "." + corps)`. Le secret
| est celui du point d'entrée, que les deux parties ont déjà : rien de
| nouveau à copier.
|
| `GET /core/tenant-schema` — la forme que l'application veut.
| `POST /core/tenants` — les locataires qu'elle a déjà, paginés. La page est
| demandée dans le **corps** : c'est le corps qui est signé.
|
*/

Route::get('/core/tenant-schema', ShowTenantSchemaController::class)
    ->middleware(VerifyCoreReadSignature::class)
    ->name('core-adapter.core.tenant-schema');

Route::post('/core/tenants', ListTenantsController::class)
    ->middleware(VerifyCoreReadSignature::class)
    ->name('core-adapter.core.tenants');
