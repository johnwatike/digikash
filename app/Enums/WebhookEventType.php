<?php

namespace App\Enums;

enum WebhookEventType: string
{
    case PAYMENT_INTENT_CREATED        = 'payment_intent.created';
    case PAYMENT_INTENT_SUCCEEDED      = 'payment_intent.succeeded';
    case PAYMENT_INTENT_FAILED         = 'payment_intent.failed';
    case PAYMENT_INTENT_CANCELED       = 'payment_intent.canceled';
    case PAYMENT_INTENT_REQUIRES_ACTION = 'payment_intent.requires_action';
    case REFUND_CREATED                = 'refund.created';
    case REFUND_SUCCEEDED              = 'refund.succeeded';
    case SETTLEMENT_REPORT_AVAILABLE   = 'settlement.report.available';
    case MPESA_C2B_RECEIVED            = 'mpesa.c2b.received';
    case MPESA_STK_COMPLETED           = 'mpesa.stk.completed';
    case PAYMENT_COMPLETED             = 'payment.completed';
    case PAYMENT_FAILED                = 'payment.failed';

    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
}
