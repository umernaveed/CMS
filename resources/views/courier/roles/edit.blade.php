@extends('layouts.app')

@section('title', 'Edit Role')
@section('page_title', 'Roles')
@section('breadcrumb', 'Roles / Edit')

@section('content')
<form method="POST" action="{{ route('courier.roles.update', $role) }}">
    @csrf
    @method('PUT')
    @include('courier.roles.form')
</form>
@if(!$role->is_system)
    <form method="POST" action="{{ route('courier.roles.destroy', $role) }}" class="mt-3" onsubmit="return confirm('Delete this role?')" style="max-width: 760px;">
        @csrf
        @method('DELETE')
        <button class="btn btn-outline-danger">Delete Role</button>
    </form>
@endif
@endsection
