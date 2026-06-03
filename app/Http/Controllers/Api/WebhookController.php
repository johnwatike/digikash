<?php

namespace App\Http\Controllers\Api;

use App\Enums\WebhookEventType;
use App\Http\Controllers\Controller;
use App\Models\PaymentIntent;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhook\WebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function __construct(protected WebhookDispatcher $webhookDispatcher) {}

    public function index(Request $request): JsonResponse
    {
        $endpoints = WebhookEndpoint::query()
            ->where('merchant_id', $request->merchant->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (WebhookEndpoint $e) => [
                'id'          => $e->id,
                'url'         => $e->url,
                'events'      => $e->events,
                'api_version' => $e->api_version,
                'status'      => $e->status,
                'is_legacy'   => $e->is_legacy_ipn,
                'created_at'  => $e->created_at,
            ]);

        return response()->json(['data' => $endpoints]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url'         => ['required', 'url', 'max:2048'],
            'events'      => ['nullable', 'array'],
            'events.*'    => ['string', 'max:80'],
            'api_version' => ['nullable', 'string', 'max:20'],
            'status'      => ['nullable', 'in:active,disabled'],
        ]);

        $endpoint = WebhookEndpoint::query()->create([
            'merchant_id' => $request->merchant->id,
            'url'         => $validated['url'],
            'events'      => $validated['events'] ?? ['*'],
            'api_version' => $validated['api_version'] ?? '2026-06-01',
            'status'      => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'endpoint' => [
                'id'     => $endpoint->id,
                'url'    => $endpoint->url,
                'secret' => $endpoint->secret,
                'events' => $endpoint->events,
                'status' => $endpoint->status,
            ],
        ], 201);
    }

    public function deliveries(Request $request): JsonResponse
    {
        $deliveries = WebhookDelivery::query()
            ->whereHas('webhookEndpoint', fn ($q) => $q->where('merchant_id', $request->merchant->id))
            ->with(['webhookEvent', 'webhookEndpoint'])
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (WebhookDelivery $d) => [
                'id'            => $d->id,
                'event_id'      => $d->webhookEvent?->event_id,
                'event_type'    => $d->webhookEvent?->type,
                'endpoint_url'  => $d->webhookEndpoint?->url,
                'status'        => $d->status,
                'attempt'       => $d->attempt,
                'http_status'   => $d->http_status,
                'error_message' => $d->error_message,
                'delivered_at'  => $d->delivered_at,
                'created_at'    => $d->created_at,
            ]);

        return response()->json(['data' => $deliveries]);
    }

    public function replay(Request $request, int $deliveryId): JsonResponse
    {
        $delivery = WebhookDelivery::query()
            ->whereHas('webhookEndpoint', fn ($q) => $q->where('merchant_id', $request->merchant->id))
            ->findOrFail($deliveryId);

        $this->webhookDispatcher->replayDelivery($delivery);

        return response()->json(['message' => 'Replay queued.']);
    }

    public function test(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event'   => ['required', 'string'],
            'pi_id'   => ['nullable', 'string'],
            'payload' => ['nullable', 'array'],
        ]);

        $merchant = $request->merchant;
        $piId     = $validated['pi_id'] ?? null;

        $payload = $validated['payload'] ?? [];

        if ($piId) {
            $intent = PaymentIntent::query()
                ->where('merchant_id', $merchant->id)
                ->where('pi_id', $piId)
                ->first();

            if ($intent) {
                $payload = app(\App\Services\PaymentIntentService::class)->serializeIntent($intent);
            }
        }

        if ($payload === []) {
            $payload = [
                'id'     => 'pi_test_'.Str::lower(Str::random(12)),
                'object' => 'payment_intent',
                'status' => 'succeeded',
                'amount' => 100,
                'currency' => 'KES',
            ];
        }

        $eventType = WebhookEventType::tryFrom($validated['event'])
            ?? WebhookEventType::PAYMENT_INTENT_SUCCEEDED;

        $event = $this->webhookDispatcher->dispatch(
            $merchant,
            $eventType,
            $payload,
            $payload['id'] ?? 'test_resource',
            $request->get('environment', 'sandbox'),
        );

        return response()->json([
            'message'  => 'Test webhook dispatched.',
            'event_id' => $event->event_id,
        ]);
    }
}
