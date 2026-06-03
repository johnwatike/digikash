@extends('frontend.layouts.user.index')
@section('title', __('Settlements'))
@section('content')
<div class="card single-form-card p-4">
    <h4>{{ __('Settlement report') }}</h4>
    <p>{{ __('Next settlement (T+:delay)', ['delay' => $report['settlement_delay']]) }}: <strong>{{ $report['next_settlement'] }}</strong></p>
    <div class="row mb-3">
        <div class="col-md-4"><div class="border rounded p-3"><small>{{ __('Gross') }}</small><h5>{{ number_format($report['gross'], 2) }}</h5></div></div>
        <div class="col-md-4"><div class="border rounded p-3"><small>{{ __('Fees') }}</small><h5>{{ number_format($report['fees'], 2) }}</h5></div></div>
        <div class="col-md-4"><div class="border rounded p-3"><small>{{ __('Net') }}</small><h5>{{ number_format($report['net'], 2) }}</h5></div></div>
    </div>
    <a href="{{ route('user.merchant.settlements.export', $merchant) }}" class="btn btn-primary btn-sm">{{ __('Export CSV') }}</a>
    <a href="{{ route('user.merchant.config', $merchant) }}" class="btn btn-outline-secondary btn-sm ms-2">{{ __('Back') }}</a>
</div>
@endsection
