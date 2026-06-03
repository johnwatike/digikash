@extends('frontend.layouts.user.index')
@section('title', __('M-PESA'))
@section('content')
<div class="card single-form-card">
    <div class="card-main p-4">
        <h4>{{ __('M-PESA Paybill & Till') }}</h4>
        <p class="alert alert-info">
            {{ __('Till Number') }}: {{ __('customers pay at checkout via STK Push.') }}
            {{ __('Paybill') }}: {{ __('customers pay via M-PESA app with Account Number reference.') }}
        </p>

        <form method="POST" action="{{ route('user.merchant.mpesa.shortcodes.store', $merchant) }}" class="mb-4">
            @csrf
            <div class="row g-2">
                <div class="col-md-2">
                    <select name="type" class="form-select" required>
                        <option value="till">{{ __('Till') }}</option>
                        <option value="paybill">{{ __('Paybill') }}</option>
                    </select>
                </div>
                <div class="col-md-3"><input name="shortcode" class="form-control" placeholder="{{ __('Shortcode') }}" required></div>
                <div class="col-md-3"><input name="label" class="form-control" placeholder="{{ __('Store label') }}"></div>
                <div class="col-md-3"><input name="nominated_phone" class="form-control" placeholder="{{ __('Nominated phone (Till STK)') }}"></div>
                <div class="col-md-1"><button class="btn btn-primary w-100">{{ __('Add') }}</button></div>
            </div>
        </form>

        <h5>{{ __('Shortcodes') }}</h5>
        <ul class="list-group mb-4">
            @foreach($shortcodes as $sc)
                <li class="list-group-item d-flex justify-content-between">
                    <span>
                        <strong>{{ strtoupper($sc->type) }}</strong> {{ $sc->shortcode }}
                        @if($sc->label)<small class="text-muted">— {{ $sc->label }}</small>@endif
                    </span>
                    @if($sc->isTill())
                        <a href="{{ route('user.merchant.mpesa.qr', [$merchant, $sc]) }}" class="btn btn-sm btn-outline-primary">{{ __('QR') }}</a>
                    @endif
                </li>
            @endforeach
        </ul>

        <h5>{{ __('STK Push simulator') }} ({{ __('sandbox') }})</h5>
        <form method="POST" action="{{ route('user.merchant.mpesa.stk-simulate', $merchant) }}" class="mb-4">
            @csrf
            <div class="row g-2">
                <div class="col-md-4"><input name="pi_id" class="form-control" placeholder="pi_..." required></div>
                <div class="col-md-3"><input name="phone" class="form-control" placeholder="2547..." required></div>
                <div class="col-md-3"><input name="amount" type="number" class="form-control" value="100" required></div>
                <div class="col-md-2"><button class="btn btn-warning w-100">{{ __('Simulate') }}</button></div>
            </div>
        </form>

        <h5>{{ __('Safaricom callback log') }}</h5>
        <pre class="bg-light p-3 small" style="max-height:300px;overflow:auto">@foreach($mpesaLogs as $log){{ json_encode($log->raw_payload, JSON_PRETTY_PRINT) }}

@endforeach</pre>

        <a href="{{ route('user.merchant.config', $merchant) }}" class="btn btn-outline-secondary btn-sm">{{ __('Back') }}</a>
    </div>
</div>
@endsection
