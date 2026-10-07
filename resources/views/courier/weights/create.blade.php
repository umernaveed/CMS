@extends('layouts.app')

@section('title', 'Add Weight Rate')
@section('page_title', 'Add Weight Rate')
@section('breadcrumb', 'Manage Weight / Add')

@section('content')
<form method="POST" action="{{ route('courier.weights.store') }}" class="panel p-4">
    @csrf
    @include('courier.weights.form')
</form>
@endsection
