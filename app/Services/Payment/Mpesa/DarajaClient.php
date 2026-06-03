<?php

namespace App\Services\Payment\Mpesa;

use App\Models\PaymentGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DarajaClient
{
    protected array $credentials;

    protected string $baseUrl;

    public function __construct(?string $environment = null)
    {
        $this->credentials = PaymentGateway::getCredentials('mpesa');
        $env               = $environment ?? ($this->credential('environment') ?: 'sandbox');
        $this->baseUrl     = $env === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    public function getAccessToken(): string
    {
        return Cache::remember('mpesa:oauth:'.md5($this->baseUrl), 3500, function () {
            $response = Http::withBasicAuth(
                $this->credential('consumer_key'),
                $this->credential('consumer_secret'),
            )->get($this->baseUrl.'/oauth/v1/generate', [
                'grant_type' => 'client_credentials',
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException('M-PESA OAuth failed: '.$response->body());
            }

            return (string) $response->json('access_token');
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function stkPush(
        string $businessShortCode,
        string $password,
        string $timestamp,
        string $phone,
        float $amount,
        string $accountReference,
        string $transactionDesc,
        string $callbackUrl,
    ): array {
        $token = $this->getAccessToken();

        $payload = [
            'BusinessShortCode' => $businessShortCode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => 'CustomerBuyGoodsOnline',
            'Amount'            => (int) round($amount),
            'PartyA'            => $this->normalizePhone($phone),
            'PartyB'            => $businessShortCode,
            'PhoneNumber'       => $this->normalizePhone($phone),
            'CallBackURL'       => $callbackUrl,
            'AccountReference'  => substr($accountReference, 0, 12),
            'TransactionDesc'   => substr($transactionDesc, 0, 13),
        ];

        if ($this->isSandbox()) {
            return $this->simulateStkPush($payload);
        }

        $response = Http::withToken($token)
            ->post($this->baseUrl.'/mpesa/stkpush/v1/processrequest', $payload);

        if (! $response->successful()) {
            Log::error('M-PESA STK Push failed', ['body' => $response->body()]);

            throw new \RuntimeException('STK Push failed: '.$response->json('errorMessage', $response->body()));
        }

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function reverseTransaction(string $transactionId, float $amount, string $receiverPhone): array
    {
        if ($this->isSandbox()) {
            return ['ResultCode' => 0, 'ResultDesc' => 'Accepted sandbox reversal'];
        }

        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->post($this->baseUrl.'/mpesa/reversal/v1/request', [
                'Initiator'              => $this->credential('initiator_name'),
                'SecurityCredential'     => $this->credential('security_credential'),
                'CommandID'              => 'TransactionReversal',
                'TransactionID'          => $transactionId,
                'Amount'                 => (int) round($amount),
                'ReceiverParty'          => $this->normalizePhone($receiverPhone),
                'RecieverIdentifierType' => '4',
                'Remarks'                => 'Refund',
                'QueueTimeOutURL'        => route('webhooks.mpesa.reversal-timeout'),
                'ResultURL'              => route('webhooks.mpesa.reversal-result'),
            ]);

        return $response->json() ?? [];
    }

    public function generatePassword(string $shortcode, string $passkey): string
    {
        $timestamp = now()->format('YmdHis');

        return base64_encode($shortcode.$passkey.$timestamp);
    }

    public function timestamp(): string
    {
        return now()->format('YmdHis');
    }

    protected function credential(string $key, mixed $default = ''): mixed
    {
        return $this->credentials[$key] ?? $default;
    }

    protected function isSandbox(): bool
    {
        return str_contains($this->baseUrl, 'sandbox');
    }

    protected function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($phone, '0')) {
            $phone = '254'.substr($phone, 1);
        }

        if (! str_starts_with($phone, '254')) {
            $phone = '254'.$phone;
        }

        return $phone;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function simulateStkPush(array $payload): array
    {
        $scenario = $this->credential('sandbox_stk_scenario', 'success');

        if ($scenario === 'timeout') {
            throw new \RuntimeException('Simulated STK timeout');
        }

        if ($scenario === 'decline') {
            return [
                'ResponseCode'        => '0',
                'ResponseDescription' => 'Success. Request accepted for processing',
                'CheckoutRequestID'   => 'ws_CO_'.uniqid(),
                'MerchantRequestID'   => 'mr_'.uniqid(),
                '_sandbox_decline'    => true,
            ];
        }

        return [
            'ResponseCode'        => '0',
            'ResponseDescription' => 'Success. Request accepted for processing',
            'CheckoutRequestID'   => 'ws_CO_'.uniqid(),
            'MerchantRequestID'   => 'mr_'.uniqid(),
        ];
    }
}
