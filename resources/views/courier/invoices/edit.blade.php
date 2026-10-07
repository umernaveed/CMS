@extends('layouts.app')

@section('title', 'Edit Invoice')

@section('content')
<div class="mb-4"><h1 class="h3 mb-1">Edit Invoice</h1><p class="text-muted mb-0">{{ $invoice->invoice_number }}</p></div>
<form id="courierInvoiceForm" method="POST" action="{{ route('courier.invoices.update', $invoice) }}" class="panel p-4">@csrf @method('PUT') @include('courier.invoices.form', ['invoice' => $invoice])</form>
@endsection
