@extends('layouts.app')

@section('title', 'Create Manifest')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Create Manifest</h1>
    <p class="text-muted mb-0">Enter shipment header details and select packages for this manifest.</p>
</div>

<form method="POST" action="{{ route('courier.manifests.store') }}" enctype="multipart/form-data">
    @csrf
    @include('courier.manifests._form', ['button' => 'Create Manifest'])
</form>
@endsection
