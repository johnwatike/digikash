<?php

namespace App\Enums;

enum MerchantTeamRole: string
{
    case ADMIN     = 'admin';
    case DEVELOPER = 'developer';
    case FINANCE   = 'finance';
    case SUPPORT   = 'support';

    public function defaultPermissions(): array
    {
        return match ($this) {
            self::ADMIN => ['*'],
            self::DEVELOPER => ['api_keys', 'webhooks', 'sandbox', 'payment_intents.read'],
            self::FINANCE => ['settlements', 'refunds', 'exports', 'payment_intents.read'],
            self::SUPPORT => ['transactions.read', 'webhooks.read', 'webhooks.replay'],
        };
    }
}
