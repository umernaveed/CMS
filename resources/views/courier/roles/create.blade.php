@extends('layouts.app')

@section('title', 'New Role')
@section('page_title', 'Roles')
@section('breadcrumb', 'Roles / New')

@section('content')
<form method="POST" action="{{ route('courier.roles.store') }}">
    @csrf
    @include('courier.roles.form', ['role' => null])
</form>
@endsection
