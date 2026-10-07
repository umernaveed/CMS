@extends('layouts.app')

@section('title', 'Edit Courier')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Edit Courier</h1><p class="text-muted mb-0">{{ $tenant->company_name }}</p></div>
    @if(auth()->user()->hasPermission('superadmin_courier_workspaces_delete'))
    <form method="POST" action="{{ route('superadmin.tenants.destroy', $tenant) }}" onsubmit="return confirm('Delete this courier and all courier data?')">
        @csrf @method('DELETE')
        <button class="btn btn-outline-danger">Delete</button>
    </form>
    @endif
</div>
<form method="POST" action="{{ route('superadmin.tenants.update', $tenant) }}" class="panel p-4">
    @csrf @method('PUT')
    @include('superadmin.tenants.form', ['tenant' => $tenant])
</form>
@endsection
