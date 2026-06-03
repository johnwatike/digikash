<?php

namespace App\Listeners;

use App\Events\PaymentIntentCreated;
use App\Events\PaymentIntentRequiresAction;
use App\Events\PaymentIntentStatusChanged;
use App\Events\PaymentIntentSucceeded;
use Illuminate\Support\Facades\Log;

class LogPaymentIntentEvent
{
    public function handleCreated(PaymentIntentCreated $event): void
    {
        Log::info('Payment intent created', ['pi_id' => $event->paymentIntent->pi_id]);
    }

    public function handleRequiresAction(PaymentIntentRequiresAction $event): void
    {
        Log::info('Payment intent requires action', [
            'pi_id'  => $event->paymentIntent->pi_id,
            'action' => $event->paymentIntent->next_action_type,
        ]);
    }

    public function handleSucceeded(PaymentIntentSucceeded $event): void
    {
        Log::info('Payment intent succeeded', ['pi_id' => $event->paymentIntent->pi_id]);
    }

    public function handleStatusChanged(PaymentIntentStatusChanged $event): void
    {
        Log::info('Payment intent status changed', [
            'pi_id'  => $event->paymentIntent->pi_id,
            'status' => $event->paymentIntent->status->value,
        ]);
    }
}
