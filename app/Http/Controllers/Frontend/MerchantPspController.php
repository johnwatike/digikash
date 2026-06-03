<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\MerchantTeamRole;
use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantTeamMember;
use App\Models\Mpesa\MpesaShortcode;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\PaymentIntentService;
use App\Services\SettlementReportService;
use App\Services\Webhook\WebhookDispatcher;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class MerchantPspController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected WebhookDispatcher $webhookDispatcher,
        protected SettlementReportService $settlementReportService,
        protected PaymentIntentService $paymentIntentService,
    ) {}

    public function webhooks(Merchant $merchant): View
    {
        $this->authorize('update', $merchant);

        $endpoints = WebhookEndpoint::query()->where('merchant_id', $merchant->id)->latest()->get();
        $deliveries = WebhookDelivery::query()
            ->whereHas('webhookEndpoint', fn ($q) => $q->where('merchant_id', $merchant->id))
            ->with(['webhookEvent', 'webhookEndpoint'])
            ->latest()
            ->limit(50)
            ->get();

        return view('frontend.user.merchant.psp.webhooks', compact('merchant', 'endpoints', 'deliveries'));
    }

    public function storeWebhook(Request $request, Merchant $merchant): RedirectResponse
    {
        $this->authorize('update', $merchant);

        $validated = $request->validate([
            'url'    => ['required', 'url'],
            'events' => ['nullable', 'string'],
        ]);

        $events = $validated['events']
            ? array_map('trim', explode(',', $validated['events']))
            : ['*'];

        WebhookEndpoint::query()->create([
            'merchant_id' => $merchant->id,
            'url'         => $validated['url'],
            'events'      => $events,
            'status'      => 'active',
        ]);

        return back()->with('success', __('Webhook endpoint added.'));
    }

    public function replayWebhook(Merchant $merchant, int $deliveryId): RedirectResponse
    {
        $this->authorize('update', $merchant);

        $delivery = WebhookDelivery::query()
            ->whereHas('webhookEndpoint', fn ($q) => $q->where('merchant_id', $merchant->id))
            ->findOrFail($deliveryId);

        $this->webhookDispatcher->replayDelivery($delivery);

        return back()->with('success', __('Webhook replay queued.'));
    }

    public function mpesa(Merchant $merchant): View
    {
        $this->authorize('update', $merchant);

        $shortcodes = MpesaShortcode::query()->where('merchant_id', $merchant->id)->get();
        $mpesaLogs  = \App\Models\Mpesa\MpesaTransaction::query()
            ->whereHas('paymentIntent', fn ($q) => $q->where('merchant_id', $merchant->id))
            ->latest()
            ->limit(30)
            ->get();

        return view('frontend.user.merchant.psp.mpesa', compact('merchant', 'shortcodes', 'mpesaLogs'));
    }

    public function storeShortcode(Request $request, Merchant $merchant): RedirectResponse
    {
        $this->authorize('update', $merchant);

        $validated = $request->validate([
            'type'             => ['required', 'in:paybill,till'],
            'shortcode'        => ['required', 'string', 'max:20'],
            'label'            => ['nullable', 'string', 'max:120'],
            'nominated_phone'  => ['nullable', 'string', 'max:20'],
        ]);

        MpesaShortcode::query()->create(array_merge($validated, [
            'merchant_id' => $merchant->id,
            'environment' => $merchant->current_mode?->value ?? 'sandbox',
        ]));

        return back()->with('success', __('M-PESA shortcode saved.'));
    }

    public function mpesaQr(Merchant $merchant, MpesaShortcode $shortcode): View
    {
        $this->authorize('update', $merchant);

        abort_unless($shortcode->merchant_id === $merchant->id, 404);

        $qrPayload = "00020101021126360016KE.COM.SAFARICOM0112{$shortcode->shortcode}";

        return view('frontend.user.merchant.psp.mpesa-qr', compact('merchant', 'shortcode', 'qrPayload'));
    }

    public function stkSimulate(Request $request, Merchant $merchant): RedirectResponse
    {
        $this->authorize('update', $merchant);

        $validated = $request->validate([
            'pi_id'  => ['required', 'string'],
            'phone'  => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:10', 'max:250000'],
        ]);

        $intent = PaymentIntent::query()
            ->where('merchant_id', $merchant->id)
            ->where('pi_id', $validated['pi_id'])
            ->firstOrFail();

        $transaction = \App\Models\Transaction::findTransaction($intent->trx_id);

        app(\App\Http\Controllers\Webhook\MpesaWebhookController::class)
            ->completeSandboxStk($intent, $transaction, (float) $validated['amount'], $validated['phone']);

        return back()->with('success', __('STK Push simulated successfully.'));
    }

    public function settlements(Merchant $merchant): View
    {
        $this->authorize('update', $merchant);

        $report = $this->settlementReportService->buildReport($merchant);

        return view('frontend.user.merchant.psp.settlements', compact('merchant', 'report'));
    }

    public function exportSettlements(Merchant $merchant): Response
    {
        $this->authorize('update', $merchant);

        $csv = $this->settlementReportService->exportCsv($merchant);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="settlements-'.$merchant->id.'.csv"',
        ]);
    }

    public function team(Merchant $merchant): View
    {
        $this->authorize('update', $merchant);

        $members = MerchantTeamMember::query()
            ->where('merchant_id', $merchant->id)
            ->with('user')
            ->get();

        return view('frontend.user.merchant.psp.team', compact('merchant', 'members'));
    }

    public function storeTeamMember(Request $request, Merchant $merchant): RedirectResponse
    {
        $this->authorize('update', $merchant);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'role'  => ['required', 'in:admin,developer,finance,support'],
        ]);

        $user = User::query()->where('email', $validated['email'])->firstOrFail();

        MerchantTeamMember::query()->updateOrCreate(
            ['merchant_id' => $merchant->id, 'user_id' => $user->id],
            [
                'role'        => MerchantTeamRole::from($validated['role']),
                'permissions' => MerchantTeamRole::from($validated['role'])->defaultPermissions(),
                'is_active'   => true,
            ]
        );

        return back()->with('success', __('Team member added.'));
    }
}
