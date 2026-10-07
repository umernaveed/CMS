@extends('layouts.app')

@section('title', 'Edit Support Ticket')
@section('page_title', 'Edit Support Ticket')
@section('breadcrumb', 'Support Tickets / Edit')

@section('content')
<div class="panel p-4">
    <h1 class="h4 mb-2">Support Ticket Editing Moved</h1>
    <p class="text-muted mb-3">Support conversations are managed from the ticket thread using replies and status updates.</p>
    <a href="{{ route('superadmin.support.show', $ticket) }}" class="btn btn-primary">Open Conversation</a>
</div>
@endsection
