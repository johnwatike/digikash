<?php

use App\Models\PaymentGateway;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (PaymentGateway::query()->where('code', 'mpesa')->exists()) {
            return;
        }

        PaymentGateway::query()->create([
            'code'        => 'mpesa',
            'name'        => 'M-PESA (Safaricom Daraja)',
            'logo'        => 'images/gateways/mpesa.png',
            'currencies'  => ['KES'],
            'credentials' => [
                'consumer_key'        => '',
                'consumer_secret'     => '',
                'passkey'             => '',
                'shortcode'           => '',
                'initiator_name'      => '',
                'security_credential' => '',
                'environment'         => 'sandbox',
                'sandbox_stk_scenario'=> 'success',
            ],
            'status'      => false,
        ]);
    }

    public function down(): void
    {
        PaymentGateway::query()->where('code', 'mpesa')->delete();
    }
};
