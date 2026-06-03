<?php

namespace App\Integrations\Kyc;

use App\Integrations\InboundWebhookHandler;
use App\Models\KycSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KycVendorWebhookHandler implements InboundWebhookHandler
{
    public function code(): string
    {
        return 'kyc_vendor';
    }

    public function handle(Request $request): void
    {
        $event  = $request->input('event');
        $userId = $request->input('user_id');

        Log::info('KYC vendor webhook', ['event' => $event, 'user_id' => $userId]);

        if ($userId && in_array($event, ['kyc.verification_passed', 'kyc.verification_failed'], true)) {
            $submission = KycSubmission::query()->where('user_id', $userId)->latest()->first();

            if ($submission) {
                $submission->status = $event === 'kyc.verification_passed' ? 'approved' : 'rejected';
                $submission->save();
            }
        }
    }
}
