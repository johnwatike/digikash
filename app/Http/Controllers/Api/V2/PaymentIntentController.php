<?php

namespace App\Http\Controllers\Api\V2;

use App\Enums\EnvironmentMode;
use App\Enums\PaymentIntentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\PaymentInitiateRequest;
use App\Models\Currency as CurrencyModel;
use App\Models\PaymentIntent;
use App\Services\MerchantService;
use App\Services\PaymentIntentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;

class PaymentIntentController extends Controller
{
    public function __construct(protected PaymentIntentService $paymentIntentService) {}

    public function store(PaymentInitiateRequest $request): JsonResponse
    {
        $validated   = $request->validated();
        $merchant    = $request->merchant;
        $environment = EnvironmentMode::from($request->get('environment'));
        $isSandbox   = $environment->isSandbox();

        $merchant->loadMissing(['user', 'currency', 'supportedCurrencies']);

        $paymentCurrency = CurrencyModel::query()
            ->where('code', strtoupper(trim((string) $validated['currency_code'])))
            ->first();

        if (! $paymentCurrency) {
            return response()->json(['error' => 'Invalid currency code.'], 422);
        }

        if (! $merchant->supportsCurrency((int) $paymentCurrency->id)) {
            return response()->json(['error' => 'Currency is not enabled for this merchant.'], 422);
        }

        if ($isSandbox && ! $merchant->hasTestCredentials()) {
            $merchant->generateTestCredentials();
        }

        $allowedMethods   = $this->normalizeAllowPaymentMethods($request->input('allow_payment_methods'));
        $gatewaySelection = $this->merchantPaymentMethodSelection($merchant, $paymentCurrency->code);

        $paymentData = array_merge(
            $request->only([
                'ref_trx',
                'description',
                'ipn_url',
                'cancel_redirect',
                'success_redirect',
                'customer_name',
                'customer_email',
            ]),
            [
                'merchant_id'                         => $merchant->id,
                'merchant_name'                       => $merchant->business_name,
                'amount'                              => $validated['payment_amount'],
                'currency_code'                       => $paymentCurrency->code,
                'environment'                         => $environment->value,
                'is_sandbox'                          => $isSandbox,
                'allow_payment_methods'               => $allowedMethods,
                'merchant_payment_methods_restricted' => $gatewaySelection['restricted'],
                'merchant_payment_method_ids'         => $gatewaySelection['ids'],
                'merchant_payment_method_codes'       => $gatewaySelection['codes'],
            ]
        );

        try {
            $intent = $this->paymentIntentService->createFromMerchantPayment(
                $merchant,
                $paymentData,
                (float) $validated['payment_amount'],
                $paymentCurrency->code,
                $environment,
                $request->attributes->get('idempotency_key'),
            );

            $encryptedTrxId = Crypt::encryptString($intent->trx_id);
            $paymentUrl     = URL::signedRoute('payment.checkout', [
                'token' => $encryptedTrxId,
            ], now()->addMinutes(900));

            return response()->json([
                'payment_intent' => $this->paymentIntentService->serializeIntent($intent),
                'payment_url'    => $paymentUrl,
                'checkout_token' => $encryptedTrxId,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, string $piId): JsonResponse
    {
        $intent = $this->paymentIntentService->findByPiId($piId, (int) $request->merchant->id);

        if (! $intent) {
            return response()->json(['error' => 'Payment intent not found.'], 404);
        }

        return response()->json([
            'payment_intent' => $this->paymentIntentService->serializeIntent($intent),
        ]);
    }

    public function cancel(Request $request, string $piId): JsonResponse
    {
        $intent = $this->paymentIntentService->findByPiId($piId, (int) $request->merchant->id);

        if (! $intent) {
            return response()->json(['error' => 'Payment intent not found.'], 404);
        }

        if ($intent->status->isTerminal()) {
            return response()->json(['error' => 'Payment intent is already terminal.'], 422);
        }

        $intent = $this->paymentIntentService->markCanceled($intent, 'canceled_by_merchant');

        return response()->json([
            'payment_intent' => $this->paymentIntentService->serializeIntent($intent),
        ]);
    }

    private function normalizeAllowPaymentMethods(null|string|array $input): array
    {
        if ($input === null) {
            return [];
        }

        $items = is_string($input)
            ? (preg_split('/[\s,|]+/', $input, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            : $input;

        return array_values(array_unique(array_filter(array_map(
            static fn ($v) => \Illuminate\Support\Str::limit(\Illuminate\Support\Str::lower(trim((string) $v)), 60, ''),
            $items
        ), static fn ($v) => $v !== '')));
    }

    private function merchantPaymentMethodSelection($merchant, string $currencyCode): array
    {
        $merchant->loadMissing('paymentMethods.paymentGateway');
        $restricted = $merchant->paymentMethods->isNotEmpty();
        $methods    = app(MerchantService::class)->configuredPaymentMethodsForCurrency($merchant, $currencyCode)
            ->filter(fn ($method) => $method->type === \App\Enums\MethodType::AUTOMATIC && (bool) $method->status)
            ->values();

        return [
            'restricted' => $restricted,
            'ids'        => $methods->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'codes'      => $methods->pluck('method_code')->filter()->map(fn ($code) => (string) $code)->values()->all(),
        ];
    }
}
