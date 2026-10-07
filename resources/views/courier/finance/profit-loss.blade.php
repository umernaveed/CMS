@extends('layouts.app')

@section('title', 'Profit / Loss')

@section('content')
@include('courier.finance._toolbar', ['title' => 'Profit / Loss', 'subtitle' => 'Paid invoice income minus expenses for the selected period.', 'section' => 'profit-loss'])
@include('courier.finance._summary-filter')
@include('courier.finance._summary-cards')
@endsection
