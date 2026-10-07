@extends('layouts.app')

@section('title', 'Edit Pre-Alert')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Edit Pre-Alert</h1>
    <p class="text-muted mb-0">{{ $preAlert->pre_alert_number }}</p>
</div>

<form method="POST" action="{{ route('customer.pre-alerts.update', $preAlert) }}" class="panel p-4" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('customer.pre-alerts._form', ['button' => 'Save Pre-Alert'])
</form>
@endsection
