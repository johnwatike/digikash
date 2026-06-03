<?php

namespace App\Services;

use App\Enums\PaymentIntentStatus;
use App\Enums\TrxType;
use App\Models\LedgerEntry;
use App\Models\Merchant;
use App\Models\MerchantSettlementSchedule;
use App\Models\PaymentIntent;
use App\Models\Transaction;
use Carbon\Carbon;

class SettlementReportService
{
    /**
     * @return array<string, mixed>
     */
    public function buildReport(Merchant $merchant): array
    {
        $schedule = MerchantSettlementSchedule::query()
            ->where('merchant_id', $merchant->id)
            ->where('is_active', true)
            ->first();

        $delayDays = $schedule?->settlement_delay_days ?? 2;

        $intents = PaymentIntent::query()
            ->where('merchant_id', $merchant->id)
            ->where('status', PaymentIntentStatus::SUCCEEDED)
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        $gross = $intents->sum('amount');
        $fees  = $intents->sum('fee');
        $net   = $intents->sum('net_amount');

        $byCurrency = $intents->groupBy('currency')->map(fn ($group) => [
            'gross' => $group->sum('amount'),
            'fees'  => $group->sum('fee'),
            'net'   => $group->sum('net_amount'),
            'count' => $group->count(),
        ]);

        $nextSettlement = now()->addDays($delayDays)->startOfDay();

        return [
            'schedule'        => $schedule,
            'settlement_delay'=> $delayDays,
            'next_settlement' => $nextSettlement->toDateString(),
            'gross'           => $gross,
            'fees'            => $fees,
            'net'             => $net,
            'by_currency'     => $byCurrency,
            'transactions'    => $intents->take(50),
        ];
    }

    public function exportCsv(Merchant $merchant): string
    {
        $report = $this->buildReport($merchant);
        $lines  = ['currency,gross,fee,net,count'];

        foreach ($report['by_currency'] as $currency => $row) {
            $lines[] = implode(',', [
                $currency,
                $row['gross'],
                $row['fees'],
                $row['net'],
                $row['count'],
            ]);
        }

        return implode("\n", $lines);
    }
}
