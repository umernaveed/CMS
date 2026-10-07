@extends('layouts.app')

@section('title', 'Edit Weight Rate')
@section('page_title', 'Edit Weight Rate')
@section('breadcrumb', 'Manage Weight / Edit')

@section('content')
<form method="POST" action="{{ route('courier.weights.update', $rate) }}" class="panel p-4">
    @csrf
    @method('PUT')
    @include('courier.weights.form')
</form>
<form method="POST" action="{{ route('courier.weights.destroy', $rate) }}" onsubmit="return confirm('Delete this weight rate?')" class="mt-3">
    @csrf
    @method('DELETE')
    <button class="btn btn-outline-danger">Delete Rate</button>
</form>
@endsection
