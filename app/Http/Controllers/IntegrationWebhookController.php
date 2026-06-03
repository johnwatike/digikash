<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationWebhookController extends Controller
{
    public function handle(Request $request, string $code): JsonResponse
    {
        $registry = app('integration.registry');
        $handler  = $registry[$code] ?? null;

        if (! $handler) {
            return response()->json(['error' => 'Unknown integration'], 404);
        }

        $handler->handle($request);

        return response()->json(['received' => true]);
    }
}
