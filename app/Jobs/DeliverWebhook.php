<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_ATTEMPTS = 10;

    private const RETRY_DELAYS_SECONDS = [5, 30, 120, 600, 1800, 3600, 7200, 21600];

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::query()
            ->with(['webhookEvent', 'webhookEndpoint'])
            ->find($this->deliveryId);

        if (! $delivery || $delivery->status === 'delivered') {
            return;
        }

        if ($delivery->next_retry_at && $delivery->next_retry_at->isFuture()) {
            self::dispatch($delivery->id)->delay($delivery->next_retry_at);

            return;
        }

        $endpoint = $delivery->webhookEndpoint;
        $event    = $delivery->webhookEvent;

        if (! $endpoint || ! $event || $endpoint->status !== 'active') {
            $delivery->update(['status' => 'failed', 'error_message' => 'Endpoint inactive or missing']);

            return;
        }

        $payloadJson = json_encode($event->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp   = (string) now()->timestamp;
        $signature   = hash_hmac('sha256', $timestamp.'.'.$payloadJson, $endpoint->secret);

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'X-Webhook-Id'        => $event->event_id,
                    'X-Webhook-Timestamp' => $timestamp,
                    'X-Webhook-Signature' => $signature,
                    'X-Environment'       => $event->environment,
                    'Content-Type'        => 'application/json',
                ])
                ->withBody($payloadJson, 'application/json')
                ->post($endpoint->url);

            $delivery->http_status    = $response->status();
            $delivery->response_body  = substr((string) $response->body(), 0, 4000);
            $delivery->attempt        = $delivery->attempt + 1;

            if ($response->successful()) {
                $delivery->status       = 'delivered';
                $delivery->delivered_at = now();
                $delivery->save();

                return;
            }

            $this->scheduleRetry($delivery, 'HTTP '.$response->status());
        } catch (\Throwable $e) {
            $this->scheduleRetry($delivery, $e->getMessage());
        }
    }

    protected function scheduleRetry(WebhookDelivery $delivery, string $error): void
    {
        $delivery->error_message = substr($error, 0, 1000);
        $delivery->attempt       = $delivery->attempt + 1;

        if ($delivery->attempt >= self::MAX_ATTEMPTS) {
            $delivery->status = 'dead_letter';
            $delivery->save();
            Log::warning('Webhook delivery moved to dead letter', ['delivery_id' => $delivery->id]);

            return;
        }

        $delayIndex = min($delivery->attempt - 1, count(self::RETRY_DELAYS_SECONDS) - 1);
        $delay      = self::RETRY_DELAYS_SECONDS[$delayIndex];

        $delivery->status        = 'pending';
        $delivery->next_retry_at = now()->addSeconds($delay);
        $delivery->save();

        self::dispatch($delivery->id)->delay($delivery->next_retry_at);
    }
}
