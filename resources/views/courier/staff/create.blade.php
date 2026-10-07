@extends('layouts.app')

@section('title', 'New Staff User')
@section('page_title', 'New Staff User')
@section('breadcrumb', 'Staff Users / New')

@section('content')
<form method="POST" action="{{ route('courier.staff.store') }}" class="panel p-4">
    @csrf
    @include('courier.staff.form', ['staff' => null])
</form>
@endsection
