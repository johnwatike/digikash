<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\PaymentIntentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MpesaCheckoutController extends Controller
{
    public function checkout(Request $request): View|RedirectResponse
    {
        $trxId = $this->resolveTrxId($request);

        if (! $trxId) {
            abort(404);
        }

        $transaction = Transaction::findTransaction($trxId);
        $intent      = app(PaymentIntentService::class)->findByTrxId($trxId);

        return view('general.merchant.mpesa_checkout', compact('transaction', 'intent'));
    }

    public function submit(Request $request): RedirectResponse
    {
        $trxId = $this->resolveTrxId($request);
        $transaction = Transaction::findTransaction($trxId);

        $factory = app(PaymentGatewayFactory::class);
        $gateway = $factory->getGateway('mpesa');

        return $gateway->deposit(
            $transaction->payable_amount,
            $transaction->payable_currency,
            $transaction->trx_id,
        );
    }

    public function paybill(Request $request): View
    {
        $trxId       = $this->resolveTrxId($request);
        $transaction = Transaction::findTransaction($trxId);
        $intent      = app(PaymentIntentService::class)->findByTrxId($trxId);

        return view('general.merchant.mpesa_paybill', compact('transaction', 'intent'));
    }

    public function stkWait(Request $request): View
    {
        $trxId       = $this->resolveTrxId($request);
        $transaction = Transaction::findTransaction($trxId);
        $intent      = app(PaymentIntentService::class)->findByTrxId($trxId);

        return view('general.merchant.mpesa_stk_wait', compact('transaction', 'intent'));
    }

    protected function resolveTrxId(Request $request): ?string
    {
        $token = $request->query('token') ?? $request->input('token');

        if (! $token) {
            return null;
        }

        try {
            return decrypt($token);
        } catch (\Throwable) {
            return null;
        }
    }
}
