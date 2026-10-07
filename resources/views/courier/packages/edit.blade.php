@extends('layouts.app')

@section('title', 'Edit Package')

@section('content')
<div class="mb-4"><h1 class="h3 mb-1">Edit Package</h1><p class="text-muted mb-0">{{ $package->tracking_number }}</p></div>
<form method="POST" action="{{ route('courier.packages.update', $package) }}" class="panel p-4" enctype="multipart/form-data">@csrf @method('PUT') @include('courier.packages.form', ['package' => $package])</form>
@endsection
