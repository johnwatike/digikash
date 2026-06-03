<?php

namespace App\Integrations\Fraud;

use App\Integrations\InboundWebhookHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FraudProviderWebhookHandler implements InboundWebhookHandler
{
    public function code(): string
    {
        return 'fraud_provider';
    }

    public function handle(Request $request): void
    {
        Log::info('Fraud provider webhook received', [
            'provider' => $request->header('X-Provider'),
            'payload'  => $request->all(),
        ]);
    }
}
