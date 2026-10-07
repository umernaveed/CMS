@extends('layouts.app')

@section('title', 'Edit Manifest')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Edit Manifest</h1>
    <p class="text-muted mb-0">{{ $manifest->manifest_number }}</p>
</div>

<form method="POST" action="{{ route('courier.manifests.update', $manifest) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    @include('courier.manifests._form', ['button' => 'Update Manifest'])
</form>
@endsection
