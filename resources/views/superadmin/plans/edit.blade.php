@extends('layouts.app')

@section('title', 'Edit Plan')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Edit Plan</h1><p class="text-muted mb-0">{{ $plan->name }}</p></div>
    <form method="POST" action="{{ route('superadmin.plans.destroy', $plan) }}" onsubmit="return confirm('Delete this plan?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete</button></form>
</div>
<form method="POST" action="{{ route('superadmin.plans.update', $plan) }}" class="panel p-4">@csrf @method('PUT') @include('superadmin.plans.form', ['plan' => $plan])</form>
@endsection
