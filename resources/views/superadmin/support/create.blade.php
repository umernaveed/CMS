@extends('layouts.app')

@section('title', 'New Support Ticket')
@section('page_title', 'New Support Ticket')
@section('breadcrumb', 'Support Tickets / New')

@section('content')
<form method="POST" action="{{ route('superadmin.support.store') }}" class="panel p-4">
    @csrf
    @include('superadmin.support.form', ['ticket' => null])
</form>
@endsection
