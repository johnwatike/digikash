@extends('frontend.layouts.user.index')
@section('title', __('Lipa Na M-PESA QR'))
@section('content')
<div class="card single-form-card text-center p-4">
    <h4>{{ __('Lipa Na M-PESA') }}</h4>
    <p>{{ __('Till') }}: <strong>{{ $shortcode->shortcode }}</strong></p>
    <div id="mpesa-qr" class="my-3"></div>
    <p class="small text-muted">{{ $qrPayload }}</p>
    <a href="{{ route('user.merchant.mpesa', $merchant) }}" class="btn btn-outline-secondary btn-sm">{{ __('Back') }}</a>
</div>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>new QRCode(document.getElementById('mpesa-qr'), { text: @json($qrPayload), width: 200, height: 200 });</script>
@endsection
