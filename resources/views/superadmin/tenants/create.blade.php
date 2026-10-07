@extends('layouts.app')

@section('title', 'Create Courier')

@section('content')
<div class="mb-4"><h1 class="h3 mb-1">Create Courier</h1><p class="text-muted mb-0">Create a courier company, main outlet, admin login, and subscription.</p></div>
<form method="POST" action="{{ route('superadmin.tenants.store') }}" class="panel p-4">
    @csrf
    @include('superadmin.tenants.form', ['tenant' => null])
</form>
@endsection
