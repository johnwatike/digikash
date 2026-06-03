<?php

namespace App\Enums;

enum PaymentIntentStatus: string
{
    case REQUIRES_PAYMENT_METHOD = 'requires_payment_method';
    case REQUIRES_ACTION         = 'requires_action';
    case PROCESSING              = 'processing';
    case REQUIRES_CAPTURE        = 'requires_capture';
    case SUCCEEDED               = 'succeeded';
    case FAILED                  = 'failed';
    case CANCELED                = 'canceled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::SUCCEEDED, self::FAILED, self::CANCELED], true);
    }
}
