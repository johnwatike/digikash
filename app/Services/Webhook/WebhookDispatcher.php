<?php

namespace App\Services\Webhook;

use App\Enums\WebhookEventType;
use App\Jobs\DeliverWebhook;
use App\Models\Merchant;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;

class WebhookDispatcher
{
    public function ensureLegacyIpnEndpoint(Merchant $merchant, string $ipnUrl, string $environment): WebhookEndpoint
    {
        return WebhookEndpoint::query()->firstOrCreate(
            [
                'merchant_id'   => $merchant->id,
                'url'           => $ipnUrl,
                'is_legacy_ipn' => true,
            ],
            [
                'events'      => ['*'],
                'api_version' => '2026-06-01',
                'status'      => 'active',
                'secret'      => $environment === 'sandbox'
                    ? ($merchant->test_api_secret ?? '')
                    : ($merchant->getRawOriginal('api_secret') ?: ''),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(
        Merchant $merchant,
        WebhookEventType|string $type,
        array $payload,
        string $resourceId,
        string $environment = 'production',
    ): WebhookEvent {
        $eventType = $type instanceof WebhookEventType ? $type->value : $type;

        return DB::transaction(function () use ($merchant, $eventType, $payload, $resourceId, $environment) {
            $sequence = (int) WebhookEvent::query()
                ->where('merchant_id', $merchant->id)
                ->where('resource_id', $resourceId)
                ->max('sequence') + 1;

            $event = WebhookEvent::query()->create([
                'merchant_id'   => $merchant->id,
                'type'          => $eventType,
                'resource_type' => 'payment_intent',
                'resource_id'   => $resourceId,
                'sequence'      => $sequence,
                'payload'       => [
                    'id'      => null,
                    'type'    => $eventType,
                    'created' => now()->timestamp,
                    'data'    => $payload,
                ],
                'environment' => $environment,
            ]);

            $event->payload = array_merge($event->payload, ['id' => $event->event_id]);
            $event->save();

            $endpoints = WebhookEndpoint::query()
                ->where('merchant_id', $merchant->id)
                ->where('status', 'active')
                ->get();

            foreach ($endpoints as $endpoint) {
                if (! $endpoint->acceptsEvent($eventType)) {
                    continue;
                }

                $delivery = WebhookDelivery::query()->create([
                    'webhook_event_id'    => $event->id,
                    'webhook_endpoint_id' => $endpoint->id,
                    'status'              => 'pending',
                    'next_retry_at'       => now(),
                ]);

                DeliverWebhook::dispatch($delivery->id);
            }

            return $event;
        });
    }

    public function replayDelivery(WebhookDelivery $delivery): void
    {
        $delivery->update([
            'status'        => 'pending',
            'attempt'       => 1,
            'next_retry_at' => now(),
            'error_message' => null,
        ]);

        DeliverWebhook::dispatch($delivery->id);
    }
}
