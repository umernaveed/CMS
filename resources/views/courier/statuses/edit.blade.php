@extends('layouts.app')

@section('title', 'Edit Package Status')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Edit Package Status</h1><p class="text-muted mb-0">{{ $status->name }}</p></div>
    <form method="POST" action="{{ route('courier.statuses.destroy', $status) }}" onsubmit="return confirm('Delete this status?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete</button></form>
</div>
<form method="POST" action="{{ route('courier.statuses.update', $status) }}" class="panel p-4">
    @csrf @method('PUT')
    @include('courier.statuses.form')
</form>
@endsection
