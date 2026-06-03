<?php

namespace App\Services\Payment;

interface PaymentGatewayCapabilities
{
    public function supports3DS(): bool;

    public function supportsCapture(): bool;

    public function supportsRefund(): bool;
}
