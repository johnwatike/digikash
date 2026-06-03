@extends('frontend.layouts.user.index')
@section('title', __('Webhook Console'))
@section('content')
<div class="card single-form-card">
    <div class="card-main p-4">
        <h4>{{ __('Webhook Console') }} — {{ $merchant->business_name }}</h4>
        <p class="text-muted">{{ __('Delivery logs, replay, and endpoint management.') }}</p>

        <div class="mb-4">
            <h5>{{ __('Add endpoint') }}</h5>
            <form method="POST" action="{{ route('user.merchant.webhooks.store', $merchant) }}">
                @csrf
                <div class="mb-2">
                    <input type="url" name="url" class="form-control" placeholder="https://example.com/webhooks" required>
                </div>
                <div class="mb-2">
                    <input type="text" name="events" class="form-control" placeholder="payment_intent.succeeded, payment.completed or *">
                </div>
                <button class="btn btn-primary btn-sm">{{ __('Save endpoint') }}</button>
            </form>
        </div>

        <h5>{{ __('Endpoints') }}</h5>
        <ul class="list-group mb-4">
            @forelse($endpoints as $endpoint)
                <li class="list-group-item">
                    <strong>{{ $endpoint->url }}</strong>
                    <span class="badge bg-secondary">{{ $endpoint->status }}</span>
                    <small class="d-block text-muted">{{ implode(', ', $endpoint->events ?? ['*']) }}</small>
                </li>
            @empty
                <li class="list-group-item text-muted">{{ __('No webhook endpoints yet.') }}</li>
            @endforelse
        </ul>

        <h5>{{ __('Recent deliveries') }}</h5>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Event') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('HTTP') }}</th>
                        <th>{{ __('Attempts') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($deliveries as $delivery)
                        <tr>
                            <td>{{ $delivery->webhookEvent?->type }}</td>
                            <td>{{ $delivery->status }}</td>
                            <td>{{ $delivery->http_status ?? '-' }}</td>
                            <td>{{ $delivery->attempt }}</td>
                            <td>
                                @if($delivery->status !== 'delivered')
                                    <form method="POST" action="{{ route('user.merchant.webhooks.replay', [$merchant, $delivery->id]) }}">
                                        @csrf
                                        <button class="btn btn-link btn-sm p-0">{{ __('Replay') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <a href="{{ route('user.merchant.config', $merchant) }}" class="btn btn-outline-secondary btn-sm">{{ __('Back') }}</a>
    </div>
</div>
@endsection
