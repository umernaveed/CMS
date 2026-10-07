@extends('layouts.app')

@section('title', 'Add Customer')

@section('content')
<div class="mb-4"><h1 class="h3 mb-1">Add Customer</h1></div>
<form method="POST" action="{{ route('courier.customers.store') }}" class="panel p-4">@csrf @include('courier.customers.form', ['customer' => null])</form>
@endsection
