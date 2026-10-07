@extends('layouts.app')

@section('title', 'Create Invoice')

@section('content')
<div class="mb-4"><h1 class="h3 mb-1">Create Invoice</h1></div>
<form id="courierInvoiceForm" method="POST" action="{{ route('courier.invoices.store') }}" class="panel p-4">@csrf @include('courier.invoices.form', ['invoice' => null])</form>
@endsection
