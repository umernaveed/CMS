@extends('layouts.app')

@section('title', 'Add Package Status')

@section('content')
<div class="mb-4"><h1 class="h3 mb-1">Add Package Status</h1><p class="text-muted mb-0">Add a custom status for courier package tracking.</p></div>
<form method="POST" action="{{ route('courier.statuses.store') }}" class="panel p-4">
    @csrf
    @include('courier.statuses.form')
</form>
@endsection
