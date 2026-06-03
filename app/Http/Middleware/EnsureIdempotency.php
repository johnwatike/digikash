<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyRecord;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');

        if (! $key || strlen($key) > 128) {
            return $next($request);
        }

        $merchant = $request->merchant;

        if (! $merchant) {
            return $next($request);
        }

        $existing = IdempotencyRecord::query()
            ->where('merchant_id', $merchant->id)
            ->where('idempotency_key', $key)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            return response()->json($existing->response_body, $existing->response_status)
                ->header('Idempotent-Replayed', 'true');
        }

        $request->attributes->set('idempotency_key', $key);

        $response = $next($request);

        if ($response->isSuccessful() || $response->getStatusCode() === 422) {
            $body = json_decode($response->getContent(), true);

            if (is_array($body)) {
                IdempotencyRecord::query()->updateOrCreate(
                    [
                        'merchant_id'      => $merchant->id,
                        'idempotency_key'  => $key,
                    ],
                    [
                        'response_status' => $response->getStatusCode(),
                        'response_body'   => $body,
                        'expires_at'      => now()->addHours(24),
                    ]
                );
            }
        }

        return $response;
    }
}
