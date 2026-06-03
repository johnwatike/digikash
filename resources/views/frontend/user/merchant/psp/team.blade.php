@extends('frontend.layouts.user.index')
@section('title', __('Merchant Team'))
@section('content')
<div class="card single-form-card p-4">
    <h4>{{ __('Team & RBAC') }}</h4>
    <form method="POST" action="{{ route('user.merchant.team.store', $merchant) }}" class="mb-4 row g-2">
        @csrf
        <div class="col-md-5"><input type="email" name="email" class="form-control" placeholder="{{ __('User email') }}" required></div>
        <div class="col-md-4">
            <select name="role" class="form-select">
                <option value="admin">{{ __('Admin') }}</option>
                <option value="developer">{{ __('Developer') }}</option>
                <option value="finance">{{ __('Finance') }}</option>
                <option value="support">{{ __('Support') }}</option>
            </select>
        </div>
        <div class="col-md-3"><button class="btn btn-primary w-100">{{ __('Invite') }}</button></div>
    </form>
    <ul class="list-group">
        @foreach($members as $member)
            <li class="list-group-item">{{ $member->user?->email }} — <span class="badge bg-info">{{ $member->role->value }}</span></li>
        @endforeach
    </ul>
    <a href="{{ route('user.merchant.config', $merchant) }}" class="btn btn-outline-secondary btn-sm mt-3">{{ __('Back') }}</a>
</div>
@endsection
