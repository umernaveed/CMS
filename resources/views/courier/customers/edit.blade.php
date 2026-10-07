@extends('layouts.app')

@section('title', 'Edit Customer')

@section('content')
<div class="mb-4"><h1 class="h3 mb-1">Edit Customer</h1><p class="text-muted mb-0">{{ $customer->mailbox }}</p></div>
<form method="POST" action="{{ route('courier.customers.update', $customer) }}" class="panel p-4">@csrf @method('PUT') @include('courier.customers.form', ['customer' => $customer])</form>
@endsection
