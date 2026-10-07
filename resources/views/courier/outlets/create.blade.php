@extends('layouts.app')

@section('title', 'Add Branch')
@section('page_title', 'Add Branch')
@section('breadcrumb', 'Branch / Add')

@section('content')
<div class="mb-4"><h1 class="h3 mb-1">Add Branch</h1><p class="text-muted mb-0">Create a branch and optionally create its staff login.</p></div>
<form method="POST" action="{{ route('courier.outlets.store') }}" class="panel p-4">@csrf @include('courier.outlets.form')</form>
@endsection
