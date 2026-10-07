@extends('layouts.app')

@section('title', 'Add Package')

@section('content')
<div class="mb-4"><h1 class="h3 mb-1">Add Package</h1><p class="text-muted mb-0">Record a shipment received for a customer mailbox.</p></div>
@if(! empty($logisticsPackage))
    <div class="alert alert-info">
        Creating an in-house package from warehouse tracking <strong>{{ $logisticsPackage->tracking_number }}</strong>.
        Assign a customer/mailbox, save it, then add it to an in-house manifest when ready.
    </div>
@endif
<form method="POST" action="{{ route('courier.packages.store') }}" class="panel p-4" enctype="multipart/form-data">@csrf @include('courier.packages.form', ['package' => null])</form>
@endsection
