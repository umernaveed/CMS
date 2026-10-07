@extends('layouts.app')

@section('title', 'New Support Chat')
@section('page_title', 'New Support Chat')
@section('breadcrumb', 'New Support Chat')

@section('content')
<div class="row justify-content-center">
    <div class="col-xl-8">
        <form method="POST" action="{{ route($routeBase.'.store') }}" enctype="multipart/form-data" class="panel p-4">
            @csrf
            <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <h1 class="h4 mb-1">Start a support chat</h1>
                    <p class="text-muted mb-0">Describe the issue and the right team will reply in this conversation.</p>
                </div>
                <a href="{{ route($routeBase.'.index') }}" class="btn btn-outline-secondary">Back</a>
            </div>

            <div class="row g-3">
                @if($canCreateForOthers)
                    <div class="col-md-6">
                        <label class="form-label">Courier</label>
                        <select name="tenant_id" class="form-select @error('tenant_id') is-invalid @enderror" required>
                            <option value="">Select courier</option>
                            @foreach($tenants as $tenant)
                                <option value="{{ $tenant->id }}" @selected(old('tenant_id') == $tenant->id)>{{ $tenant->company_name }}</option>
                            @endforeach
                        </select>
                        @error('tenant_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Courier Contact</label>
                        <select name="user_id" class="form-select">
                            <option value="">Start as System Admin</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" data-tenant-id="{{ $user->tenant_id }}" @selected(old('user_id') == $user->id)>{{ $user->full_name ?: $user->email }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Customer users are not available to System Admin.</div>
                    </div>
                @endif

                <div class="col-md-4">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select">
                        @foreach(['low', 'medium', 'high', 'urgent'] as $priority)
                            <option value="{{ $priority }}" @selected(old('priority', 'medium') === $priority)>{{ ucfirst($priority) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Subject</label>
                    <input name="subject" class="form-control @error('subject') is-invalid @enderror" value="{{ old('subject') }}" required>
                    @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Message</label>
                    <textarea name="message" class="form-control @error('message') is-invalid @enderror" rows="7" required>{{ old('message') }}</textarea>
                    @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Attachment</label>
                    <input type="file" name="attachment" class="form-control">
                    <div class="form-text">Optional. Max 5MB.</div>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary btn-lg"><i class="fas fa-paper-plane me-2"></i>Start Chat</button>
                </div>
            </div>
        </form>
    </div>
</div>
@if($canCreateForOthers)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tenantSelect = document.querySelector('select[name="tenant_id"]');
    const userSelect = document.querySelector('select[name="user_id"]');
    if (!tenantSelect || !userSelect) return;

    function syncCourierContacts() {
        const tenantId = tenantSelect.value;
        userSelect.querySelectorAll('option[data-tenant-id]').forEach(function (option) {
            option.hidden = tenantId && option.dataset.tenantId !== tenantId;
            if (option.hidden && option.selected) {
                userSelect.value = '';
            }
        });
    }

    tenantSelect.addEventListener('change', syncCourierContacts);
    syncCourierContacts();
});
</script>
@endif
@endsection
