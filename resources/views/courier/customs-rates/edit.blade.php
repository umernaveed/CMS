@extends('layouts.app')

@section('title', 'Edit Customs Rate')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Edit Customs Rate</h1>
    <p class="text-muted mb-0">Update the HS-code duty rate used by the calculator.</p>
</div>

<form method="POST" action="{{ route('courier.customs-rates.update', $rate) }}" class="panel p-4">
    @csrf
    @method('PUT')
    @include('courier.customs-rates.form')
</form>
@endsection
