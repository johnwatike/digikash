<?php

use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\StripeController;
use App\Http\Controllers\Api\V2\PaymentIntentController;
use App\Http\Controllers\Api\SubMerchantController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('merchant.auth')->group(function () {
    Route::group(['prefix' => 'v1'], function () {

        Route::post('initiate-payment', [PaymentController::class, 'initiatePayment'])
            ->middleware('idempotency');

        Route::get('verify-payment/{trxId}', [PaymentController::class, 'verifyPayment']);

        Route::get('site-info', [PaymentController::class, 'siteInfo']);

        Route::post('test_webhooks', [WebhookController::class, 'test']);

        Route::get('webhooks/endpoints', [WebhookController::class, 'index']);
        Route::post('webhooks/endpoints', [WebhookController::class, 'store']);
        Route::get('webhooks/deliveries', [WebhookController::class, 'deliveries']);
        Route::post('webhooks/deliveries/{deliveryId}/replay', [WebhookController::class, 'replay']);

        Route::post('sub-merchants', [SubMerchantController::class, 'store']);
        Route::get('sub-merchants/{subMerchantId}/kyc-status', [SubMerchantController::class, 'kycStatus']);
    });

    Route::group(['prefix' => 'v2', 'middleware' => 'idempotency'], function () {
        Route::post('payment-intents', [PaymentIntentController::class, 'store']);
        Route::get('payment-intents/{piId}', [PaymentIntentController::class, 'show']);
        Route::post('payment-intents/{piId}/cancel', [PaymentIntentController::class, 'cancel']);
    });
});

Route::middleware('auth:sanctum')
    ->post('/stripe/issuing/ephemeral-key', [StripeController::class, 'createEphemeralKey'])->name('stripe.issuing.ephemeral-key');
