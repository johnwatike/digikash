<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('M-PESA Checkout') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card p-4 mx-auto" style="max-width:420px">
        <h5>{{ __('Pay with M-PESA') }}</h5>
        <p class="text-muted">{{ __('Amount') }}: {{ $transaction->payable_amount }} {{ $transaction->payable_currency }}</p>
        <form method="POST" action="{{ route('payment.mpesa.checkout.submit') }}">
            @csrf
            <input type="hidden" name="token" value="{{ request('token') }}">
            <div class="mb-3">
                <label class="form-label">{{ __('M-PESA phone') }}</label>
                <input type="tel" name="mpesa_phone" class="form-control" placeholder="254712345678" required>
            </div>
            <div class="mb-3">
                <label class="form-label">{{ __('Payment type') }}</label>
                <select name="shortcode_type" class="form-select">
                    <option value="till">{{ __('Till (STK Push)') }}</option>
                    <option value="paybill">{{ __('Paybill') }}</option>
                </select>
            </div>
            <button class="btn btn-success w-100">{{ __('Pay now') }}</button>
        </form>
    </div>
</div>
</body>
</html>
