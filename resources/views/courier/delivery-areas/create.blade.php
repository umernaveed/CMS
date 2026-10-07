@extends('layouts.app')

@section('title', 'Create Delivery Location')

@section('content')
<div class="mb-4"><h1 class="h3 mb-1">Create Delivery Location</h1><p class="text-muted mb-0">Set the rate and geofence coverage for this location.</p></div>
<form method="POST" action="{{ route('courier.delivery-areas.store') }}">
    @csrf
    @include('courier.delivery-areas._form', ['button' => 'Create Location'])
</form>
@endsection
