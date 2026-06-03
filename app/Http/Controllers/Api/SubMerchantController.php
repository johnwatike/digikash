<?php

namespace App\Http\Controllers\Api;

use App\Enums\MerchantStatus;
use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubMerchantController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $platform = $request->merchant;

        $validated = $request->validate([
            'business_name'        => ['required', 'string', 'max:255'],
            'business_email'       => ['required', 'email'],
            'site_url'             => ['nullable', 'url'],
            'currency_code'        => ['required', 'string', 'size:3'],
            'owner_email'          => ['required', 'email'],
            'metadata'             => ['nullable', 'array'],
        ]);

        $owner = User::query()->firstOrCreate(
            ['email' => $validated['owner_email']],
            [
                'first_name' => 'Sub',
                'last_name'  => 'Merchant',
                'password'   => bcrypt(Str::random(32)),
            ]
        );

        $merchant = Merchant::query()->create([
            'user_id'              => $owner->id,
            'business_name'        => $validated['business_name'],
            'business_email'       => $validated['business_email'],
            'site_url'             => $validated['site_url'] ?? null,
            'status'               => MerchantStatus::PENDING,
            'fee'                  => $platform->fee,
            'merchant_key'         => 'sm_'.Str::random(16),
            'api_key'              => 'sk_'.Str::random(40),
            'api_secret'           => 'secret_'.Str::random(32),
            'sandbox_enabled'      => true,
        ]);

        return response()->json([
            'sub_merchant' => [
                'id'            => $merchant->id,
                'business_name' => $merchant->business_name,
                'status'        => $merchant->status->value,
                'kyc_status'    => 'pending',
            ],
        ], 201);
    }

    public function kycStatus(Request $request, int $subMerchantId): JsonResponse
    {
        $merchant = Merchant::query()->findOrFail($subMerchantId);

        $kyc = $merchant->user
            ? \App\Models\KycSubmission::query()->where('user_id', $merchant->user_id)->latest()->first()
            : null;

        return response()->json([
            'merchant_id' => $merchant->id,
            'status'      => $merchant->status->value,
            'kyc_status'  => $kyc?->status ?? 'not_submitted',
        ]);
    }
}
