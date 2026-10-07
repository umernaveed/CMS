@extends('layouts.app')

@section('title', 'Add Pre-Alert')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Add Package Pre-Alert</h1>
    <p class="text-muted mb-0">Tell the courier about an expected online order before it reaches the warehouse.</p>
</div>

<form method="POST" action="{{ route('customer.pre-alerts.store') }}" class="panel p-4" enctype="multipart/form-data">
    @csrf
    @include('customer.pre-alerts._form', ['button' => 'Submit Pre-Alert'])
</form>
@endsection
