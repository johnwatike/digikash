<?php

namespace App\Providers;

use App\Integrations\Fraud\FraudProviderWebhookHandler;
use App\Integrations\InboundWebhookHandler;
use App\Integrations\Kyc\KycVendorWebhookHandler;
use App\Models\IntegrationHandler;
use Illuminate\Support\ServiceProvider;

class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([
            FraudProviderWebhookHandler::class,
            KycVendorWebhookHandler::class,
        ], 'integration.webhooks');

        $this->app->singleton('integration.registry', function ($app) {
            $handlers = [];

            foreach ($app->tagged('integration.webhooks') as $handler) {
                if ($handler instanceof InboundWebhookHandler) {
                    $handlers[$handler->code()] = $handler;
                }
            }

            return $handlers;
        });
    }

    public function boot(): void
    {
        if ($this->app->environment('testing')) {
            return;
        }

        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('integration_handlers')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $defaults = [
            ['code' => 'fraud_provider', 'name' => 'Fraud Provider', 'type' => 'fraud'],
            ['code' => 'kyc_vendor', 'name' => 'KYC Vendor', 'type' => 'kyc'],
            ['code' => 'bnpl_klarna', 'name' => 'BNPL Klarna', 'type' => 'bnpl'],
        ];

        foreach ($defaults as $row) {
            IntegrationHandler::query()->firstOrCreate(
                ['code' => $row['code']],
                ['name' => $row['name'], 'type' => $row['type'], 'is_enabled' => false]
            );
        }
    }
}
