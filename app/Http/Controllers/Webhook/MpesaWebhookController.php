<?php

namespace App\Http\Controllers\Webhook;

use App\Enums\PaymentIntentStatus;
use App\Enums\WebhookEventType;
use App\Http\Controllers\Controller;
use App\Models\Mpesa\MpesaShortcode;
use App\Models\Mpesa\MpesaTransaction;
use App\Models\PaymentIntent;
use App\Services\PaymentIntentService;
use App\Services\Webhook\WebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Transaction;

class MpesaWebhookController extends Controller
{
    public function __construct(
        protected PaymentIntentService $paymentIntentService,
        protected WebhookDispatcher $webhookDispatcher,
    ) {}

    public function stkCallback(Request $request): JsonResponse
    {
        $body = $request->all();
        Log::info('M-PESA STK callback', ['payload' => $body]);

        $callback = $body['Body']['stkCallback'] ?? $body['stkCallback'] ?? null;

        if (! $callback) {
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Invalid payload']);
        }

        $checkoutId = $callback['CheckoutRequestID'] ?? null;
        $resultCode = (int) ($callback['ResultCode'] ?? 1);

        $mpesaTxn = MpesaTransaction::query()
            ->where('bill_ref_number', $checkoutId)
            ->orWhere('raw_payload->CheckoutRequestID', $checkoutId)
            ->first();

        if (! $mpesaTxn) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $mpesaTxn->raw_payload = array_merge($mpesaTxn->raw_payload ?? [], ['callback' => $body]);
        $mpesaTxn->save();

        $intent = $mpesaTxn->paymentIntent;

        if (! $intent) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        if ($resultCode === 0) {
            $metadata = collect($callback['CallbackMetadata']['Item'] ?? [])->keyBy('Name');
            $mpesaTxn->trans_id = $metadata->get('MpesaReceiptNumber')['Value'] ?? $mpesaTxn->trans_id;
            $mpesaTxn->msisdn    = (string) ($metadata->get('PhoneNumber')['Value'] ?? $mpesaTxn->msisdn);
            $mpesaTxn->status    = 'completed';
            $mpesaTxn->save();

            $this->completePayment($intent);

            $this->webhookDispatcher->dispatch(
                $intent->merchant,
                WebhookEventType::MPESA_STK_COMPLETED,
                ['mpesa' => $mpesaTxn->toArray(), 'payment_intent' => $this->paymentIntentService->serializeIntent($intent)],
                $intent->pi_id,
                $intent->environment,
            );
        } else {
            $mpesaTxn->status = 'failed';
            $mpesaTxn->save();
            Transaction::failTransaction($intent->trx_id, 'M-PESA STK failed');
            $this->paymentIntentService->markFailed($intent, $callback['ResultDesc'] ?? 'STK failed');
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    public function c2bValidation(Request $request, int $shortcodeId): JsonResponse
    {
        Log::info('M-PESA C2B validation', ['shortcode_id' => $shortcodeId, 'payload' => $request->all()]);

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted',
        ]);
    }

    public function c2bConfirmation(Request $request, int $shortcodeId): JsonResponse
    {
        $payload = $request->all();
        Log::info('M-PESA C2B confirmation', ['shortcode_id' => $shortcodeId, 'payload' => $payload]);

        $transId    = $payload['TransID'] ?? $payload['trans_id'] ?? null;
        $billRef    = $payload['BillRefNumber'] ?? $payload['bill_ref'] ?? null;
        $amount     = (float) ($payload['TransAmount'] ?? $payload['amount'] ?? 0);
        $msisdn     = $payload['MSISDN'] ?? $payload['msisdn'] ?? null;

        if ($transId && MpesaTransaction::query()->where('trans_id', $transId)->exists()) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Duplicate accepted']);
        }

        $intent = null;

        if ($billRef) {
            $intent = PaymentIntent::query()
                ->where('pi_id', 'like', '%'.substr($billRef, 0, 12).'%')
                ->orWhereHas('mpesaTransactions', fn ($q) => $q->where('bill_ref_number', $billRef))
                ->first();

            if (! $intent) {
                $intent = PaymentIntent::query()
                    ->whereRaw('UPPER(SUBSTRING(pi_id, 4, 12)) = ?', [strtoupper($billRef)])
                    ->first();
            }
        }

        $shortcode = MpesaShortcode::query()->find($shortcodeId);

        MpesaTransaction::query()->create([
            'mpesa_shortcode_id' => $shortcode?->id,
            'payment_intent_id'  => $intent?->id,
            'trans_id'           => $transId,
            'bill_ref_number'    => $billRef,
            'msisdn'             => $msisdn,
            'amount'             => $amount,
            'transaction_type'   => 'c2b',
            'status'             => 'completed',
            'raw_payload'        => $payload,
        ]);

        if ($intent && $intent->status !== PaymentIntentStatus::SUCCEEDED) {
            $this->completePayment($intent);

            $this->webhookDispatcher->dispatch(
                $intent->merchant,
                WebhookEventType::MPESA_C2B_RECEIVED,
                ['mpesa' => $payload, 'payment_intent' => $this->paymentIntentService->serializeIntent($intent)],
                $intent->pi_id,
                $intent->environment,
            );
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    public function handleGenericIpn(Request $request): JsonResponse
    {
        if ($request->has('Body')) {
            return $this->stkCallback($request);
        }

        if ($request->has('TransID') || $request->has('BillRefNumber')) {
            $shortcodeId = (int) $request->route('shortcodeId', 0);

            return $this->c2bConfirmation($request, $shortcodeId ?: 0);
        }

        return response()->json(['message' => 'Unhandled M-PESA IPN'], 400);
    }

    public function completeSandboxStk(PaymentIntent $intent, $transaction, float $amount, string $phone): void
    {
        $mpesaTxn = MpesaTransaction::query()->where('payment_intent_id', $intent->id)->latest()->first();

        if ($mpesaTxn) {
            $mpesaTxn->trans_id = 'SAN'.strtoupper(substr(uniqid(), -8));
            $mpesaTxn->msisdn   = $phone;
            $mpesaTxn->status   = 'completed';
            $mpesaTxn->save();
        }

        $this->completePayment($intent);
    }

    public function reversalResult(Request $request): JsonResponse
    {
        Log::info('M-PESA reversal result', $request->all());

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    public function reversalTimeout(Request $request): JsonResponse
    {
        Log::warning('M-PESA reversal timeout', $request->all());

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    protected function completePayment(PaymentIntent $intent): void
    {
        DB::transaction(function () use ($intent) {
            Transaction::completeTransaction($intent->trx_id, 'M-PESA payment completed');
            $this->paymentIntentService->markSucceeded($intent->fresh());
        });
    }
}
