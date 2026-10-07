@extends('layouts.app')

@section('title', 'Create Plan')

@section('content')
<div class="mb-4"><h1 class="h3 mb-1">Create Plan</h1></div>
<form method="POST" action="{{ route('superadmin.plans.store') }}" class="panel p-4">@csrf @include('superadmin.plans.form', ['plan' => null])</form>
@endsection
