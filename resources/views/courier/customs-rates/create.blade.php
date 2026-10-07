@extends('layouts.app')

@section('title', 'Add Customs Rate')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Add Customs Rate</h1>
    <p class="text-muted mb-0">Create an HS-code duty rate for the calculator.</p>
</div>

<form method="POST" action="{{ route('courier.customs-rates.store') }}" class="panel p-4">
    @csrf
    @include('courier.customs-rates.form')
</form>
@endsection
