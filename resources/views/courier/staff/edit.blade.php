@extends('layouts.app')

@section('title', 'Edit Staff User')
@section('page_title', 'Edit Staff User')
@section('breadcrumb', 'Staff Users / Edit')

@section('content')
<form method="POST" action="{{ route('courier.staff.update', $staff) }}" class="panel p-4">
    @csrf
    @method('PUT')
    @include('courier.staff.form')
</form>
@if($staff->id !== auth()->id())
    <form method="POST" action="{{ route('courier.staff.destroy', $staff) }}" class="mt-3" onsubmit="return confirm('Delete this staff user?')">
        @csrf
        @method('DELETE')
        <button class="btn btn-outline-danger">Delete Staff User</button>
    </form>
@endif
@endsection
