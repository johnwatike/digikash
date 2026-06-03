<?php

namespace App\Services\Payment\Concerns;

trait HasStandardGatewayCapabilities
{
    public function supports3DS(): bool
    {
        return false;
    }

    public function supportsCapture(): bool
    {
        return false;
    }

    public function supportsRefund(): bool
    {
        return false;
    }
}
