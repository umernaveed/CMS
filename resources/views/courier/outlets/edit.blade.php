@extends('layouts.app')

@section('title', 'Edit Branch')
@section('page_title', 'Edit Branch')
@section('breadcrumb', 'Branch / Edit')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Edit Branch</h1><p class="text-muted mb-0">{{ $outlet->name }}</p></div>
    @unless($outlet->is_main)
        <form method="POST" action="{{ route('courier.outlets.destroy', $outlet) }}" onsubmit="return confirm('Delete this branch?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete</button></form>
    @endunless
</div>
<form method="POST" action="{{ route('courier.outlets.update', $outlet) }}" class="panel p-4">@csrf @method('PUT') @include('courier.outlets.form')</form>
@endsection
