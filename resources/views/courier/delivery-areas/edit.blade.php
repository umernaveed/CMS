@extends('layouts.app')

@section('title', 'Edit Delivery Location')

@section('content')
<div class="mb-4"><h1 class="h3 mb-1">Edit Delivery Location</h1><p class="text-muted mb-0">{{ $area->name }}</p></div>
<form method="POST" action="{{ route('courier.delivery-areas.update', $area) }}">
    @csrf
    @method('PUT')
    @include('courier.delivery-areas._form', ['button' => 'Update Location'])
</form>
@include('courier.delivery-areas._schedule', ['area' => $area])
@endsection
