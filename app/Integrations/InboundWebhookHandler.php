<?php

namespace App\Integrations;

use Illuminate\Http\Request;

interface InboundWebhookHandler
{
    public function code(): string;

    public function handle(Request $request): void;
}
