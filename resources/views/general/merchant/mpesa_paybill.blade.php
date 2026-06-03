<!DOCTYPE html>
<html>
<head><title>{{ __('M-PESA Paybill') }}</title></head>
<body class="p-4">
    <h4>{{ __('Pay via Paybill') }}</h4>
    @if($intent?->next_action_data)
        <p>{{ __('Paybill') }}: <strong>{{ $intent->next_action_data['paybill'] ?? '' }}</strong></p>
        <p>{{ __('Account No') }}: <strong>{{ $intent->next_action_data['account_ref'] ?? '' }}</strong></p>
        <p>{{ __('Amount') }}: <strong>{{ $intent->next_action_data['amount'] ?? '' }} KES</strong></p>
        <p class="text-muted">{{ $intent->next_action_data['instructions'] ?? '' }}</p>
    @endif
</body>
</html>
